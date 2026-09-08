<?php

namespace Tests\Unit\Erp;

use App\Models\Order;
use App\Services\Erp\OrderExportService;
use ReflectionClass;
use Tests\TestCase;

class OrderExportServiceTest extends TestCase
{
    public function test_header_payload_limits_province_fields_to_erp_length(): void
    {
        $order = new Order([
            'channel' => 'b2c',
            'invoice_required' => true,
            'billing_province' => 'Antw',
            'shipping_province' => 'Antw',
            'billing_country_code' => 'BE',
            'shipping_country_code' => 'BE',
        ]);

        $method = (new ReflectionClass(OrderExportService::class))->getMethod('buildHeaderPayload');
        $payload = $method->invoke(new OrderExportService(), $order, 8041);

        $this->assertSame('AN', $payload['WDO11_PROV_EMAIL']);
        $this->assertSame('AN', $payload['WDO11_PROV_SPED']);
        $this->assertSame('AN', $payload['WDO11_PROV_FT']);
    }
}
