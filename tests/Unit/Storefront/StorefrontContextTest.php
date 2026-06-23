<?php

namespace Tests\Unit\Storefront;

use App\Models\Customer;
use App\Models\Store;
use App\Services\Storefront\StorefrontContext;
use Tests\TestCase;

class StorefrontContextTest extends TestCase
{
    public function test_it_resolves_the_current_store_and_b2c_catalog_context(): void
    {
        $store = new Store([
            'name' => 'B2C Store',
            'is_b2b' => false,
        ]);

        app()->instance('currentStore', $store);
        app()->setLocale('it');

        $context = app(StorefrontContext::class);
        $customerContext = $context->customerCatalogContext($store);

        $this->assertSame($store, $context->store());
        $this->assertSame('it', $context->locale());
        $this->assertNull($customerContext->tipocf);
        $this->assertNull($customerContext->clifor);
    }

    public function test_it_resolves_the_authenticated_b2b_customer_catalog_identifiers(): void
    {
        $store = new Store(['is_b2b' => true]);
        $customer = new Customer([
            'tipocf_cg44' => 1,
            'clifor_cg44' => 1234,
        ]);

        auth('customer')->setUser($customer);

        $customerContext = app(StorefrontContext::class)->customerCatalogContext($store);

        $this->assertSame(1, $customerContext->tipocf);
        $this->assertSame(1234, $customerContext->clifor);
    }
}
