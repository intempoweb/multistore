<?php

namespace Tests\Unit\Newsletters;

use App\Models\Newsletter;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\Store;
use App\Services\Newsletters\NewsletterProductSelector;
use App\Services\Newsletters\NewsletterRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_email_html_from_selected_products(): void
    {
        $store = Store::query()->create([
            'ditta_cg18' => 1,
            'erp_site_code' => 1,
            'name' => 'B2B',
            'domain' => 'shop.example.com',
            'is_b2b' => true,
            'is_active' => true,
            'default_locale' => 'it',
            'supported_locales' => ['it'],
        ]);

        $newsletter = Newsletter::query()->create([
            'store_id' => $store->id,
            'ditta_cg18' => 1,
            'site_type' => 1,
            'name' => 'Promo',
            'subject' => 'Promo settembre',
            'locale' => 'it',
            'selection_type' => Newsletter::SELECTION_MANUAL,
            'provider' => Newsletter::PROVIDER_BREVO,
            'listino_id' => 31,
        ]);

        $product = Product::query()->create([
            'ditta_cg18' => 1,
            'site_type' => 1,
            'sku' => 'LOSA4',
            'type' => 'simple',
            'is_active' => true,
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->id,
            'locale' => 'it',
            'name' => 'SUPER ATTAK 3GR. ORIGINAL',
            'description' => 'Colla istantanea per uso professionale.',
        ]);

        PriceTier::query()->create([
            'ditta_cg18' => 1,
            'listino_id' => 31,
            'sku' => 'LOSA4',
            'qty_from' => 1,
            'qty_to' => 0,
            'price_net' => 1.88,
        ]);

        app(NewsletterProductSelector::class)->syncProducts($newsletter, collect([$product]));

        $html = app(NewsletterRenderer::class)->render($newsletter->fresh(['store', 'products']));

        $this->assertStringContainsString('SUPER ATTAK 3GR. ORIGINAL', $html);
        $this->assertStringContainsString('LOSA4', $html);
        $this->assertStringContainsString('1,880 EUR', $html);
        $this->assertStringContainsString('https://shop.example.com', $html);
    }
}
