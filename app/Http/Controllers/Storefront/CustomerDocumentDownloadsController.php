<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Erp\DocumentHeader;
use App\Services\Storefront\Documents\DocumentExcelExportService;
use App\Services\Storefront\Documents\DocumentProductImagesZipService;
use App\Services\Storefront\Documents\DocumentProductResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerDocumentDownloadsController extends Controller
{
    private function initErpSession(): void
    {
        DB::connection('erp')
            ->unprepared(
                'SET ANSI_NULLS ON; SET ANSI_WARNINGS ON;'
            );
    }

    private function isAgentMode(Request $request): bool
    {
        return (bool) $request->session()->get(
            'agent_mode',
            false
        );
    }

    private function resolveCustomer(Request $request): ?Customer
    {
        $store = current_store();
        $contextId = (string) $request->query('agent_context', '');

        if ($contextId !== '' && $this->isAgentMode($request)) {
            $context = $request->session()->get("agent_contexts.$contextId");

            if (is_array($context) && !empty($context['customer_id'])) {
                $contextCustomer = Customer::query()
                    ->active()
                    ->webEnabled()
                    ->where('id', (int) $context['customer_id'])
                    ->where('ditta_cg18', (int) $store->ditta_cg18)
                    ->first();

                if ($contextCustomer instanceof Customer) {
                    return $contextCustomer;
                }
            }
        }

        $authCustomer = auth('customer')->user();

        return $authCustomer instanceof Customer ? $authCustomer : null;
    }

    public function excel(
        Request $request,
        string $document,
        DocumentExcelExportService $excelExport,
        DocumentProductResolver $productResolver
    ): BinaryFileResponse {
        $documentHeader = $this->resolveDocument($request, $document);
        $productResolver->attachProducts($documentHeader, current_store());

        $path = $excelExport->build($documentHeader);
        $filename = 'documento-' . $this->safeName((string) $documentHeader->NUMREG_CO99) . '.xlsx';

        return response()
            ->download(
                $path,
                $filename,
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            )
            ->deleteFileAfterSend(true);
    }

    public function images(
        Request $request,
        string $document,
        DocumentProductImagesZipService $zipService,
        DocumentProductResolver $productResolver
    ): BinaryFileResponse {
        $documentHeader = $this->resolveDocument($request, $document);
        $productResolver->attachProducts($documentHeader, current_store());

        $path = $zipService->build($documentHeader);

        abort_if($path === null, 404);

        $filename = 'documento-' . $this->safeName((string) $documentHeader->NUMREG_CO99) . '-immagini.zip';

        return response()
            ->download($path, $filename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    private function resolveDocument(Request $request, string $document): DocumentHeader
    {
        $store = current_store();
        $customer = $this->resolveCustomer($request);

        abort_unless($customer instanceof Customer, 403);

        if ($this->isAgentMode($request) && !$request->filled('agent_context')) {
            abort(403);
        }

        $this->initErpSession();

        return DocumentHeader::query()
            ->forCustomer(
                (int) $customer->ditta_cg18,
                (int) $customer->clifor_cg44
            )
            ->visibleDocumentTypes()
            ->where('DOCTESTATABASE_DO11.DITTA_CG18', (int) $store->ditta_cg18)
            ->where('DOCTESTATABASE_DO11.NUMREG_CO99', $document)
            ->with('rows')
            ->firstOrFail();
    }

    private function safeName(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?: 'documento';
        $value = trim($value, '-_.');

        return $value !== '' ? $value : 'documento';
    }
}
