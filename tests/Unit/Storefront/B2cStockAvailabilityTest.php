<?php

namespace Tests\Unit\Storefront;

use App\Models\Product;
use App\Models\ProductCardViewModel;
use App\Models\Store;
use Tests\TestCase;

class B2cStockAvailabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        app()->forgetInstance('currentStore');

        parent::tearDown();
    }

    public function test_b2c_product_with_zero_stock_is_not_purchasable_even_when_backorder_flag_allows_it(): void
    {
        app()->instance('currentStore', new Store(['is_b2b' => false]));

        $product = new Product([
            'sku' => '7539CKB34',
            'type' => 'simple',
            'stock_qty' => 0,
            'no_backorder' => false,
            'min_order_qty' => 1,
        ]);

        $card = ProductCardViewModel::make($product, [
            'target_sku' => '7539CKB34',
        ]);

        $this->assertFalse($card->isPurchasable);
        $this->assertSame(0, $card->quantityMax);
    }

    public function test_b2b_product_with_zero_stock_can_still_use_backorder_flag(): void
    {
        app()->instance('currentStore', new Store(['is_b2b' => true]));

        $product = new Product([
            'sku' => '7539CKB34',
            'type' => 'simple',
            'stock_qty' => 0,
            'no_backorder' => false,
            'min_order_qty' => 1,
        ]);

        $card = ProductCardViewModel::make($product, [
            'target_sku' => '7539CKB34',
        ]);

        $this->assertTrue($card->isPurchasable);
        $this->assertNull($card->quantityMax);
    }
}
