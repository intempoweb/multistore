<?php

namespace Tests\Unit\Admin\Analytics;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Services\Admin\Analytics\AnalyticsDateRange;
use App\Services\Admin\Analytics\SalesAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_are_scoped_to_selected_store(): void
    {
        $store = $this->store('Store A', 1);
        $otherStore = $this->store('Store B', 2);
        $product = $this->product($store, 'SKU-A');
        $otherProduct = $this->product($otherStore, 'SKU-B');

        $order = $this->order($store, 'A-1001', 120, '2026-09-02 10:00:00', [
            'customer_email' => 'first@example.test',
        ]);
        $this->item($order, $product, 2, 120);

        $previousOrder = $this->order($store, 'A-1000', 80, '2026-08-29 10:00:00', [
            'customer_email' => 'first@example.test',
        ]);
        $this->item($previousOrder, $product, 1, 80);

        $ignoredOrder = $this->order($otherStore, 'B-1001', 999, '2026-09-02 10:00:00');
        $this->item($ignoredOrder, $otherProduct, 9, 999);

        $range = new AnalyticsDateRange(
            preset: 'custom',
            start: Carbon::parse('2026-09-01 00:00:00'),
            end: Carbon::parse('2026-09-05 23:59:59'),
            previousStart: Carbon::parse('2026-08-27 00:00:00'),
            previousEnd: Carbon::parse('2026-08-31 23:59:59'),
        );

        $dashboard = app(SalesAnalyticsService::class)->dashboard($store, $range);

        $this->assertSame(120.0, $dashboard['kpis']['revenue']['value']);
        $this->assertSame(1, $dashboard['kpis']['orders']['value']);
        $this->assertSame(120.0, $dashboard['kpis']['aov']['value']);
        $this->assertSame(2.0, $dashboard['kpis']['quantity_sold']['value']);
        $this->assertSame(1, $dashboard['kpis']['customers']['value']);
        $this->assertSame(0, $dashboard['kpis']['new_customers']['value']);
        $this->assertSame(1, $dashboard['kpis']['returning_customers']['value']);
        $this->assertSame('SKU-A', $dashboard['top_products'][0]['sku']);
        $this->assertSame(120.0, $dashboard['top_products'][0]['revenue']);
    }

    public function test_cancelled_and_refunded_orders_do_not_increase_commercial_kpis(): void
    {
        $store = $this->store('Store A', 1);
        $product = $this->product($store, 'SKU-A');

        $paid = $this->order($store, 'A-1001', 120, '2026-09-02 10:00:00');
        $this->item($paid, $product, 2, 120);

        $cancelled = $this->order($store, 'A-1002', 90, '2026-09-03 10:00:00', [
            'status' => 'canceled',
            'payment_status' => 'canceled',
        ]);
        $this->item($cancelled, $product, 3, 90);

        $refunded = $this->order($store, 'A-1003', 50, '2026-09-04 10:00:00', [
            'payment_status' => 'refunded',
        ]);
        $this->item($refunded, $product, 1, 50);

        $range = new AnalyticsDateRange(
            preset: 'custom',
            start: Carbon::parse('2026-09-01 00:00:00'),
            end: Carbon::parse('2026-09-05 23:59:59'),
        );

        $dashboard = app(SalesAnalyticsService::class)->dashboard($store, $range);

        $this->assertSame(120.0, $dashboard['kpis']['revenue']['value']);
        $this->assertSame(1, $dashboard['kpis']['orders']['value']);
        $this->assertSame(2.0, $dashboard['kpis']['quantity_sold']['value']);
        $this->assertSame(3, collect($dashboard['order_statuses'])->sum('total'));
        $this->assertCount(2, $dashboard['order_statuses']);
    }

    private function store(string $name, int $siteType): Store
    {
        return Store::query()->create([
            'ditta_cg18' => 1,
            'erp_site_code' => $siteType,
            'name' => $name,
            'domain' => strtolower(str_replace(' ', '-', $name)).'.test',
            'is_b2b' => false,
            'is_active' => true,
        ]);
    }

    private function product(Store $store, string $sku): Product
    {
        return Product::query()->create([
            'ditta_cg18' => (int) $store->ditta_cg18,
            'site_type' => (int) $store->erp_site_code,
            'sku' => $sku,
            'type' => 'simple',
            'is_active' => true,
            'stock_qty' => 10,
            'fam_99' => 'FAM',
            'gruppo_99' => 'GRP',
            'marca_mg64' => 'BRAND',
        ]);
    }

    private function order(Store $store, string $number, float $total, string $placedAt, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'store_id' => $store->id,
            'channel' => $store->channel(),
            'ditta_cg18' => (int) $store->ditta_cg18,
            'site_type' => (int) $store->erp_site_code,
            'order_number' => $number,
            'status' => 'complete',
            'payment_status' => 'paid',
            'payment_gateway' => 'stripe',
            'customer_email' => 'customer@example.test',
            'subtotal' => $total,
            'grand_total' => $total,
            'placed_at' => $placedAt,
        ], $overrides));
    }

    private function item(Order $order, Product $product, float $quantity, float $total): OrderItem
    {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'ditta_cg18' => (int) $order->ditta_cg18,
            'site_type' => (int) $order->site_type,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'product_name' => 'Product '.$product->sku,
            'quantity' => $quantity,
            'row_subtotal' => $total,
            'row_total' => $total,
        ]);
    }
}
