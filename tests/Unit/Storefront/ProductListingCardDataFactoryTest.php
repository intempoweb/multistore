<?php

namespace Tests\Unit\Storefront;

use App\Services\Storefront\Catalog\ProductListingCardDataFactory;
use PHPUnit\Framework\TestCase;
use stdClass;

class ProductListingCardDataFactoryTest extends TestCase
{
    public function test_it_builds_the_existing_listing_card_payload(): void
    {
        $product = new stdClass;
        $product->sku = 'PARENT';
        $product->listing_target_sku = 'BLUE';
        $product->listing_variant_options = [
            [
                'sku' => 'RED',
                'image' => '/red.jpg',
                'price' => 12.5,
                'color' => ['value' => 'red'],
            ],
            [
                'sku' => 'BLUE',
                'image' => '/blue.jpg',
                'hover_image' => '/blue-hover.jpg',
                'effective_price' => 14.5,
                'color' => ['value' => 'blue'],
                'format' => ['value' => '15x21'],
            ],
        ];

        $payload = (new ProductListingCardDataFactory)->forProduct($product);

        $this->assertSame('BLUE', $payload['target_sku']);
        $this->assertSame('/blue.jpg', $payload['image']);
        $this->assertSame('/blue-hover.jpg', $payload['hover_image']);
        $this->assertSame(14.5, $payload['price']);
        $this->assertSame('blue', $payload['selected_color_value']);
        $this->assertSame('15x21', $payload['selected_format_value']);
    }

    public function test_it_falls_back_to_the_first_available_variant(): void
    {
        $product = new stdClass;
        $product->sku = 'PARENT';
        $product->listing_target_sku = 'MISSING';
        $product->listing_variant_options = [
            ['sku' => 'FIRST', 'image' => '/first.jpg'],
        ];

        $payload = (new ProductListingCardDataFactory)->forProduct($product);

        $this->assertSame('FIRST', $payload['target_sku']);
        $this->assertSame('/first.jpg', $payload['image']);
    }
}
