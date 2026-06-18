<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GroupDescription;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminCatalogController extends Controller
{
    public function index(): View
    {
        $store = $this->currentStore();
        $locale = app()->getLocale();

        $famiglie = $this->baseSimpleProductsQuery($store)
            ->leftJoin('group_descriptions as gd_fam', function ($join) use ($locale) {
                $join->on('gd_fam.ditta_cg18', '=', 'products.ditta_cg18')
                    ->on('gd_fam.site_type', '=', 'products.site_type')
                    ->on('gd_fam.fam_code', '=', 'products.fam_99')
                    ->whereNull('gd_fam.sfam_code')
                    ->whereNull('gd_fam.gruppo_code')
                    ->where('gd_fam.locale', '=', $locale);
            })
            ->whereNotNull('products.fam_99')
            ->select([
                'products.fam_99 as code',
                DB::raw('MAX(gd_fam.description) as description'),
                DB::raw('COUNT(*) as prodotti'),
            ])
            ->groupBy('products.fam_99')
            ->orderByRaw('COALESCE(MAX(gd_fam.description), products.fam_99) asc')
            ->get()
            ->map(function ($item) {
                $item->level = 'Livello 1';
                $item->level_label = 'Categoria';
                $item->url = route('admin.catalog.show', ['fam' => $item->code]);

                return $item;
            });

        return view('admin.catalog.index', [
            'store' => $store,
            'famiglie' => $famiglie,
        ]);
    }

    public function show(
        string $fam,
        ?string $sfam = null,
        ?string $gruppo = null,
        ?string $sgruppo = null
    ): View {
        $store = $this->currentStore();
        $locale = app()->getLocale();

        $fam = Product::normalizeErpCodeValue($fam);
        $sfam = Product::normalizeErpCodeValue($sfam);
        $gruppo = Product::normalizeErpCodeValue($gruppo);
        $sgruppo = Product::normalizeErpCodeValue($sgruppo);

        abort_if($fam === null, 404);

        $famigliaDescription = $this->familyDescription($store, $locale, $fam);
        $sottofamigliaDescription = $sfam
            ? $this->subfamilyDescription($store, $locale, $fam, $sfam)
            : null;
        $groupDescription = ($sfam && $gruppo)
            ? $this->groupDescription($store, $fam, $sfam, $gruppo)
            : null;

        /** @var Collection<int, object> $items */
        $items = collect();

        /** @var LengthAwarePaginator<int, Product>|null $products */
        $products = null;

        $level = 'famiglia';
        $title = $famigliaDescription ?: $fam;
        $backUrl = route('admin.catalog.index');
        $backLabel = 'Torna alle categorie';
        $sectionTitle = 'Sottocategorie';

        if ($sfam === null) {
            $items = $this->baseSimpleProductsQuery($store)
                ->leftJoin('group_descriptions as gd_sfam', function ($join) use ($locale) {
                    $join->on('gd_sfam.ditta_cg18', '=', 'products.ditta_cg18')
                        ->on('gd_sfam.site_type', '=', 'products.site_type')
                        ->on('gd_sfam.fam_code', '=', 'products.fam_99')
                        ->on('gd_sfam.sfam_code', '=', 'products.sfam_99')
                        ->whereNull('gd_sfam.gruppo_code')
                        ->where('gd_sfam.locale', '=', $locale);
                })
                ->where('products.fam_99', '=', $fam)
                ->whereNotNull('products.sfam_99')
                ->select([
                    'products.sfam_99 as code',
                    DB::raw('MAX(gd_sfam.description) as description'),
                    DB::raw('COUNT(*) as prodotti'),
                ])
                ->groupBy('products.sfam_99')
                ->orderByRaw('COALESCE(MAX(gd_sfam.description), products.sfam_99) asc')
                ->get()
                ->map(function ($item) use ($fam) {
                    $item->level = 'Livello 2';
                    $item->level_label = 'Sottofamiglia';
                    $item->url = route('admin.catalog.show', [
                        'fam' => $fam,
                        'sfam' => $item->code,
                    ]);

                    return $item;
                });

            $products = $this->baseSimpleProductsQuery($store)
                ->with(['translations', 'mediaAssets'])
                ->where('products.fam_99', '=', $fam)
                ->whereNull('products.sfam_99')
                ->orderBy('products.sku')
                ->paginate(50, ['*'], 'products_page')
                ->withQueryString();
        } elseif ($gruppo === null) {
            $level = 'sottofamiglia';
            $title = $sottofamigliaDescription ?: $sfam;
            $backUrl = route('admin.catalog.show', ['fam' => $fam]);
            $backLabel = 'Torna al livello precedente';

            $items = $this->baseSimpleProductsQuery($store)
                ->leftJoin('group_descriptions as gd_grp', function ($join) use ($locale) {
                    $join->on('gd_grp.ditta_cg18', '=', 'products.ditta_cg18')
                        ->on('gd_grp.site_type', '=', 'products.site_type')
                        ->on('gd_grp.fam_code', '=', 'products.fam_99')
                        ->on('gd_grp.sfam_code', '=', 'products.sfam_99')
                        ->on('gd_grp.gruppo_code', '=', 'products.gruppo_99')
                        ->where('gd_grp.locale', '=', $locale);
                })
                ->where('products.fam_99', '=', $fam)
                ->where('products.sfam_99', '=', $sfam)
                ->whereNotNull('products.gruppo_99')
                ->select([
                    'products.gruppo_99 as code',
                    DB::raw('MAX(gd_grp.description) as description'),
                    DB::raw('COUNT(*) as prodotti'),
                ])
                ->groupBy('products.gruppo_99')
                ->orderByRaw('COALESCE(MAX(gd_grp.description), products.gruppo_99) asc')
                ->get()
                ->map(function ($item) use ($fam, $sfam) {
                    $item->level = 'Livello 3';
                    $item->level_label = 'Gruppo';
                    $item->url = route('admin.catalog.show', [
                        'fam' => $fam,
                        'sfam' => $sfam,
                        'gruppo' => $item->code,
                    ]);

                    return $item;
                });

            $products = $this->baseSimpleProductsQuery($store)
                ->with(['translations', 'mediaAssets'])
                ->where('products.fam_99', '=', $fam)
                ->where('products.sfam_99', '=', $sfam)
                ->whereNull('products.gruppo_99')
                ->orderBy('products.sku')
                ->paginate(50, ['*'], 'products_page')
                ->withQueryString();
        } elseif ($sgruppo === null) {
            $level = 'gruppo';
            $title = $groupDescription?->description ?: $gruppo;
            $backUrl = route('admin.catalog.show', [
                'fam' => $fam,
                'sfam' => $sfam,
            ]);
            $backLabel = 'Torna al livello precedente';

            $items = $this->baseSimpleProductsQuery($store)
                ->where('products.fam_99', '=', $fam)
                ->where('products.sfam_99', '=', $sfam)
                ->where('products.gruppo_99', '=', $gruppo)
                ->whereNotNull('products.sgruppo_99')
                ->select([
                    'products.sgruppo_99 as code',
                    DB::raw('COUNT(*) as prodotti'),
                ])
                ->groupBy('products.sgruppo_99')
                ->orderBy('products.sgruppo_99')
                ->get()
                ->map(function ($item) {
                    $item->description = $item->code;
                    $item->level = 'Livello 4';
                    $item->level_label = 'Sottogruppo';

                    return $item;
                });

            $items = $items->map(function ($item) use ($fam, $sfam, $gruppo) {
                $item->url = route('admin.catalog.show', [
                    'fam' => $fam,
                    'sfam' => $sfam,
                    'gruppo' => $gruppo,
                    'sgruppo' => $item->code,
                ]);

                return $item;
            });

            $products = $this->baseSimpleProductsQuery($store)
                ->with(['translations', 'mediaAssets'])
                ->where('products.fam_99', '=', $fam)
                ->where('products.sfam_99', '=', $sfam)
                ->where('products.gruppo_99', '=', $gruppo)
                ->whereNull('products.sgruppo_99')
                ->orderBy('products.sku')
                ->paginate(50, ['*'], 'products_page')
                ->withQueryString();
        } else {
            $level = 'prodotti';
            $title = $sgruppo;
            $backUrl = route('admin.catalog.show', [
                'fam' => $fam,
                'sfam' => $sfam,
                'gruppo' => $gruppo,
            ]);
            $backLabel = 'Torna al livello precedente';
            $sectionTitle = 'Prodotti';

            $products = $this->baseSimpleProductsQuery($store)
                ->with(['translations', 'mediaAssets'])
                ->where('products.fam_99', '=', $fam)
                ->where('products.sfam_99', '=', $sfam)
                ->where('products.gruppo_99', '=', $gruppo)
                ->where('products.sgruppo_99', '=', $sgruppo)
                ->orderBy('products.sku')
                ->paginate(50, ['*'], 'products_page')
                ->withQueryString();
        }

        return view('admin.catalog.show', [
            'store' => $store,
            'level' => $level,
            'title' => $title,
            'currentLabel' => $title,
            'sectionTitle' => $sectionTitle,
            'fam' => $fam,
            'sfam' => $sfam,
            'gruppo' => $gruppo,
            'sgruppo' => $sgruppo,
            'famigliaDescription' => $famigliaDescription,
            'sottofamigliaDescription' => $sottofamigliaDescription,
            'groupDescription' => $groupDescription,
            'items' => $items,
            'products' => $products,
            'backUrl' => $backUrl,
            'backLabel' => $backLabel,
        ]);
    }

    private function currentStore(): Store
    {
        /** @var Store $store */
        $store = app()->bound('adminStore')
            ? app('adminStore')
            : app('currentStore');

        return $store;
    }

    private function baseSimpleProductsQuery(Store $store): Builder
    {
        return Product::query()
            ->from('products')
            ->where('products.ditta_cg18', '=', (int) $store->ditta_cg18)
            ->where('products.site_type', '=', (int) $store->erp_site_code)
            ->where('products.type', '=', 'simple')
            ->where('products.is_active', '=', true);
    }

    private function familyDescription(Store $store, string $locale, string $fam): ?string
    {
        return GroupDescription::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->forLocale($locale)
            ->where('fam_code', '=', $fam)
            ->whereNull('sfam_code')
            ->whereNull('gruppo_code')
            ->value('description');
    }

    private function subfamilyDescription(Store $store, string $locale, string $fam, string $sfam): ?string
    {
        return GroupDescription::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->forLocale($locale)
            ->where('fam_code', '=', $fam)
            ->where('sfam_code', '=', $sfam)
            ->whereNull('gruppo_code')
            ->value('description');
    }

    private function groupDescription(Store $store, string $fam, string $sfam, string $gruppo): ?GroupDescription
    {
        return GroupDescription::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->forLocale(app()->getLocale())
            ->where('fam_code', '=', $fam)
            ->where('sfam_code', '=', $sfam)
            ->where('gruppo_code', '=', $gruppo)
            ->first();
    }
}