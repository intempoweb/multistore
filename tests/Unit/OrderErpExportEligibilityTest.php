<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\Erp\OrderExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OrderErpExportEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private int $orderSequence = 900000;

    public function test_b2c_invoice_order_can_export_to_erp_only_when_complete(): void
    {
        $processingOrder = new Order([
            'channel' => 'b2c',
            'status' => 'processing',
            'invoice_required' => true,
            'erp_export_status' => 'pending',
        ]);

        $completeOrder = new Order([
            'channel' => 'b2c',
            'status' => 'complete',
            'invoice_required' => true,
            'erp_export_status' => 'pending',
        ]);

        $this->assertTrue($processingOrder->requiresErpExport());
        $this->assertFalse($processingOrder->canExportToErp());
        $this->assertTrue($completeOrder->canExportToErp());
    }

    public function test_requires_erp_export_scope_includes_b2c_invoice_orders_only_after_completion(): void
    {
        $processingOrder = $this->order([
            'channel' => 'b2c',
            'status' => 'processing',
            'invoice_required' => true,
        ]);
        $completeOrder = $this->order([
            'channel' => 'b2c',
            'status' => 'complete',
            'invoice_required' => true,
        ]);
        $noInvoiceOrder = $this->order([
            'channel' => 'b2c',
            'status' => 'complete',
            'invoice_required' => false,
        ]);
        $b2bOrder = $this->order([
            'channel' => 'b2b',
            'status' => 'pending',
            'invoice_required' => false,
        ]);

        $ids = Order::query()
            ->requiresErpExport()
            ->pluck('id')
            ->all();

        $this->assertNotContains($processingOrder->id, $ids);
        $this->assertContains($completeOrder->id, $ids);
        $this->assertNotContains($noInvoiceOrder->id, $ids);
        $this->assertContains($b2bOrder->id, $ids);
    }

    public function test_export_service_rejects_b2c_invoice_orders_before_completion(): void
    {
        $order = $this->order([
            'channel' => 'b2c',
            'status' => 'processing',
            'invoice_required' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ordine B2C con fattura esportabile verso ERP solo dopo il completamento.');

        app(OrderExportService::class)->export($order);
    }

    private function order(array $attributes): Order
    {
        return Order::query()->create(array_merge([
            'ditta_cg18' => 1,
            'site_type' => 1,
            'order_number' => (string) ++$this->orderSequence,
            'payment_status' => 'pending',
            'fulfillment_status' => 'pending',
            'erp_export_status' => 'pending',
            'placed_at' => now(),
        ], $attributes));
    }
}
