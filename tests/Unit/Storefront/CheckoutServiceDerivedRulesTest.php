<?php

namespace Tests\Unit\Storefront;

use App\Models\Store;
use App\Services\Storefront\CheckoutService;
use App\Services\Storefront\InventoryStockService;
use App\Services\Storefront\Totals\CartTotalsService;
use PHPUnit\Framework\TestCase;

class CheckoutServiceDerivedRulesTest extends TestCase
{
    public function test_it_resolves_price_source_for_public_b2b_and_coupon_items(): void
    {
        $service = $this->service();

        $b2cStore = new Store(['is_b2b' => false]);
        $b2bStore = new Store(['is_b2b' => true]);

        $this->assertSame('public', $service->priceSource($b2cStore, (object) [
            'sku' => 'SKU-1',
            'listino_id' => 10,
        ]));

        $this->assertSame('b2b_tier', $service->priceSource($b2bStore, (object) [
            'sku' => 'SKU-1',
            'listino_id' => 10,
        ]));

        $this->assertSame('public', $service->priceSource($b2bStore, (object) [
            'sku' => 'SKU-1',
            'listino_id' => null,
        ]));

        $this->assertSame('coupon_discount', $service->priceSource($b2bStore, (object) [
            'sku' => 'MTBUONO-COUPON',
            'listino_id' => 10,
        ]));
    }

    public function test_it_preserves_erp_export_rules_for_b2b_b2c_invoice_and_fipell(): void
    {
        $service = $this->service();

        $b2cStore = new Store(['is_b2b' => false, 'ditta_cg18' => 1, 'erp_site_code' => 5]);
        $b2bStore = new Store(['is_b2b' => true, 'ditta_cg18' => 1, 'erp_site_code' => 1]);
        $fipellB2bStore = new Store(['is_b2b' => true, 'ditta_cg18' => 3, 'erp_site_code' => 1]);

        $this->assertFalse($service->requiresExport($b2cStore, false, false));
        $this->assertTrue($service->requiresExport($b2cStore, false, true));
        $this->assertTrue($service->requiresExport($b2bStore, true, false));
        $this->assertFalse($service->requiresExport($fipellB2bStore, true, false));
        $this->assertTrue($service->isFipellB2b($fipellB2bStore, true));
    }

    private function service(): object
    {
        return new class(
            $this->createMock(CartTotalsService::class),
            $this->createMock(InventoryStockService::class),
        ) extends CheckoutService {
            public function priceSource(Store $store, mixed $item): string
            {
                return $this->resolvePriceSource($store, $item);
            }

            public function requiresExport(Store $store, bool $isB2b, bool $invoiceRequired): bool
            {
                return $this->requiresErpExport($store, $isB2b, $invoiceRequired);
            }

            public function isFipellB2b(Store $store, bool $isB2b): bool
            {
                return $this->isFipellB2bStore($store, $isB2b);
            }
        };
    }
}
