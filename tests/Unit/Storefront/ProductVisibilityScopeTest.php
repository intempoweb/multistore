<?php

namespace Tests\Unit\Storefront;

use App\Models\Customer;
use App\Models\CustomerVisibleGroup;
use App\Models\Product;
use App\Models\StoreVisibleGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_b2b_visibility_is_scoped_by_store_ditta_when_customer_codes_overlap(): void
    {
        Customer::query()->create([
            'ditta_cg18' => 1,
            'tipocf_cg44' => 0,
            'clifor_cg44' => 5,
            'ragsoanag_cg16' => 'Cliente Intempo',
            'codrifalf_mg19' => 'PT',
            'is_active' => true,
        ]);

        Customer::query()->create([
            'ditta_cg18' => 3,
            'tipocf_cg44' => 0,
            'clifor_cg44' => 5,
            'ragsoanag_cg16' => 'Cliente Fipell',
            'codrifalf_mg19' => 'PT',
            'is_active' => true,
        ]);

        foreach ([1, 3] as $ditta) {
            StoreVisibleGroup::query()->create([
                'ditta_cg18' => $ditta,
                'site_type' => 1,
                'codice_xx32' => 'I1',
            ]);

            CustomerVisibleGroup::query()->create([
                'ditta_cg18' => $ditta,
                'flg_b2b_b2c_webt81' => '1',
                'tipocf_cg44' => 0,
                'clifor_cg44' => 5,
                'codice_xx32' => 'I1',
                'descrizione_xx32' => 'Gruppo I1',
                'flgattivo_xx32' => 1,
                'is_active' => true,
            ]);
        }

        Product::query()->create([
            'ditta_cg18' => 1,
            'site_type' => 1,
            'sku' => 'INTEMPO-SKU',
            'type' => 'simple',
            'is_active' => true,
            'codgrupfis_mg61' => 'I1',
        ]);

        Product::query()->create([
            'ditta_cg18' => 3,
            'site_type' => 1,
            'sku' => 'FIPELL-SKU',
            'type' => 'simple',
            'is_active' => true,
            'codgrupfis_mg61' => 'I1',
        ]);

        $visibleSkus = Product::query()
            ->visibleForCustomer(3, 1, 0, 5)
            ->pluck('sku')
            ->all();

        $this->assertSame(['FIPELL-SKU'], $visibleSkus);
    }
}
