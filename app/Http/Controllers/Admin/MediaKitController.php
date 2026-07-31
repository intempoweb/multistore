<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendMediaKitEmail;
use App\Models\Customer;
use App\Models\Erp\DocumentHeader;
use App\Models\Product;
use App\Repositories\Storefront\SearchRepository;
use App\Models\MediaKitRequest;
use App\Models\Order;
use App\Services\MediaKit\MediaKitContext;
use App\Services\MediaKit\MediaKitDownloadService;
use App\Services\MediaKit\MediaKitRequestService;
use App\Services\MediaKit\MediaKitSelectionManager;
use App\Services\Visibility\ProductVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class MediaKitController extends Controller
{
    public function index(): View
    {
        $store = admin_store();

        abort_unless($store, Response::HTTP_UNPROCESSABLE_ENTITY, 'Store BO non selezionato.');

        $requests = MediaKitRequest::query()
            ->where('store_id', $store->getKey())
            ->latest()
            ->paginate(20);

        return view('admin.mediakit.index', compact('store', 'requests'));
    }

    public function create(): View
    {
        $store = admin_store();

        abort_unless($store, Response::HTTP_UNPROCESSABLE_ENTITY, 'Store BO non selezionato.');

        return view('admin.mediakit.create', compact('store'));
    }

    public function products(
        Request $request,
        SearchRepository $searchRepository,
        ProductVisibilityService $visibility
    ): JsonResponse {
        $store = admin_store();

        abort_unless($store, Response::HTTP_UNPROCESSABLE_ENTITY, 'Store BO non selezionato.');

        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'search' => ['required', 'string', 'min:2', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $customer = Customer::query()
            ->whereKey((int) $data['customer_id'])
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->firstOrFail();

        $requestedPage = max(1, (int) ($data['page'] ?? 1));
        $originalPage = $request->query('page');
        $request->query->set('page', 1);

        try {
            /*
             * Lo storefront cerca principalmente i configurabili. Per MediaKit
             * usiamo gli stessi risultati di ricerca, ma poi li espandiamo nei
             * prodotti simple realmente dotati di SKU e media.
             */
            $searchResults = $searchRepository->search(
                store: $store,
                locale: app()->getLocale(),
                query: trim((string) $data['search']),
                tipocf: (int) $customer->tipocf_cg44,
                clifor: (int) $customer->clifor_cg44,
                perPage: 500,
                sort: 'default',
                filters: [],
            );
        } finally {
            if ($originalPage === null) {
                $request->query->remove('page');
            } else {
                $request->query->set('page', $originalPage);
            }
        }

        $matchedProducts = collect($searchResults->items());

        $configurableSkus = $matchedProducts
            ->filter(fn (Product $product) => $product->type === 'configurable')
            ->pluck('sku')
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values();

        $directSimpleIds = $matchedProducts
            ->filter(fn (Product $product) => $product->type === 'simple')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $parentNames = $matchedProducts
            ->filter(fn (Product $product) => $product->type === 'configurable')
            ->mapWithKeys(function (Product $product): array {
                $translation = method_exists($product, 'translationOrFallback')
                    ? $product->translationOrFallback(app()->getLocale())
                    : null;

                return [
                    (string) $product->sku => (string) (
                        $translation?->name
                        ?? $product->display_name
                        ?? $product->name
                        ?? $product->sku
                    ),
                ];
            });

        if ($configurableSkus->isEmpty() && $directSimpleIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
            ]);
        }

        $variants = $visibility
            ->visibleProductsQuery(
                (int) $store->ditta_cg18,
                (int) $store->erp_site_code,
                (int) $customer->tipocf_cg44,
                (int) $customer->clifor_cg44,
            )
            ->where('p.type', 'simple')
            ->where('p.is_active', 1)
            ->where(function ($where) use ($configurableSkus, $directSimpleIds): void {
                if ($configurableSkus->isNotEmpty()) {
                    $where->whereIn('p.parent_code', $configurableSkus->all());
                }

                if ($directSimpleIds->isNotEmpty()) {
                    $method = $configurableSkus->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $where->{$method}('p.id', $directSimpleIds->all());
                }
            })
            ->with([
                'translations',
                'mediaAssets' => fn ($media) => $media
                    ->whereNotNull('local_path')
                    ->orderByRaw("CASE WHEN role = 'main' THEN 0 ELSE 1 END")
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->orderBy('p.parent_code')
            ->orderBy('p.sku')
            ->get()
            ->unique('id')
            ->values();

        $perPage = 12;
        $total = $variants->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($requestedPage, $lastPage);
        $pageItems = $variants->slice(($page - 1) * $perPage, $perPage)->values();

        $products = $pageItems
            ->map(function (Product $product) use ($parentNames): array {
                $assets = $product->mediaAssets;
                $asset = $assets->first();
                $translation = method_exists($product, 'translationOrFallback')
                    ? $product->translationOrFallback(app()->getLocale())
                    : null;

                $variantName = (string) (
                    $translation?->name
                    ?? $product->display_name
                    ?? $product->name
                    ?? $product->sku
                );

                return [
                    'id' => (int) $product->getKey(),
                    'sku' => (string) $product->sku,
                    'parent_sku' => (string) ($product->parent_code ?? ''),
                    'parent_name' => (string) ($parentNames[(string) $product->parent_code] ?? ''),
                    'name' => $variantName,
                    'media_count' => $assets->count(),
                    'image_url' => $product->main_image_url
                        ?? $asset?->url
                        ?? ($asset && function_exists('media_url')
                            ? media_url($asset->local_path)
                            : ($asset?->local_path)),
                ];
            })
            ->values();

        return response()->json([
            'data' => $products,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total ? (($page - 1) * $perPage) + 1 : null,
                'to' => $total ? min($page * $perPage, $total) : null,
            ],
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $store = admin_store();

        abort_unless($store, Response::HTTP_UNPROCESSABLE_ENTITY, 'Store BO non selezionato.');

        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $customer = Customer::query()
            ->whereKey((int) $data['customer_id'])
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->firstOrFail();

        try {
            /*
             * Alyante usa tabelle/vista eterogenee: SQL Server richiede queste
             * opzioni sulla stessa sessione prima di eseguire le SELECT.
             */
            $erp = DB::connection('erp');
            $erp->statement('SET ANSI_NULLS ON');
            $erp->statement('SET ANSI_WARNINGS ON');

            $query = DocumentHeader::query()
                ->forCustomer(
                    (int) $store->ditta_cg18,
                    (int) $customer->clifor_cg44
                )
                ->storeLocatorDocuments()
                ->select([
                    'DOCTESTATABASE_DO11.NUMREG_CO99',
                    'DOCTESTATABASE_DO11.DITTA_CG18',
                    'DOCTESTATABASE_DO11.CLIFOR_CG44',
                    'DOCTESTATABASE_DO11.DATADOC_DO11',
                    'DOCTESTATABASE_DO11.NUMSEZDOC_DO11',
                    'DOCTESTATABASE_DO11.TIPODOCDECOD_MG36',
                ])
                ->orderByDocumentDate('desc')
                ->orderByDocumentNumber('desc');

            $search = trim((string) ($data['search'] ?? ''));

            if ($search !== '') {
                $query->where(function ($where) use ($search): void {
                    $where
                        ->where(
                            'DOCTESTATABASE_DO11.NUMREG_CO99',
                            $search
                        )
                        ->orWhere(
                            'DOCTESTATABASE_DO11.NUMSEZDOC_DO11',
                            'like',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'DOCTESTATABASE_DO11.TIPODOCDECOD_MG36',
                            'like',
                            '%' . $search . '%'
                        );
                });
            }

            $documents = $query
                ->limit(50)
                ->get()
                ->map(fn (DocumentHeader $document) => [
                    'id' => (string) $document->NUMREG_CO99,
                    'number' => $document->documentNumberForDisplay(),
                    'type' => $document->documentTypeForDisplay(),
                    'date' => $this->formatErpDocumentDate($document->DATADOC_DO11),
                ])
                ->values();

            return response()->json(['data' => $documents]);
        } catch (QueryException $e) {
            report($e);

            return response()->json([
                'message' => 'Impossibile leggere i documenti ERP. Verifica che il tunnel SQL sia attivo e riprova.',
                'data' => [],
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Errore durante il recupero dei documenti ERP.',
                'data' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function orders(Request $request): JsonResponse
    {
        $store = admin_store();

        abort_unless($store, Response::HTTP_UNPROCESSABLE_ENTITY, 'Store BO non selezionato.');

        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Order::query()
            ->where('store_id', $store->getKey())
            ->where('customer_id', (int) $data['customer_id'])
            ->latest('placed_at')
            ->latest('id');

        $search = trim((string) ($data['search'] ?? ''));

        if ($search !== '') {
            $query->where(function ($where) use ($search): void {
                $where->where('id', $search)
                    ->orWhere('order_number', 'like', "%{$search}%")
                    ->orWhere('legacy_magento_order_number', 'like', "%{$search}%")
                    ->orWhere('erp_document_number', 'like', "%{$search}%")
                    ->orWhere('erp_document_visible_number', 'like', "%{$search}%");
            });
        }

        $orders = $query
            ->limit(30)
            ->get([
                'id',
                'order_number',
                'legacy_magento_order_number',
                'erp_document_number',
                'erp_document_visible_number',
                'status',
                'grand_total',
                'currency',
                'placed_at',
            ])
            ->map(fn (Order $order) => [
                'id' => (int) $order->getKey(),
                'number' => (string) (
                    $order->order_number
                    ?: $order->erp_document_visible_number
                    ?: $order->erp_document_number
                    ?: $order->legacy_magento_order_number
                    ?: $order->getKey()
                ),
                'status' => (string) $order->status,
                'total' => $order->grand_total,
                'currency' => (string) ($order->currency ?: 'EUR'),
                'placed_at' => optional($order->placed_at)->format('d/m/Y H:i'),
            ]);

        return response()->json(['data' => $orders]);
    }

    public function preview(
        Request $httpRequest,
        MediaKitSelectionManager $selectionManager
    ): JsonResponse {
        $data = $this->validatedInput($httpRequest);
        [$store, $customer, $context] = $this->resolveContext($httpRequest, $data);

        $sourceType = (string) $data['source_type'];
        $sourceReference = trim((string) ($data['source_reference'] ?? ''));
        $skus = $this->parseSkus((string) ($data['skus_text'] ?? ''));
        $productIds = collect($data['product_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        $this->validateSourceInput($httpRequest, $sourceType, $sourceReference, $skus, $productIds);

        $temporaryInput = null;
        $requestModel = new MediaKitRequest([
            'uuid' => (string) Str::uuid(),
            'store_id' => $store->getKey(),
            'customer_id' => $customer->getKey(),
            'source_type' => $sourceType,
            'source_reference' => $sourceReference !== '' ? $sourceReference : null,
            'status' => MediaKitRequest::STATUS_QUEUED,
            'progress' => 0,
            'meta' => [
                'skus' => $skus,
                'product_ids' => $productIds,
                'customer' => $this->customerMeta($customer),
                'context' => [
                    'tipo_cf' => $context->tipoCf,
                    'clifor' => $context->clifor,
                    'apply_customer_acl' => true,
                ],
            ],
        ]);

        try {
            if ($sourceType === MediaKitRequest::SOURCE_UPLOADED_DDT) {
                $temporaryInput = $this->storeUploadedDdt($httpRequest, 'previews');
                $requestModel->input_disk = $temporaryInput['disk'];
                $requestModel->input_path = $temporaryInput['path'];
                $requestModel->source_reference = $temporaryInput['name'];

                // Il resolver DDT aggiorna meta tramite save(): per l'anteprima usiamo
                // una richiesta temporanea reale, eliminata subito dopo.
                $requestModel->save();
            }

            $selection = $selectionManager->resolve($requestModel, $context);

            return response()->json([
                'data' => [
                    'count' => $selection->products->count(),
                    'source_type' => $selection->sourceType,
                    'source_reference' => $selection->sourceReference,
                    'warnings' => $selection->warnings,
                    'products' => $selection->products
                        ->take(250)
                        ->map(function ($product): array {
                            $assets = method_exists($product, 'relationLoaded')
                                && $product->relationLoaded('mediaAssets')
                                ? $product->mediaAssets
                                : collect();

                            $images = $assets
                                ->filter(fn ($asset) => filled($asset->local_path))
                                ->take(6)
                                ->map(fn ($asset) => [
                                    'id' => (int) $asset->getKey(),
                                    'role' => (string) $asset->role,
                                    'filename' => (string) ($asset->filename ?: basename((string) $asset->local_path)),
                                    'url' => function_exists('media_url')
                                        ? media_url($asset->local_path)
                                        : $asset->local_path,
                                ])
                                ->values();

                            return [
                                'id' => (int) $product->getKey(),
                                'sku' => (string) $product->sku,
                                'name' => (string) (
                                    $product->name
                                    ?? $product->title
                                    ?? $product->description
                                    ?? $product->sku
                                ),
                                'media_count' => $assets->count(),
                                'images' => $images,
                            ];
                        })
                        ->values(),
                ],
            ]);
        } finally {
            if ($requestModel->exists) {
                $requestModel->forceDelete();
            }

            if ($temporaryInput) {
                Storage::disk($temporaryInput['disk'])->delete($temporaryInput['path']);
            }
        }
    }

    public function store(Request $httpRequest, MediaKitRequestService $service): JsonResponse
    {
        $data = $this->validatedInput($httpRequest);
        [, $customer, $context] = $this->resolveContext($httpRequest, $data);

        $sourceType = (string) $data['source_type'];
        $sourceReference = trim((string) ($data['source_reference'] ?? ''));
        $skus = $this->parseSkus((string) ($data['skus_text'] ?? ''));
        $productIds = collect($data['product_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        $this->validateSourceInput($httpRequest, $sourceType, $sourceReference, $skus, $productIds);

        $inputDisk = null;
        $inputPath = null;

        if ($sourceType === MediaKitRequest::SOURCE_UPLOADED_DDT) {
            $uploaded = $this->storeUploadedDdt($httpRequest, 'inputs');
            $inputDisk = $uploaded['disk'];
            $inputPath = $uploaded['path'];
            $sourceReference = $uploaded['name'];
        }

        try {
            $request = $service->create($context, [
                'source_type' => $sourceType,
                'source_reference' => $sourceReference !== '' ? $sourceReference : null,
                'input_disk' => $inputDisk,
                'input_path' => $inputPath,
                'meta' => [
                    'skus' => $skus,
                    'product_ids' => $productIds,
                    'customer' => $this->customerMeta($customer),
                ],
            ]);
        } catch (Throwable $e) {
            if ($inputDisk && $inputPath) {
                Storage::disk($inputDisk)->delete($inputPath);
            }

            throw $e;
        }

        return response()->json([
            'message' => 'Richiesta MediaKit creata.',
            'data' => $request,
            'redirect_url' => route('admin.mediakit.index'),
        ], Response::HTTP_ACCEPTED);
    }

    public function show(MediaKitRequest $mediaKitRequest): JsonResponse
    {
        $store = admin_store();

        abort_unless(
            $store && (int) $mediaKitRequest->store_id === (int) $store->getKey(),
            Response::HTTP_NOT_FOUND
        );

        return response()->json($mediaKitRequest->fresh());
    }

    public function download(
        MediaKitRequest $mediaKitRequest,
        MediaKitDownloadService $download
    ): RedirectResponse {
        $store = admin_store();

        abort_unless(
            $store && (int) $mediaKitRequest->store_id === (int) $store->getKey(),
            Response::HTTP_NOT_FOUND
        );

        try {
            return redirect()->away($download->temporaryUrl($mediaKitRequest));
        } catch (Throwable $e) {
            abort(Response::HTTP_GONE, $e->getMessage());
        }
    }

    public function sendEmail(Request $httpRequest, MediaKitRequest $mediaKitRequest): JsonResponse
    {
        $store = admin_store();

        abort_unless(
            $store && (int) $mediaKitRequest->store_id === (int) $store->getKey(),
            Response::HTTP_NOT_FOUND
        );

        if (!$mediaKitRequest->isDownloadable()) {
            return response()->json([
                'message' => 'Archivio MediaKit non disponibile o scaduto.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $httpRequest->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $recipient = mb_strtolower(trim((string) $data['email']));

        $mediaKitRequest->forceFill([
            'email_to' => $recipient,
            'email_status' => MediaKitRequest::EMAIL_QUEUED,
            'email_error_message' => null,
        ])->save();

        SendMediaKitEmail::dispatch($mediaKitRequest->getKey(), $recipient)
            ->onQueue((string) config('mediakit.queue', 'default'));

        return response()->json([
            'message' => 'Invio MediaKit accodato.',
            'data' => $mediaKitRequest->fresh(),
        ], Response::HTTP_ACCEPTED);
    }

    private function validatedInput(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer'],
            'source_type' => ['required', 'in:catalog,uploaded_ddt,document,order'],
            'source_reference' => ['nullable', 'string', 'max:191'],
            'skus_text' => ['nullable', 'string', 'max:50000'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
            'ddt_file' => ['nullable', 'file', 'mimes:xls,xlsx,csv,txt', 'max:20480'],
        ]);
    }

    private function resolveContext(Request $request, array $data): array
    {
        $store = admin_store();

        abort_unless($store, Response::HTTP_UNPROCESSABLE_ENTITY, 'Store BO non selezionato.');

        $customer = Customer::query()
            ->whereKey((int) $data['customer_id'])
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->first();

        if (!$customer) {
            throw ValidationException::withMessages([
                'customer_id' => 'Il cliente selezionato non appartiene allo store corrente.',
            ]);
        }

        $actor = $request->user();

        $context = new MediaKitContext(
            store: $store,
            customerId: (int) $customer->getKey(),
            actorType: $actor ? $actor::class : null,
            actorId: $actor?->getAuthIdentifier() ? (int) $actor->getAuthIdentifier() : null,
            tipoCf: (int) $customer->tipocf_cg44,
            clifor: (int) $customer->clifor_cg44,
            applyCustomerAcl: true,
        );

        return [$store, $customer, $context];
    }

    private function validateSourceInput(
        Request $request,
        string $sourceType,
        string $sourceReference,
        array $skus,
        array $productIds
    ): void {
        if ($sourceType === MediaKitRequest::SOURCE_CATALOG && $skus === [] && $productIds === []) {
            throw ValidationException::withMessages(['skus_text' => 'Inserisci almeno uno SKU.']);
        }

        if (in_array($sourceType, [
            MediaKitRequest::SOURCE_DOCUMENT,
            MediaKitRequest::SOURCE_ORDER,
        ], true) && $sourceReference === '') {
            throw ValidationException::withMessages([
                'source_reference' => 'Inserisci il riferimento richiesto.',
            ]);
        }

        if ($sourceType === MediaKitRequest::SOURCE_UPLOADED_DDT && !$request->hasFile('ddt_file')) {
            throw ValidationException::withMessages(['ddt_file' => 'Seleziona il file DDT.']);
        }
    }

    private function storeUploadedDdt(Request $request, string $folder): array
    {
        $file = $request->file('ddt_file');
        $disk = 'local';
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $path = "mediakit/{$folder}/" . Str::uuid() . '.' . $extension;

        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

        return [
            'disk' => $disk,
            'path' => $path,
            'name' => $file->getClientOriginalName(),
        ];
    }

    private function customerMeta(Customer $customer): array
    {
        return [
            'id' => (int) $customer->getKey(),
            'ragione_sociale' => (string) ($customer->ragsoanag_cg16 ?: $customer->ragsocor_cg16),
            'tipocf' => (int) $customer->tipocf_cg44,
            'clifor' => (int) $customer->clifor_cg44,
            'email' => trim((string) ($customer->indemail_cg16 ?? '')),
        ];
    }

    private function formatErpDocumentDate(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return null;
        }

        foreach (['d/m/Y', 'd/m/Y H:i:s', 'Y-m-d', 'Y-m-d H:i:s'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw)->format('d/m/Y');
            } catch (Throwable) {
                // Prova il formato successivo.
            }
        }

        try {
            return Carbon::parse($raw)->format('d/m/Y');
        } catch (Throwable) {
            return $raw;
        }
    }

    /**
     * @return array<int, string>
     */
    private function parseSkus(string $value): array
    {
        return collect(preg_split('/[\s,;|]+/u', $value) ?: [])
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
