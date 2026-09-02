<?php

namespace Tests\Unit\Admin\Orders;

use App\Models\Order;
use App\Models\Store;
use App\Services\Admin\Orders\OrderSalesExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class OrderSalesExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_all_orders_for_the_selected_month(): void
    {
        $store = Store::query()->create([
            'ditta_cg18' => 1,
            'erp_site_code' => 1,
            'name' => 'INTEMPO',
            'domain' => 'intempo.test',
            'is_b2b' => true,
            'is_active' => true,
        ]);

        Order::query()->create([
            'store_id' => $store->id,
            'channel' => 'b2b',
            'ditta_cg18' => 1,
            'site_type' => 1,
            'order_number' => '1001',
            'status' => 'complete',
            'payment_status' => 'paid',
            'customer_name' => 'Mario Rossi',
            'customer_email' => 'mario@example.test',
            'subtotal' => 100,
            'shipping_total' => 10,
            'grand_total' => 110,
            'shipping_country_code' => 'ITA',
            'placed_at' => '2026-08-10 10:30:00',
        ]);

        Order::query()->create([
            'store_id' => $store->id,
            'channel' => 'b2b',
            'ditta_cg18' => 1,
            'site_type' => 1,
            'order_number' => '1002',
            'status' => 'canceled',
            'payment_status' => 'canceled',
            'customer_name' => 'Cliente Annullato',
            'subtotal' => 50,
            'shipping_total' => 0,
            'grand_total' => 50,
            'placed_at' => '2026-08-12 09:00:00',
        ]);

        Order::query()->create([
            'store_id' => $store->id,
            'channel' => 'b2b',
            'ditta_cg18' => 1,
            'site_type' => 1,
            'order_number' => '1003',
            'status' => 'complete',
            'payment_status' => 'paid',
            'customer_name' => 'Fuori Mese',
            'subtotal' => 70,
            'shipping_total' => 0,
            'grand_total' => 70,
            'placed_at' => '2026-09-01 10:30:00',
        ]);

        $path = app(OrderSalesExportService::class)->build(now()->setDate(2026, 8, 1));
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();

        $this->assertSame('ID', $rows[0][0]);
        $this->assertSame('1001', $rows[1][1]);
        $this->assertSame('1002', $rows[2][1]);
        $this->assertNull($rows[3][1] ?? null);
        $this->assertSame('Annullato', $rows[2][10]);

        @unlink($path);
    }
}
