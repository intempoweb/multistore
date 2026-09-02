<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NewsletterStoreRequest;
use App\Http\Requests\Admin\NewsletterUpdateRequest;
use App\Models\Newsletter;
use App\Models\Product;
use App\Models\Store;
use App\Services\Newsletters\NewsletterProductPresenter;
use App\Services\Newsletters\NewsletterProductSelector;
use App\Services\Newsletters\NewsletterRenderer;
use App\Services\Newsletters\NewsletterSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class NewsletterController extends Controller
{
    public function index(Request $request): View
    {
        $store = $this->resolveAdminStore();

        $query = Newsletter::query()
            ->withCount('products')
            ->forStore($store)
            ->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('subject', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('provider')) {
            $query->where('provider', (string) $request->input('provider'));
        }

        $newsletters = $query->paginate(25)->withQueryString();

        return view('admin.newsletters.index', [
            'store' => $store,
            'newsletters' => $newsletters,
            'providerLabels' => Newsletter::PROVIDER_LABELS,
        ]);
    }

    public function create(): View
    {
        $store = $this->resolveAdminStore();
        $context = $this->contextConfig($store);

        return view('admin.newsletters.create', [
            'store' => $store,
            'newsletter' => new Newsletter([
                'provider' => (string) ($context['provider'] ?? config('newsletters.default_provider', Newsletter::PROVIDER_MAILCHIMP)),
                'selection_type' => Newsletter::SELECTION_PROMOTIONS,
                'locale' => $store->defaultLocale('it'),
                'listino_id' => $context['listino_id'] ?? null,
                'price_mode' => config('newsletters.default_price_mode', 'listino'),
            ]),
            'selectionLabels' => Newsletter::SELECTION_LABELS,
            'providerLabels' => Newsletter::PROVIDER_LABELS,
            'locales' => $store->supportedLocales('it'),
            'manualSkus' => '',
        ]);
    }

    public function store(NewsletterStoreRequest $request, NewsletterProductSelector $selector): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $data = $request->validated();

        $newsletter = DB::transaction(function () use ($data, $store, $selector) {
            $newsletter = Newsletter::query()->create($this->payload($data, $store));
            $products = $this->productsForPayload($newsletter, $data, $selector);
            $selector->syncProducts($newsletter, $products);

            return $newsletter;
        });

        return redirect()
            ->route('admin.newsletters.edit', $newsletter)
            ->with('success', 'Newsletter creata correttamente.');
    }

    public function productsPreview(
        Request $request,
        NewsletterProductSelector $selector,
        NewsletterProductPresenter $presenter
    ): JsonResponse {
        $store = $this->resolveAdminStore();
        $newsletter = $this->previewNewsletter($request, $store);
        $manualSkus = $selector->manualSkusFromText($request->input('manual_skus'));
        $products = $selector->select($newsletter, $manualSkus ?: null);
        $manualCandidates = $manualSkus !== []
            ? Product::query()
                ->whereIn('sku', $manualSkus)
                ->get()
                ->groupBy(fn (Product $product) => (string) $product->sku)
            : collect();

        $rows = $this->productPreviewRows($newsletter, $products, $manualSkus, $manualCandidates, $presenter, $store);

        return response()->json([
            'html' => view('admin.newsletters._product_preview_table', [
                'rows' => $rows,
                'hasManualSkus' => $manualSkus !== [],
                'productsCount' => $products->count(),
            ])->render(),
            'products_count' => $products->count(),
        ]);
    }

    public function edit(Newsletter $newsletter): View
    {
        $store = $this->resolveAdminStore();
        $this->guardNewsletterContext($newsletter, $store);
        $newsletter->load(['products.translations', 'products.mediaAssets']);

        return view('admin.newsletters.edit', [
            'store' => $store,
            'newsletter' => $newsletter,
            'selectionLabels' => Newsletter::SELECTION_LABELS,
            'providerLabels' => Newsletter::PROVIDER_LABELS,
            'locales' => $store->supportedLocales('it'),
            'manualSkus' => $newsletter->products->pluck('sku')->implode("\n"),
        ]);
    }

    public function update(
        NewsletterUpdateRequest $request,
        Newsletter $newsletter,
        NewsletterProductSelector $selector
    ): RedirectResponse {
        $store = $this->resolveAdminStore();
        $this->guardNewsletterContext($newsletter, $store);
        $data = $request->validated();

        DB::transaction(function () use ($newsletter, $data, $store, $selector) {
            $newsletter->update($this->payload($data, $store, false));
            $products = $this->productsForPayload($newsletter->fresh('store'), $data, $selector);
            $selector->syncProducts($newsletter, $products);
        });

        return redirect()
            ->route('admin.newsletters.edit', $newsletter)
            ->with('success', 'Newsletter aggiornata correttamente.');
    }

    public function preview(Newsletter $newsletter, NewsletterRenderer $renderer): View
    {
        $store = $this->resolveAdminStore();
        $this->guardNewsletterContext($newsletter, $store);

        return view('admin.newsletters.preview', [
            'newsletter' => $newsletter,
            'html' => $renderer->render($newsletter),
        ]);
    }

    public function sync(Newsletter $newsletter, NewsletterSyncService $syncService): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $this->guardNewsletterContext($newsletter, $store);

        try {
            $syncService->syncDraft($newsletter);
        } catch (RuntimeException $exception) {
            $newsletter->forceFill(['status' => Newsletter::STATUS_ERROR])->save();

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Bozza sincronizzata con ' . $newsletter->providerLabel() . '.');
    }

    public function duplicate(Newsletter $newsletter, NewsletterProductSelector $selector): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $this->guardNewsletterContext($newsletter, $store);

        $copy = DB::transaction(function () use ($newsletter, $selector) {
            $copy = $newsletter->replicate([
                'status',
                'rendered_html',
                'provider_campaign_id',
                'provider_web_id',
                'provider_edit_url',
                'provider_synced_at',
            ]);
            $copy->name = $newsletter->name . ' - copia';
            $copy->status = Newsletter::STATUS_DRAFT;
            $copy->created_by = auth()->id();
            $copy->save();

            $selector->syncProducts($copy, $newsletter->products()->get());

            return $copy;
        });

        return redirect()
            ->route('admin.newsletters.edit', $copy)
            ->with('success', 'Newsletter duplicata correttamente.');
    }

    public function destroy(Newsletter $newsletter): RedirectResponse
    {
        $store = $this->resolveAdminStore();
        $this->guardNewsletterContext($newsletter, $store);
        $newsletter->delete();

        return redirect()
            ->route('admin.newsletters.index')
            ->with('success', 'Newsletter eliminata correttamente.');
    }

    private function payload(array $data, Store $store, bool $includeCreator = true): array
    {
        $payload = [
            'store_id' => $store->id,
            'ditta_cg18' => (int) $store->ditta_cg18,
            'site_type' => (int) $store->erp_site_code,
            'name' => (string) $data['name'],
            'subject' => (string) $data['subject'],
            'preview_text' => $this->nullableString($data['preview_text'] ?? null),
            'locale' => (string) $data['locale'],
            'selection_type' => (string) $data['selection_type'],
            'provider' => (string) $data['provider'],
            'listino_id' => $data['listino_id'] ?? null,
            'price_mode' => (string) config('newsletters.default_price_mode', 'listino'),
            'hero_title' => $this->nullableString($data['hero_title'] ?? null),
            'hero_text' => $this->nullableString($data['hero_text'] ?? null),
            'hero_image_url' => $this->nullableString($data['hero_image_url'] ?? null),
            'intro_html' => $this->nullableString($data['intro_html'] ?? null),
            'filters' => $data['filters'] ?? [],
            'settings' => [
                'selection_types' => $data['selection_types'] ?? [],
            ],
        ];

        if ($includeCreator) {
            $payload['created_by'] = auth()->id();
        }

        return $payload;
    }

    private function productsForPayload(Newsletter $newsletter, array $data, NewsletterProductSelector $selector)
    {
        $manualSkus = $selector->manualSkusFromText($data['manual_skus'] ?? null);

        return $selector->select($newsletter, $manualSkus ?: null);
    }

    private function previewNewsletter(Request $request, Store $store): Newsletter
    {
        $context = $this->contextConfig($store);
        $newsletter = new Newsletter([
            'store_id' => $store->id,
            'ditta_cg18' => (int) $store->ditta_cg18,
            'site_type' => (int) $store->erp_site_code,
            'locale' => (string) $request->input('locale', $store->defaultLocale('it')),
            'selection_type' => (string) $request->input('selection_type', Newsletter::SELECTION_PROMOTIONS),
            'provider' => (string) $request->input('provider', $context['provider'] ?? config('newsletters.default_provider', Newsletter::PROVIDER_MAILCHIMP)),
            'listino_id' => $this->nullableInteger($request->input('listino_id', $context['listino_id'] ?? null)),
            'price_mode' => (string) config('newsletters.default_price_mode', 'listino'),
            'filters' => (array) $request->input('filters', []),
            'settings' => [
                'selection_types' => (array) $request->input('selection_types', []),
            ],
        ]);

        $newsletter->setRelation('store', $store);

        return $newsletter;
    }

    private function productPreviewRows(
        Newsletter $newsletter,
        $products,
        array $manualSkus,
        $manualCandidates,
        NewsletterProductPresenter $presenter,
        Store $store
    ) {
        $productRows = $products
            ->mapWithKeys(function (Product $product) use ($newsletter, $presenter) {
                return [(string) $product->sku => $this->foundProductPreviewRow($newsletter, $product, $presenter)];
            });

        if ($manualSkus === []) {
            return $productRows->values();
        }

        return collect($manualSkus)
            ->map(function (string $sku) use ($productRows, $manualCandidates, $store) {
                if ($productRows->has($sku)) {
                    return $productRows->get($sku);
                }

                return [
                    'status' => 'missing',
                    'sku' => $sku,
                    'name' => null,
                    'type' => null,
                    'parent_code' => null,
                    'price_label' => null,
                    'price_source' => null,
                    'admin_url' => null,
                    'image_url' => null,
                    'message' => $this->missingSkuMessage($sku, $manualCandidates->get($sku, collect()), $store),
                ];
            })
            ->values();
    }

    private function foundProductPreviewRow(Newsletter $newsletter, Product $product, NewsletterProductPresenter $presenter): array
    {
        $data = $presenter->present($newsletter, $product);

        return [
            'status' => 'ok',
            'sku' => $data['sku'],
            'name' => $data['name'],
            'type' => $data['type'],
            'parent_code' => $data['parent_code'],
            'price_label' => $data['price_label'],
            'price_source' => $data['price_source'],
            'admin_url' => route('admin.products.show', $product),
            'image_url' => $data['image_url'],
            'message' => null,
        ];
    }

    private function missingSkuMessage(string $sku, $candidates, Store $store): string
    {
        $sameContext = $candidates
            ->filter(fn (Product $product) => (int) $product->ditta_cg18 === (int) $store->ditta_cg18
                && (int) $product->site_type === (int) $store->erp_site_code);

        if ($sameContext->contains(fn (Product $product) => (string) $product->type !== 'simple')) {
            return 'Trovato, ma e un prodotto padre/configurabile: inserisci lo SKU figlio.';
        }

        if ($sameContext->contains(fn (Product $product) => ! $product->is_active)) {
            return 'Trovato nel contesto corrente, ma non risulta attivo.';
        }

        if ($candidates->isNotEmpty()) {
            return 'SKU presente in un altro contesto store/ditta.';
        }

        return 'SKU non trovato nel catalogo prodotti.';
    }

    private function resolveAdminStore(): Store
    {
        $store = app('adminStore');

        if (! $store instanceof Store) {
            throw new InvalidArgumentException('Store admin non risolto.');
        }

        return $store;
    }

    private function guardNewsletterContext(Newsletter $newsletter, Store $store): void
    {
        abort_if((int) $newsletter->store_id !== (int) $store->id, 404);
    }

    private function contextConfig(Store $store): array
    {
        return (array) config('newsletters.contexts.' . ((int) $store->ditta_cg18) . ':' . ((int) $store->erp_site_code), []);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
