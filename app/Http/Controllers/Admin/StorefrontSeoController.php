<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontSeoEntry;
use App\Repositories\Storefront\CatalogRepository;
use App\Services\Storefront\Seo\StorefrontSeoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontSeoController extends Controller
{
    public function __construct(private CatalogRepository $catalogRepository)
    {
    }

    public function index(): View|RedirectResponse
    {
        $store = $this->currentAdminStore();

        if ($redirect = $this->redirectIfCannotAccessStore($store)) {
            return $redirect;
        }

        $locales = collect($store->supportedLocales())->values();
        $categoryRowsByLocale = $this->categoryRows($store, $locales->all());
        $entries = StorefrontSeoEntry::query()
            ->where('store_id', $store->id)
            ->get()
            ->keyBy(fn ($entry) => implode('|', [$entry->locale, $entry->entity_type, $entry->entity_key]));

        return view('admin.storefront-seo.index', compact('store', 'locales', 'categoryRowsByLocale', 'entries'));
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $this->currentAdminStore();

        if ($redirect = $this->redirectIfCannotAccessStore($store)) {
            return $redirect;
        }

        $validated = $request->validate([
            'entries' => ['nullable', 'array'],
            'entries.*.locale' => ['required', 'string', 'max:10'],
            'entries.*.entity_type' => ['required', 'in:catalog,collection'],
            'entries.*.entity_key' => ['required', 'string', 'max:255'],
            'entries.*.meta_title' => ['nullable', 'string', 'max:190'],
            'entries.*.meta_description' => ['nullable', 'string'],
            'entries.*.heading' => ['nullable', 'string', 'max:190'],
            'entries.*.intro' => ['nullable', 'string'],
            'entries.*.canonical_url' => ['nullable', 'url', 'max:255'],
            'entries.*.robots' => ['nullable', 'string', 'max:80'],
            'entries.*.og_title' => ['nullable', 'string', 'max:190'],
            'entries.*.og_description' => ['nullable', 'string'],
            'entries.*.og_image_path' => ['nullable', 'string', 'max:255'],
            'entries.*.og_image_file' => ['nullable', 'image', 'max:8192'],
        ]);

        foreach (($validated['entries'] ?? []) as $index => $data) {
            abort_unless($store->supportsLocale((string) $data['locale']), 422);
            $entry = StorefrontSeoEntry::query()->firstOrNew([
                'store_id' => $store->id,
                'locale' => $data['locale'],
                'entity_type' => $data['entity_type'],
                'entity_key' => $data['entity_key'],
            ]);
            $imagePath = $data['og_image_path'] ?? $entry->og_image_path;

            if ($request->hasFile("entries.{$index}.og_image_file")) {
                $imagePath = $request->file("entries.{$index}.og_image_file")->store(
                    "storefront/seo/{$store->id}",
                    env('MEDIA_SYNC_DISK', config('filesystems.default', 'public'))
                );
            }

            $entry->fill([
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'heading' => $data['heading'] ?? null,
                'intro' => $data['intro'] ?? null,
                'canonical_url' => $data['canonical_url'] ?? null,
                'robots' => $data['robots'] ?? 'index,follow',
                'og_title' => $data['og_title'] ?? null,
                'og_description' => $data['og_description'] ?? null,
                'og_image_path' => $imagePath,
                'is_active' => true,
            ])->save();
        }

        return back()->with('status', 'SEO storefront aggiornata correttamente.');
    }

    private function currentAdminStore(): Store
    {
        /** @var Store $store */
        $store = admin_store();

        return $store;
    }

    private function redirectIfCannotAccessStore(Store $store): ?RedirectResponse
    {
        $user = request()->user();

        if ($user && method_exists($user, 'canAccessAdminStore') && !$user->canAccessAdminStore($store)) {
            return redirect()
                ->route('admin.dashboard')
                ->with('warning', 'Non hai i permessi per amministrare questo store.');
        }

        return null;
    }

    private function categoryRows(Store $store, array $locales): array
    {
        $paths = Product::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('site_type', (int) $store->erp_site_code)
            ->where('is_active', true)
            ->whereNotNull('fam_99')
            ->distinct()
            ->get(['fam_99', 'sfam_99', 'gruppo_99', 'sgruppo_99'])
            ->flatMap(function (Product $product) {
                $codes = [
                    Product::normalizeErpCodeValue($product->fam_99),
                    Product::normalizeErpCodeValue($product->sfam_99),
                    Product::normalizeErpCodeValue($product->gruppo_99),
                    Product::normalizeErpCodeValue($product->sgruppo_99),
                ];
                $paths = [];

                foreach ($codes as $level => $code) {
                    if ($code === null) {
                        break;
                    }

                    $paths[] = [
                        'fam' => $codes[0],
                        'sfam' => $level >= 1 ? $codes[1] : null,
                        'gruppo' => $level >= 2 ? $codes[2] : null,
                        'sgruppo' => $level >= 3 ? $codes[3] : null,
                    ];
                }

                return $paths;
            })
            ->unique(fn (array $path) => StorefrontSeoService::categoryKey($path))
            ->values();

        return collect($locales)->mapWithKeys(function (string $locale) use ($store, $paths) {
            $rows = $paths->map(function (array $path) use ($store, $locale) {
                $category = $this->catalogRepository->getCategoryMeta(
                    $store,
                    $locale,
                    $path['fam'],
                    $path['sfam'],
                    $path['gruppo'],
                    $path['sgruppo']
                );

                return [
                    'key' => StorefrontSeoService::categoryKey($path),
                    'label' => $category['label'],
                ];
            })->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values();

            return [$locale => $rows];
        })->all();
    }
}
