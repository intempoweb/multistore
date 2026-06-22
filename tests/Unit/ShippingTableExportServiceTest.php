<?php

namespace Tests\Unit;

use App\Models\ShippingRule;
use App\Services\Shipping\Export\ShippingTableExportService;
use PHPUnit\Framework\TestCase;

class ShippingTableExportServiceTest extends TestCase
{
    public function test_it_exports_a_csv_compatible_with_the_shipping_table_import(): void
    {
        $rules = [
            new ShippingRule([
                'country' => 'ITA',
                'province' => 'MI',
                'cap' => '201*',
                'weight_from' => 0,
                'amount' => 5.9,
            ]),
            new ShippingRule([
                'country' => null,
                'province' => null,
                'cap' => null,
                'weight_from' => 5.25,
                'amount' => 7,
            ]),
        ];
        $handle = fopen('php://temp', 'w+b');

        (new ShippingTableExportService())->writeCsv($rules, $handle);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Nazione;Provincia;CAP;"Peso (e superiore)";"Prezzo di spedizione"', $csv);
        $this->assertStringContainsString('ITA;MI;201*;0;5.9', $csv);
        $this->assertStringContainsString('*;*;*;5.25;7', $csv);
    }
}
