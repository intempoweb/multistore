<?php

namespace App\Services\Storefront;

use App\Data\Storefront\CustomerCatalogContext;
use App\Models\Customer;
use App\Models\Store;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;

final class StorefrontContext
{
    public function __construct(
        private Application $app,
        private AuthFactory $auth,
    ) {}

    public function store(): Store
    {
        $store = $this->app->bound('currentStore')
            ? $this->app->make('currentStore')
            : null;

        abort_unless($store instanceof Store, 404, 'Store corrente non disponibile.');

        return $store;
    }

    public function locale(): string
    {
        return $this->app->getLocale();
    }

    public function customerCatalogContext(Store $store): CustomerCatalogContext
    {
        if ($store->isB2C()) {
            return new CustomerCatalogContext(null, null);
        }

        $customer = $this->auth->guard('customer')->user();

        if (! $customer instanceof Customer) {
            return new CustomerCatalogContext(null, null);
        }

        $tipocf = (int) ($customer->tipocf_cg44 ?? $customer->tipocf ?? $customer->tipo_cf ?? 0);
        $clifor = (int) ($customer->clifor_cg44 ?? $customer->clifor ?? $customer->codice_cg16 ?? 0);

        return new CustomerCatalogContext(
            tipocf: $tipocf >= 0 ? $tipocf : 0,
            clifor: $clifor > 0 ? $clifor : null,
        );
    }
}
