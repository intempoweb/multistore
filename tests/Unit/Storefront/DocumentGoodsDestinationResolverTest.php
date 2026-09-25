<?php

namespace Tests\Unit\Storefront;

use App\Models\Erp\DocumentHeader;
use App\Services\Storefront\Documents\DocumentGoodsDestinationResolver;
use PHPUnit\Framework\TestCase;

class DocumentGoodsDestinationResolverTest extends TestCase
{
    public function test_it_formats_goods_destination_for_display(): void
    {
        $this->assertSame(
            'IDEA CONVENIENZA - VIA SAN FRANCESCO, 7 - 34132 TRIESTE TS',
            DocumentGoodsDestinationResolver::format([
                'code' => '3',
                'name' => 'IDEA CONVENIENZA',
                'address' => 'VIA SAN FRANCESCO, 7',
                'postcode' => '34132',
                'city' => 'TRIESTE',
                'province' => 'TS',
            ])
        );
    }

    public function test_document_header_prefers_goods_destination_over_order_shipping_address(): void
    {
        $document = new DocumentHeader([
            'INDSPEDMERCE' => 'VIA G. MARCONI, 1 - 30035 MIRANO VE',
            'document_goods_destination' => [
                'name' => 'IDEA CONVENIENZA',
                'address' => 'VIA SAN FRANCESCO, 7',
                'postcode' => '34132',
                'city' => 'TRIESTE',
                'province' => 'TS',
            ],
        ]);

        $this->assertSame(
            'IDEA CONVENIENZA - VIA SAN FRANCESCO, 7 - 34132 TRIESTE TS',
            $document->shippingAddressForDisplay()
        );
    }

    public function test_document_header_keeps_existing_shipping_fallback_without_goods_destination(): void
    {
        $document = new DocumentHeader([
            'INDSPEDMERCE' => 'VIA G. MARCONI, 1 - 30035 MIRANO VE',
        ]);

        $this->assertSame(
            'VIA G. MARCONI, 1 - 30035 MIRANO VE',
            $document->shippingAddressForDisplay()
        );
    }

    public function test_it_ignores_unsafe_document_numbers_before_querying_erp(): void
    {
        $document = new DocumentHeader([
            'NUMREG_CO99' => "202600034508'",
        ]);

        $this->assertNull(
            (new DocumentGoodsDestinationResolver())->resolve($document)
        );
    }
}
