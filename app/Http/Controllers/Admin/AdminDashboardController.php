<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Services\Admin\Analytics\AnalyticsDateRangeFactory;
use App\Services\Admin\Analytics\SalesAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(
        Request $request,
        AnalyticsDateRangeFactory $dateRangeFactory,
        SalesAnalyticsService $salesAnalyticsService,
    ): View {
        $store = $this->currentStore();
        $dateRange = $dateRangeFactory->fromRequest($request);
        $analytics = $salesAnalyticsService->dashboard($store, $dateRange);
        $stats = $salesAnalyticsService->catalogStats($store);

        $stores = Store::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (Store $store) => $this->canAccessStore($store))
            ->values();

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
            ->filter(function ($row) use ($stores) {
                return $stores->contains(function (Store $store) use ($row) {
                    return (int) $store->ditta_cg18 === (int) $row->ditta_cg18
                        && (int) $store->erp_site_code === (int) $row->site_type;
                });
            })
            ->keyBy(fn ($row) => ((int) $row->ditta_cg18).':'.((int) $row->site_type));

        return view('admin.dashboard', [
            'store' => $store,
            'adminStore' => $store,
            'stores' => $stores,
            'storeSummaries' => $storeSummaries,
            'stats' => $stats,
            'analytics' => $analytics,
            'dateRange' => $dateRange,
            'periodPresets' => AnalyticsDateRangeFactory::PRESETS,
        ]);
    }

    private function currentStore(): Store
    {
        /** @var Store $store */
        $store = admin_store();

        return $store;
    }

    private function canAccessStore(Store $store): bool
    {
        $user = request()->user();

        return ! $user
            || ! method_exists($user, 'canAccessAdminStore')
            || $user->canAccessAdminStore($store);
    }
}
