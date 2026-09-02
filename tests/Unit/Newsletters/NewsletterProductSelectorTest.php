<?php

namespace Tests\Unit\Newsletters;

use App\Models\Newsletter;
use App\Models\Product;
use App\Models\Store;
use App\Services\Newsletters\NewsletterProductSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterProductSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_selects_current_promotions_for_the_newsletter_context(): void
    {
        $store = Store::query()->create([
            'ditta_cg18' => 1,
            'erp_site_code' => 1,
            'name' => 'B2B',
            'domain' => 'b2b.test',
            'is_b2b' => true,
            'is_active' => true,
        ]);

        $newsletter = Newsletter::query()->create([
            'store_id' => $store->id,
            'ditta_cg18' => 1,
            'site_type' => 1,
            'name' => 'Promo',
            'subject' => 'Promo',
            'selection_type' => Newsletter::SELECTION_PROMOTIONS,
            'provider' => Newsletter::PROVIDER_MAILCHIMP,
        ]);

        Product::query()->create([
            'ditta_cg18' => 1,
            'site_type' => 1,
            'sku' => 'PROMO-1',
            'type' => 'simple',
            'is_active' => true,
            'flgpromo_webt01' => true,
            'datainizpromo_webt01' => now()->subDay()->toDateString(),
            'datafinepromo_webt01' => now()->addDay()->toDateString(),
        ]);

        Product::query()->create([
            'ditta_cg18' => 3,
            'site_type' => 1,
            'sku' => 'OTHER-CONTEXT',
            'type' => 'simple',
            'is_active' => true,
            'flgpromo_webt01' => true,
            'datainizpromo_webt01' => now()->subDay()->toDateString(),
            'datafinepromo_webt01' => now()->addDay()->toDateString(),
        ]);

        $products = app(NewsletterProductSelector::class)->select($newsletter);

        $this->assertSame(['PROMO-1'], $products->pluck('sku')->all());
    }

    public function test_it_keeps_flagged_simple_variants_because_listino_is_on_the_child_sku(): void
    {
        $store = Store::query()->create([
            'ditta_cg18' => 1,
            'erp_site_code' => 1,
            'name' => 'B2B',
            'domain' => 'b2b.test',
            'is_b2b' => true,
            'is_active' => true,
        ]);

        $newsletter = Newsletter::query()->create([
            'store_id' => $store->id,
            'ditta_cg18' => 1,
            'site_type' => 1,
            'name' => 'Promo',
            'subject' => 'Promo',
            'selection_type' => Newsletter::SELECTION_PROMOTIONS,
            'provider' => Newsletter::PROVIDER_MAILCHIMP,
        ]);

        Product::query()->create([
            'ditta_cg18' => 1,
            'site_type' => 1,
            'sku' => 'PARENT',
            'type' => 'configurable',
            'is_active' => true,
        ]);

        Product::query()->create([
            'ditta_cg18' => 1,
            'site_type' => 1,
            'sku' => 'CHILD-A',
            'parent_code' => 'PARENT',
            'type' => 'simple',
            'is_active' => true,
            'flgpromo_webt01' => true,
        ]);

        Product::query()->create([
            'ditta_cg18' => 1,
            'site_type' => 1,
            'sku' => 'CHILD-B',
            'parent_code' => 'PARENT',
            'type' => 'simple',
            'is_active' => true,
            'flgpromo_webt01' => true,
        ]);

        $products = app(NewsletterProductSelector::class)->select($newsletter);

        $this->assertSame(['CHILD-A', 'CHILD-B'], $products->pluck('sku')->all());
    }

    public function test_manual_skus_preserve_user_order_and_do_not_return_parent_products(): void
    {
        $store = Store::query()->create([
            'ditta_cg18' => 3,
            'erp_site_code' => 1,
            'name' => 'FIPELL',
            'domain' => 'fipell.test',
            'is_b2b' => true,
            'is_active' => true,
        ]);

        $newsletter = Newsletter::query()->create([
            'store_id' => $store->id,
            'ditta_cg18' => 3,
            'site_type' => 1,
            'name' => 'Manuale',
            'subject' => 'Manuale',
            'selection_type' => Newsletter::SELECTION_MANUAL,
            'provider' => Newsletter::PROVIDER_MAILCHIMP,
        ]);

        Product::query()->create([
            'ditta_cg18' => 3,
            'site_type' => 1,
            'sku' => '1114ST',
            'type' => 'configurable',
            'is_active' => true,
        ]);

        Product::query()->create([
            'ditta_cg18' => 3,
            'site_type' => 1,
            'sku' => '1114ST22',
            'parent_code' => '1114ST',
            'type' => 'simple',
            'is_active' => true,
        ]);

        Product::query()->create([
            'ditta_cg18' => 3,
            'site_type' => 1,
            'sku' => '1114ST23',
            'parent_code' => '1114ST',
            'type' => 'simple',
            'is_active' => true,
        ]);

        $products = app(NewsletterProductSelector::class)->select($newsletter, ['1114ST23', '1114ST22', '1114ST']);

        $this->assertSame(['1114ST23', '1114ST22'], $products->pluck('sku')->all());
    }

    public function test_mixed_selection_without_checked_types_returns_no_products(): void
    {
        $store = Store::query()->create([
            'ditta_cg18' => 3,
            'erp_site_code' => 1,
            'name' => 'FIPELL',
            'domain' => 'fipell.test',
            'is_b2b' => true,
            'is_active' => true,
        ]);

        $newsletter = Newsletter::query()->create([
            'store_id' => $store->id,
            'ditta_cg18' => 3,
            'site_type' => 1,
            'name' => 'Mix',
            'subject' => 'Mix',
            'selection_type' => Newsletter::SELECTION_MIXED,
            'settings' => ['selection_types' => []],
            'provider' => Newsletter::PROVIDER_MAILCHIMP,
        ]);

        Product::query()->create([
            'ditta_cg18' => 3,
            'site_type' => 1,
            'sku' => 'PROMO-1',
            'type' => 'simple',
            'is_active' => true,
            'flgpromo_webt01' => true,
        ]);

        $products = app(NewsletterProductSelector::class)->select($newsletter);

        $this->assertTrue($products->isEmpty());
    }
}
