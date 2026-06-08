<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;

class StoreContext
{
    public function handle(Request $request, Closure $next)
    {
        $host = $this->normalizeHost($request->getHost());

        $activeStores = Store::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($activeStores->isEmpty()) {
            abort(404, 'Nessuno store attivo configurato.');
        }

        /** @var Store|null $domainStore */
        $domainStore = $activeStores->first(function (Store $store) use ($host) {
            return $this->normalizeHost((string) $store->domain) === $host;
        });

        if (!$domainStore instanceof Store) {
            abort(404, "Store non configurato per host: {$host}");
        }

        /** @var int|string|null $adminStoreId */
        $adminStoreId = $request->session()->get('admin_store_id');

        /** @var Store|null $adminStore */
        $adminStore = null;

        if ($adminStoreId !== null) {
            $adminStore = $activeStores->firstWhere('id', (int) $adminStoreId);
        }

        if (!$adminStore instanceof Store) {
            $adminStore = $domainStore;
        }

        $request->session()->put('admin_store_id', $adminStore->id);

        app()->instance('currentStore', $domainStore);
        app()->instance('adminStore', $adminStore);

        view()->share('currentStore', $domainStore);
        view()->share('adminStore', $adminStore);
        view()->share('adminStores', $activeStores);

        $fallbackLocale = config('app.fallback_locale', 'en');
        $storeLocale = $domainStore->default_locale ?: $fallbackLocale;
        $currentLocale = app()->getLocale();

        if (!$currentLocale || $currentLocale === $fallbackLocale) {
            app()->setLocale($storeLocale);
        }

        config([
            'app.store_theme' => $domainStore->theme,
            'app.store_locale' => $storeLocale,
            'app.current_store_id' => $domainStore->id,
            'app.current_store_domain' => $domainStore->domain,
            'app.admin_store_id' => $adminStore->id,
            'app.admin_store_domain' => $adminStore->domain,
        ]);

        return $next($request);
    }

    private function normalizeHost(string $host): string
    {
        return strtolower((string) preg_replace('/^www\./', '', trim($host)));
    }
}