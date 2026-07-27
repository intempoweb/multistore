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

    public function test_variant_attribute_options_preserve_the_other_selected_attribute(): void
    {
        $product = new Product([
            'sku' => 'PARENT',
            'type' => 'configurable',
        ]);

        $product->listing_variant_options = [
            [
                'sku' => 'RED-S',
                'image' => '/red-s.jpg',
                'color' => ['value' => 'Red'],
                'format' => ['value' => 'S'],
            ],
            [
                'sku' => 'RED-M',
                'image' => '/red-m.jpg',
                'color' => ['value' => 'Red'],
                'format' => ['value' => 'M'],
            ],
            [
                'sku' => 'BLUE-S',
                'image' => '/blue-s.jpg',
                'color' => ['value' => 'Blue'],
                'format' => ['value' => 'S'],
            ],
            [
                'sku' => 'BLUE-M',
                'image' => '/blue-m.jpg',
                'color' => ['value' => 'Blue'],
                'format' => ['value' => 'M'],
            ],
        ];

        $card = ProductCardViewModel::make($product, [
            'target_sku' => 'RED-M',
            'selected_color_value' => 'Red',
            'selected_format_value' => 'M',
        ]);

        $blueOption = $card->colorOptions->first(fn (array $option) => ($option['color']['value'] ?? null) === 'Blue');
        $smallOption = $card->formatOptions->first(fn (array $option) => ($option['format']['value'] ?? null) === 'S');

        $this->assertSame('BLUE-M', $blueOption['sku'] ?? null);
        $this->assertSame('/blue-m.jpg', $blueOption['image'] ?? null);
        $this->assertSame('RED-S', $smallOption['sku'] ?? null);
        $this->assertSame('/red-s.jpg', $smallOption['image'] ?? null);
    }
}
