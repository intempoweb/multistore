<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\DocumentReturnRequest;
use App\Http\Requests\Storefront\DocumentSupportTicketRequest;
use App\Mail\Storefront\Documents\CustomerDocumentRequestMail;
use App\Models\Customer;
use App\Models\CustomerRequestAttachment;
use App\Models\CustomerReturn;
use App\Models\CustomerSupportTicket;
use App\Models\Erp\DocumentHeader;
use App\Models\Erp\DocumentRow;
use App\Services\Storefront\Documents\CustomerDocumentAccessService;
use App\Services\Storefront\Documents\DocumentProductResolver;
use App\Services\Storefront\Mail\StorefrontMailService;
use App\Services\Storefront\ThemeResolver;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerDocumentRequestsController extends Controller
{
    public function __construct(
        private CustomerDocumentAccessService $documentAccess,
        private DocumentProductResolver $productResolver,
        private ThemeResolver $themeResolver,
        private StorefrontMailService $mailService,
    ) {}

    public function createReturn(Request $request, string $document): View
    {
        [$store, $customer, $documentHeader] = $this->documentContext($request, $document);

        return view('storefront.base.pages.account.documents.return', [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'customer' => $customer,
            'document' => $documentHeader,
            'rows' => collect($documentHeader->rows ?? []),
            'agentContext' => (string) $request->query('agent_context', ''),
            'contextParams' => $this->contextParams($request),
            'requestToken' => $this->issueRequestToken($request, 'return', $document),
            'contactDefaults' => $this->contactDefaults($customer),
            'receiptRequired' => $this->returnReceiptRequired($documentHeader),
            'returnWindowLabel' => '2 anni',
        ]);
    }

    public function storeReturn(DocumentReturnRequest $request, string $document): RedirectResponse
    {
        [$store, $customer, $documentHeader] = $this->documentContext($request, $document);

        $selectedItems = $this->validatedReturnItems($request, $documentHeader);

        if ($selectedItems->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['items' => 'Seleziona almeno una riga da rendere.']);
        }

        if ($this->returnReceiptRequired($documentHeader) && !$request->hasFile('attachments')) {
            throw ValidationException::withMessages([
                'attachments' => 'Per questo documento devi allegare ricevuta fiscale o scontrino che attesti la vendita entro due anni.',
            ]);
        }

        if (!$this->consumeRequestToken($request, 'return', $document)) {
            return back()
                ->withInput()
                ->with('error', 'Richiesta già inviata o sessione scaduta. Riapri il form e riprova.');
        }

        try {
            $customerReturn = DB::transaction(function () use ($request, $store, $customer, $documentHeader, $selectedItems) {
                $customerReturn = CustomerReturn::query()->create([
                    ...$this->documentPayload($store, $customer, $documentHeader),
                    'status' => CustomerReturn::STATUS_OPEN,
                    ...$this->contactPayload($request),
                    'notes' => trim((string) $request->input('notes', '')) ?: null,
                    'terms_accepted_at' => now(),
                ]);

                $customerReturn->forceFill([
                    'request_number' => 'RES-' . now()->format('Y') . '-' . str_pad((string) $customerReturn->id, 6, '0', STR_PAD_LEFT),
                ])->save();

                foreach ($selectedItems as $item) {
                    $customerReturn->items()->create($item);
                }

                $this->storeAttachments(
                    request: $request,
                    type: CustomerRequestAttachment::TYPE_RETURN,
                    requestId: (int) $customerReturn->id,
                    customerId: (int) $customer->id,
                    storeId: (int) $store->id
                );

                return $customerReturn->load(['items', 'attachments']);
            });

            $this->notifyInternal($store, 'return', $customerReturn);
            Log::info('Customer document return created', [
                'return_id' => $customerReturn->id,
                'request_number' => $customerReturn->request_number,
                'customer_id' => $customer->id,
                'document' => $documentHeader->NUMREG_CO99,
            ]);

            return redirect()
                ->route('storefront.account.documents.show', array_merge(
                    ['document' => $documentHeader->NUMREG_CO99],
                    $this->contextParams($request)
                ))
                ->with('success', 'Richiesta di reso ' . $customerReturn->request_number . ' inviata correttamente.');
        } catch (\Throwable $exception) {
            Log::error('Customer document return failed', [
                'message' => $exception->getMessage(),
                'customer_id' => $customer->id,
                'document' => $documentHeader->NUMREG_CO99,
            ]);

            return back()
                ->withInput()
                ->with('error', 'Non è stato possibile salvare la richiesta. Riprova più tardi.');
        }
    }

    public function createSupport(Request $request, string $document): View
    {
        [$store, $customer, $documentHeader] = $this->documentContext($request, $document);

        return view('storefront.base.pages.account.documents.support', [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'customer' => $customer,
            'document' => $documentHeader,
            'rows' => collect($documentHeader->rows ?? []),
            'agentContext' => (string) $request->query('agent_context', ''),
            'contextParams' => $this->contextParams($request),
            'requestToken' => $this->issueRequestToken($request, 'support', $document),
            'contactDefaults' => $this->contactDefaults($customer),
        ]);
    }

    public function storeSupport(DocumentSupportTicketRequest $request, string $document): RedirectResponse
    {
        [$store, $customer, $documentHeader] = $this->documentContext($request, $document);

        if (!$this->consumeRequestToken($request, 'support', $document)) {
            return back()
                ->withInput()
                ->with('error', 'Ticket già inviato o sessione scaduta. Riapri il form e riprova.');
        }

        try {
            $ticket = DB::transaction(function () use ($request, $store, $customer, $documentHeader) {
                $ticket = CustomerSupportTicket::query()->create([
                    ...$this->documentPayload($store, $customer, $documentHeader),
                    'status' => CustomerSupportTicket::STATUS_OPEN,
                    'subject' => trim((string) $request->input('subject')),
                    ...$this->contactPayload($request),
                    'message' => trim((string) $request->input('message')),
                    'terms_accepted_at' => now(),
                ]);

                $ticket->forceFill([
                    'ticket_number' => 'TCK-' . now()->format('Y') . '-' . str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT),
                ])->save();

                foreach ($this->validatedSupportItems($request, $documentHeader) as $item) {
                    $ticket->items()->create($item);
                }

                $this->storeAttachments(
                    request: $request,
                    type: CustomerRequestAttachment::TYPE_SUPPORT_TICKET,
                    requestId: (int) $ticket->id,
                    customerId: (int) $customer->id,
                    storeId: (int) $store->id
                );

                return $ticket->load(['items', 'attachments']);
            });

            $this->notifyInternal($store, 'support', $ticket);
            Log::info('Customer document support ticket created', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'customer_id' => $customer->id,
                'document' => $documentHeader->NUMREG_CO99,
            ]);

            return redirect()
                ->route('storefront.account.documents.show', array_merge(
                    ['document' => $documentHeader->NUMREG_CO99],
                    $this->contextParams($request)
                ))
                ->with('success', 'Ticket assistenza ' . $ticket->ticket_number . ' inviato correttamente.');
        } catch (\Throwable $exception) {
            Log::error('Customer document support ticket failed', [
                'message' => $exception->getMessage(),
                'customer_id' => $customer->id,
                'document' => $documentHeader->NUMREG_CO99,
            ]);

            return back()
                ->withInput()
                ->with('error', 'Non è stato possibile salvare il ticket. Riprova più tardi.');
        }
    }

    private function documentContext(Request $request, string $document): array
    {
        $store = current_store();
        abort_if($store->isB2C(), 404);

        $customer = $this->documentAccess->resolveCustomer($request, $store);
        abort_unless($customer instanceof Customer, 403);

        $documentHeader = $this->documentAccess->resolveDocument($request, $store, $document);
        $this->productResolver->attachProducts($documentHeader, $store);

        return [$store, $customer, $documentHeader];
    }

    private function validatedReturnItems(DocumentReturnRequest $request, DocumentHeader $document): Collection
    {
        $submitted = collect($request->input('items', []));
        $rows = $this->documentRowsByNumber($document);
        $errors = [];
        $items = collect();

        foreach ($submitted as $rowNumber => $payload) {
            if (!is_array($payload) || empty($payload['selected'])) {
                continue;
            }

            $rowKey = (string) $rowNumber;
            $row = $rows->get($rowKey);
            $rowErrors = [];

            if (!$row instanceof DocumentRow) {
                continue;
            }

            $quantity = (float) str_replace(',', '.', (string) ($payload['quantity'] ?? 0));
            $documentQuantity = (float) ($row->QTA1_DO30 ?? 0);
            $reason = trim((string) ($payload['reason'] ?? ''));

            if ($quantity <= 0) {
                $rowErrors["items.$rowKey.quantity"] = 'Inserisci una quantità maggiore di zero.';
            }

            if ($documentQuantity > 0 && $quantity > $documentQuantity) {
                $rowErrors["items.$rowKey.quantity"] = 'La quantità da rendere non può superare la quantità documento.';
            }

            if ($reason === '') {
                $rowErrors["items.$rowKey.reason"] = 'Indica il motivo del reso.';
            }

            if (!empty($rowErrors)) {
                $errors = array_merge($errors, $rowErrors);
                continue;
            }

            $items->push([
                'erp_row_number' => $rowKey,
                'sku' => trim((string) ($row->CODART_MG66 ?? '')) ?: null,
                'description' => trim((string) ($row->DESCART_DO30 ?? '')) ?: null,
                'unit' => trim((string) ($row->UM1_DO30 ?? '')) ?: null,
                'document_quantity' => $documentQuantity,
                'requested_quantity' => $quantity,
                'reason' => $reason,
                'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
            ]);
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $items;
    }

    private function validatedSupportItems(DocumentSupportTicketRequest $request, DocumentHeader $document): Collection
    {
        $submitted = collect($request->input('items', []));
        $rows = $this->documentRowsByNumber($document);
        $items = collect();

        foreach ($submitted as $rowNumber => $payload) {
            if (!is_array($payload) || empty($payload['selected'])) {
                continue;
            }

            $row = $rows->get((string) $rowNumber);

            if (!$row instanceof DocumentRow) {
                continue;
            }

            $items->push([
                'erp_row_number' => (string) $rowNumber,
                'sku' => trim((string) ($row->CODART_MG66 ?? '')) ?: null,
                'description' => trim((string) ($row->DESCART_DO30 ?? '')) ?: null,
                'unit' => trim((string) ($row->UM1_DO30 ?? '')) ?: null,
                'document_quantity' => (float) ($row->QTA1_DO30 ?? 0),
            ]);
        }

        return $items;
    }

    private function documentRowsByNumber(DocumentHeader $document): Collection
    {
        return collect($document->rows ?? [])
            ->filter(fn ($row) => $row instanceof DocumentRow)
            ->keyBy(fn (DocumentRow $row) => (string) ($row->PROGRIGA_DO30 ?? ''));
    }

    private function documentPayload($store, Customer $customer, DocumentHeader $document): array
    {
        return [
            'customer_id' => (int) $customer->id,
            'store_id' => (int) $store->id,
            'ditta_cg18' => (int) ($document->DITTA_CG18 ?? $customer->ditta_cg18),
            'clifor_cg44' => (int) ($document->CLIFOR_CG44 ?? $customer->clifor_cg44),
            'numreg_co99' => (string) $document->NUMREG_CO99,
            'document_number' => method_exists($document, 'documentNumberForDisplay') ? $document->documentNumberForDisplay() : (string) ($document->NUMSEZDOC_DO11 ?? ''),
            'document_type' => method_exists($document, 'documentTypeForDisplay') ? $document->documentTypeForDisplay() : (string) ($document->TIPODOCDECOD_MG36 ?? ''),
            'document_date' => trim((string) ($document->DATADOC_DO11 ?? '')) ?: null,
        ];
    }

    private function contactPayload(Request $request): array
    {
        return [
            'contact_name' => trim((string) $request->input('contact_name')),
            'contact_email' => trim((string) $request->input('contact_email')),
            'contact_phone' => trim((string) $request->input('contact_phone')) ?: null,
            'address_line' => trim((string) $request->input('address_line')) ?: null,
            'city' => trim((string) $request->input('city')) ?: null,
            'postcode' => trim((string) $request->input('postcode')) ?: null,
            'province' => trim((string) $request->input('province')) ?: null,
        ];
    }

    private function contactDefaults(Customer $customer): array
    {
        return [
            'contact_name' => trim((string) ($customer->ragsoanag_cg16 ?: ($customer->nomeconnweb . ' ' . $customer->cognomeconnweb))),
            'contact_email' => trim((string) ($customer->indemail_cg16 ?? '')),
            'contact_phone' => trim((string) ($customer->tel1num_cg16 ?: $customer->cellnum_cg16)),
            'address_line' => trim((string) ($customer->indirizzo_cg16 ?? '')),
            'city' => trim((string) ($customer->citta_cg16 ?? '')),
            'postcode' => trim((string) ($customer->cap_cg16 ?? '')),
            'province' => trim((string) ($customer->prov_cg16 ?? '')),
        ];
    }

    private function storeAttachments(Request $request, string $type, int $requestId, int $customerId, int $storeId): void
    {
        $files = $request->file('attachments', []);
        $files = is_array($files) ? $files : [$files];
        $disk = 'local';

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $filename = Str::uuid() . '-' . $this->safeFilename($file->getClientOriginalName());
            $path = $file->storeAs("customer-requests/$storeId/$type/$requestId", $filename, $disk);

            CustomerRequestAttachment::query()->create([
                'request_type' => $type,
                'request_id' => $requestId,
                'customer_id' => $customerId,
                'store_id' => $storeId,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ]);
        }
    }

    private function notifyInternal($store, string $type, $requestModel): void
    {
        $recipient = $this->mailService->internalRecipientForStore($store);

        if ($recipient === null) {
            Log::warning('Customer document request notification skipped: no recipient', [
                'store_id' => $store->id,
                'type' => $type,
                'request_id' => $requestModel->id,
            ]);

            return;
        }

        try {
            Mail::to($recipient)->send(new CustomerDocumentRequestMail($store, $type, $requestModel));
        } catch (\Throwable $exception) {
            Log::error('Customer document request notification failed', [
                'message' => $exception->getMessage(),
                'store_id' => $store->id,
                'type' => $type,
                'request_id' => $requestModel->id,
            ]);
        }
    }

    private function returnReceiptRequired(DocumentHeader $document): bool
    {
        return !$this->isInvoiceInsideReturnWindow($document);
    }

    private function isInvoiceInsideReturnWindow(DocumentHeader $document): bool
    {
        $type = strtoupper(trim((string) ($document->TIPODOCDECOD_MG36 ?? '')));

        if (!str_contains($type, 'FATT')) {
            return false;
        }

        $date = $this->documentDate($document);

        if (!$date instanceof Carbon) {
            return false;
        }

        return $date->greaterThanOrEqualTo(now()->subYears(2)->startOfDay());
    }

    private function documentDate(DocumentHeader $document): ?Carbon
    {
        $value = trim((string) ($document->DATADOC_DO11 ?? ''));

        if ($value === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                return Carbon::parse($value)->startOfDay();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function issueRequestToken(Request $request, string $type, string $document): string
    {
        $token = Str::random(40);
        $request->session()->put($this->tokenKey($type, $document), $token);

        return $token;
    }

    private function consumeRequestToken(Request $request, string $type, string $document): bool
    {
        $key = $this->tokenKey($type, $document);
        $expected = (string) $request->session()->pull($key, '');
        $actual = (string) $request->input('request_token', '');

        return $expected !== '' && hash_equals($expected, $actual);
    }

    private function tokenKey(string $type, string $document): string
    {
        return 'document_request_tokens.' . $type . '.' . $document;
    }

    private function contextParams(Request $request): array
    {
        $agentContext = trim((string) $request->query('agent_context', $request->input('agent_context', '')));

        return $agentContext !== '' ? ['agent_context' => $agentContext] : [];
    }

    private function safeFilename(string $value): string
    {
        $extension = pathinfo($value, PATHINFO_EXTENSION);
        $basename = pathinfo($value, PATHINFO_FILENAME);
        $safeBasename = Str::slug($basename) ?: 'allegato';
        $safeExtension = Str::slug($extension, '');

        return $safeExtension !== ''
            ? $safeBasename . '.' . $safeExtension
            : $safeBasename;
    }
}
