<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $store = $this->currentStore();

        $productsQuery = Product::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code);

        $simpleActiveProductsQuery = Product::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->simple()
            ->active();

        $stats = [
            'products_total' => (clone $productsQuery)->count(),

            'products_simple' => (clone $productsQuery)
                ->simple()
                ->count(),

            'products_configurable' => (clone $productsQuery)
                ->configurable()
                ->count(),

            'products_active' => (clone $productsQuery)
                ->active()
                ->count(),

            'products_with_price' => (clone $productsQuery)
                ->whereNotNull('public_price')
                ->count(),

            'products_without_price' => (clone $productsQuery)
                ->whereNull('public_price')
                ->count(),

            'families_total' => (clone $simpleActiveProductsQuery)
                ->whereNotNull('fam_99')
                ->distinct()
                ->count('fam_99'),

            'subfamilies_total' => (clone $simpleActiveProductsQuery)
                ->whereNotNull('sfam_99')
                ->distinct()
                ->count('sfam_99'),

            'groups_total' => (clone $simpleActiveProductsQuery)
                ->whereNotNull('gruppo_99')
                ->distinct()
                ->count('gruppo_99'),

            'subgroups_total' => (clone $simpleActiveProductsQuery)
                ->whereNotNull('sgruppo_99')
                ->distinct()
                ->count('sgruppo_99'),

            'attributes_total' => Attribute::query()->count(),
            'attribute_values_total' => AttributeValue::query()->count(),

            'price_min' => (clone $productsQuery)
                ->whereNotNull('public_price')
                ->min('public_price'),

            'price_max' => (clone $productsQuery)
                ->whereNotNull('public_price')
                ->max('public_price'),
        ];

        $stores = Store::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $storeSummaries = Product::query()
            ->select([
                'ditta_cg18',
                'site_type',
                DB::raw('COUNT(*) as products_total'),
                DB::raw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as products_active'),
                DB::raw('SUM(CASE WHEN public_price IS NOT NULL THEN 1 ELSE 0 END) as products_with_price'),
                DB::raw('SUM(CASE WHEN type = "simple" THEN 1 ELSE 0 END) as products_simple'),
                DB::raw('SUM(CASE WHEN type = "configurable" THEN 1 ELSE 0 END) as products_configurable'),
            ])
            ->groupBy('ditta_cg18', 'site_type')
            ->get()
            ->keyBy(fn ($row) => ((int) $row->ditta_cg18) . ':' . ((int) $row->site_type));

        return view('admin.dashboard', [
            'store' => $store,
            'adminStore' => $store,
            'stores' => $stores,
            'storeSummaries' => $storeSummaries,
            'stats' => $stats,
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
}