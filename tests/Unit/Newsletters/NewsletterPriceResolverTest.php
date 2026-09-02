<?php

namespace Tests\Unit\Newsletters;

use App\Models\PriceTier;
use App\Models\Product;
use App\Services\Newsletters\NewsletterPriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterPriceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_price_from_the_newsletter_listino(): void
    {
        $product = Product::query()->create([
            'ditta_cg18' => 1,
            'site_type' => 1,
            'sku' => 'SKU-1',
            'type' => 'simple',
            'is_active' => true,
            'public_price' => 99,
        ]);

        PriceTier::query()->create([
            'ditta_cg18' => 1,
            'listino_id' => 31,
            'sku' => 'SKU-1',
            'qty_from' => 1,
            'qty_to' => 0,
            'price_net' => 12.34,
        ]);

        $price = app(NewsletterPriceResolver::class)->resolve($product, 31);

        $this->assertSame(12.34, $price['price']);
        $this->assertSame('listino', $price['source']);
    }
}
