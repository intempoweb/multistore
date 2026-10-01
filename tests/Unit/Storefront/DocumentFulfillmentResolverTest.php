<?php

namespace Tests\Unit\Storefront;

use App\Models\Erp\DocumentHeader;
use App\Models\Erp\DocumentRow;
use App\Services\Storefront\Documents\DocumentFulfillmentResolver;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DocumentFulfillmentResolverTest extends TestCase
{
    public function test_it_returns_empty_result_when_document_has_no_numreg(): void
    {
        $document = new DocumentHeader([
            'DITTA_CG18' => 1,
            'CLIFOR_CG44' => 31594,
            'TIPODOCDECOD_MG36' => 'ORDINE',
        ]);

        $result = (new DocumentFulfillmentResolver())->resolve($document);

        $this->assertSame('ORDINE', $result['document_type']);

        $this->assertInstanceOf(Collection::class, $result['rows']);
        $this->assertInstanceOf(Collection::class, $result['states']);
        $this->assertInstanceOf(Collection::class, $result['orders']);
        $this->assertInstanceOf(Collection::class, $result['ddts']);
        $this->assertInstanceOf(Collection::class, $result['invoices']);
        $this->assertInstanceOf(Collection::class, $result['shipments']);

        $this->assertTrue($result['rows']->isEmpty());
        $this->assertTrue($result['shipments']->isEmpty());
    }

    public function test_it_normalizes_an_erp_reference_and_preserves_carrier_code_as_string(): void
    {
        $resolver = new DocumentFulfillmentResolver();

        $method = new ReflectionMethod(
            DocumentFulfillmentResolver::class,
            'normalizeReference'
        );

        $reference = (object) [
            'DITTA_CG18' => 1,
            'CLIFOR_CG44' => 31594,
            'NUMREG_CO99' => '202600034866 ',
            'PROGRIGA_DO30' => 22,
            'CODART_MG66' => '9237RDT ',
            'STATO_VWEBDO31' => 'FATTURATO ',
            'QTA1ORDINE_DO30' => '6.000',
            'QTAPROCES_CALDO30' => '6.000',
            'QTASALDO_CALDO30' => '0.000',
            'DATASPEDIZ_DO11' => '25/09/2026',
            'CODVETTORE_MG14' => '1   ',
            'NUMREG_CO99_ORD' => '202600034866',
            'NUMREG_CO99_DDT' => '202600035053',
            'NUMREG_CO99_FAT' => '202600036289',
            'DOCCORRIERE_VWEBDO32' => '3427',
            'SEZCORRIERE_VWEBDO32' => 'B ',
            'NUMREGCORRIERE_VWEBDO32' => '202600035053',
            'TOTCOLLI_VWEBDO32' => '4.000',
            'IDCOLLICLI_VWEBDO32' => '028139054799869',
        ];

        $result = $method->invoke(
            $resolver,
            $reference
        );

        $this->assertSame(1, $result['ditta']);
        $this->assertSame(31594, $result['clifor']);

        $this->assertSame(
            '202600034866',
            $result['source_numreg']
        );

        $this->assertSame(22, $result['source_row']);
        $this->assertSame('9237RDT', $result['sku']);
        $this->assertSame('FATTURATO', $result['state']);

        $this->assertSame(
            6.0,
            $result['ordered_quantity']
        );

        $this->assertSame(
            6.0,
            $result['processed_quantity']
        );

        $this->assertSame(
            0.0,
            $result['remaining_quantity']
        );

        $this->assertSame(
            '25/09/2026',
            $result['shipping_date']
        );

        /*
         * Il codice vettore deve restare una stringa.
         */
        $this->assertSame(
            '1',
            $result['carrier_code']
        );

        /*
         * Il nome viene valorizzato successivamente
         * tramite VETTORI_VTA14.
         */
        $this->assertNull(
            $result['carrier_name']
        );

        $this->assertSame(
            '202600034866',
            $result['order_numreg']
        );

        $this->assertSame(
            '202600035053',
            $result['ddt_numreg']
        );

        $this->assertSame(
            '202600036289',
            $result['invoice_numreg']
        );

        $this->assertSame(
            '3427',
            $result['courier_document']
        );

        $this->assertSame(
            'B',
            $result['courier_section']
        );

        $this->assertSame(
            '202600035053',
            $result['courier_numreg']
        );

        $this->assertSame(
            4.0,
            $result['parcels']
        );

        /*
         * IDCOLLICLI resta parcel_id.
         *
         * Per BRT abbiamo verificato che può essere utilizzato
         * nel tracking pubblico, ma non assumiamo che abbia lo
         * stesso significato per tutti i vettori.
         */
        $this->assertSame(
            '028139054799869',
            $result['parcel_id']
        );

        $this->assertArrayNotHasKey(
            'tracking_number',
            $result
        );
    }

    public function test_it_preserves_leading_zero_in_carrier_code(): void
    {
        $resolver = new DocumentFulfillmentResolver();

        $method = new ReflectionMethod(
            DocumentFulfillmentResolver::class,
            'normalizeReference'
        );

        $reference = (object) [
            'DITTA_CG18' => 1,
            'CLIFOR_CG44' => 31594,
            'NUMREG_CO99' => '202600000001',
            'PROGRIGA_DO30' => 1,
            'CODART_MG66' => 'TEST',
            'STATO_VWEBDO31' => 'SPEDITO',
            'QTA1ORDINE_DO30' => '1.000',
            'QTAPROCES_CALDO30' => '1.000',
            'QTASALDO_CALDO30' => '0.000',
            'DATASPEDIZ_DO11' => '01/10/2026',

            /*
             * ERP:
             *
             * 05 = LOGIT SERVICE SRL
             * 5  = CORRIERE CASINI MARCO
             *
             * Non devono essere considerati equivalenti.
             */
            'CODVETTORE_MG14' => '05  ',

            'NUMREG_CO99_ORD' => '202600000001',
            'NUMREG_CO99_DDT' => '202600000002',
            'NUMREG_CO99_FAT' => null,
            'DOCCORRIERE_VWEBDO32' => null,
            'SEZCORRIERE_VWEBDO32' => null,
            'NUMREGCORRIERE_VWEBDO32' => null,
            'TOTCOLLI_VWEBDO32' => '1.000',
            'IDCOLLICLI_VWEBDO32' => null,
        ];

        $result = $method->invoke(
            $resolver,
            $reference
        );

        $this->assertSame(
            '05',
            $result['carrier_code']
        );

        $this->assertNotSame(
            '5',
            $result['carrier_code']
        );
    }

    public function test_it_keeps_carrier_05_and_5_as_distinct_shipments(): void
    {
        $resolver = new DocumentFulfillmentResolver();

        $method = new ReflectionMethod(
            DocumentFulfillmentResolver::class,
            'buildShipments'
        );

        $rows = collect([
            [
                'ditta' => 1,
                'clifor' => 31594,
                'ddt_numreg' => '202600000001',
                'shipping_date' => '01/10/2026',
                'carrier_code' => '05',
                'carrier_name' => 'LOGIT SERVICE SRL',
                'courier_document' => null,
                'courier_section' => null,
                'courier_numreg' => null,
                'parcels' => 1.0,
                'parcel_id' => null,
                'order_numreg' => '202600000010',
                'invoice_numreg' => null,
            ],
            [
                'ditta' => 1,
                'clifor' => 31594,
                'ddt_numreg' => '202600000001',
                'shipping_date' => '01/10/2026',
                'carrier_code' => '5',
                'carrier_name' => 'CORRIERE CASINI MARCO',
                'courier_document' => null,
                'courier_section' => null,
                'courier_numreg' => null,
                'parcels' => 1.0,
                'parcel_id' => null,
                'order_numreg' => '202600000010',
                'invoice_numreg' => null,
            ],
        ]);

        $shipments = $method->invoke(
            $resolver,
            $rows
        );

        $this->assertCount(
            2,
            $shipments
        );

        $this->assertSame(
            ['05', '5'],
            $shipments
                ->pluck('carrier_code')
                ->sort()
                ->values()
                ->all()
        );

        $this->assertSame(
            [
                'CORRIERE CASINI MARCO',
                'LOGIT SERVICE SRL',
            ],
            $shipments
                ->pluck('carrier_name')
                ->sort()
                ->values()
                ->all()
        );
    }

    public function test_it_groups_equal_logistics_rows_into_one_shipment(): void
    {
        $resolver = new DocumentFulfillmentResolver();

        $method = new ReflectionMethod(
            DocumentFulfillmentResolver::class,
            'buildShipments'
        );

        $rows = collect([
            [
                'ditta' => 1,
                'clifor' => 31594,
                'ddt_numreg' => '202600035053',
                'shipping_date' => '25/09/2026',
                'carrier_code' => '1',
                'carrier_name' => 'BRT S.P.A.',
                'courier_document' => '3427',
                'courier_section' => 'B',
                'courier_numreg' => '202600035053',
                'parcels' => 4.0,
                'parcel_id' => '028139054799869',
                'order_numreg' => '202600034866',
                'invoice_numreg' => '202600036289',
            ],
            [
                'ditta' => 1,
                'clifor' => 31594,
                'ddt_numreg' => '202600035053',
                'shipping_date' => '25/09/2026',
                'carrier_code' => '1',
                'carrier_name' => 'BRT S.P.A.',
                'courier_document' => '3427',
                'courier_section' => 'B',
                'courier_numreg' => '202600035053',
                'parcels' => 4.0,
                'parcel_id' => '028139054799869',
                'order_numreg' => '202600034866',
                'invoice_numreg' => '202600036289',
            ],
        ]);

        $shipments = $method->invoke(
            $resolver,
            $rows
        );

        $this->assertCount(
            1,
            $shipments
        );

        $shipment = $shipments->first();

        $this->assertSame(
            1,
            $shipment['ditta']
        );

        $this->assertSame(
            31594,
            $shipment['clifor']
        );

        $this->assertSame(
            '202600035053',
            $shipment['ddt_numreg']
        );

        $this->assertSame(
            '25/09/2026',
            $shipment['shipping_date']
        );

        $this->assertSame(
            '1',
            $shipment['carrier_code']
        );

        $this->assertSame(
            'BRT S.P.A.',
            $shipment['carrier_name']
        );

        $this->assertSame(
            '3427',
            $shipment['courier_document']
        );

        $this->assertSame(
            'B',
            $shipment['courier_section']
        );

        $this->assertSame(
            4.0,
            $shipment['parcels']
        );

        $this->assertSame(
            '028139054799869',
            $shipment['parcel_id']
        );

        $this->assertSame(
            ['202600034866'],
            $shipment['orders']->all()
        );

        $this->assertSame(
            ['202600036289'],
            $shipment['invoices']->all()
        );
    }

    public function test_it_keeps_shipments_from_different_companies_separate(): void
    {
        $resolver = new DocumentFulfillmentResolver();

        $method = new ReflectionMethod(
            DocumentFulfillmentResolver::class,
            'buildShipments'
        );

        $rows = collect([
            [
                'ditta' => 1,
                'clifor' => 31880,
                'ddt_numreg' => '202600011663',
                'shipping_date' => '25/05/2026',
                'carrier_code' => null,
                'carrier_name' => null,
                'courier_document' => null,
                'courier_section' => null,
                'courier_numreg' => null,
                'parcels' => null,
                'parcel_id' => null,
                'order_numreg' => '202600011663',
                'invoice_numreg' => '202600012151',
            ],
            [
                'ditta' => 3,
                'clifor' => 3477,
                'ddt_numreg' => '202600011663',
                'shipping_date' => '25/05/2026',
                'carrier_code' => null,
                'carrier_name' => null,
                'courier_document' => null,
                'courier_section' => null,
                'courier_numreg' => null,
                'parcels' => null,
                'parcel_id' => null,
                'order_numreg' => '202600011663',
                'invoice_numreg' => '202600012151',
            ],
        ]);

        $shipments = $method->invoke(
            $resolver,
            $rows
        );

        $this->assertCount(
            2,
            $shipments
        );

        $this->assertSame(
            [1, 3],
            $shipments
                ->pluck('ditta')
                ->sort()
                ->values()
                ->all()
        );
    }

    public function test_it_attaches_fulfillment_to_matching_order_row_by_progressive_and_sku(): void
    {
        $document = new DocumentHeader([
            'NUMREG_CO99' => '202600034866',
            'DITTA_CG18' => 1,
            'CLIFOR_CG44' => 31594,
            'TIPODOCDECOD_MG36' => 'ORDINE',
        ]);

        $row = new DocumentRow([
            'NUMREG_CO99' => '202600034866',
            'PROGRIGA_DO30' => 22,
            'CODART_MG66' => '9237RDT',
        ]);

        $document->setRelation(
            'rows',
            collect([$row])
        );

        $references = collect([
            [
                'ditta' => 1,
                'clifor' => 31594,
                'source_numreg' => '202600034866',
                'source_row' => 22,
                'sku' => '9237RDT',
                'state' => 'FATTURATO',
                'ordered_quantity' => 6.0,
                'processed_quantity' => 6.0,
                'remaining_quantity' => 0.0,
                'shipping_date' => '25/09/2026',
                'carrier_code' => '1',
                'carrier_name' => 'BRT S.P.A.',
                'order_numreg' => '202600034866',
                'ddt_numreg' => '202600035053',
                'invoice_numreg' => '202600036289',
                'courier_document' => '3427',
                'courier_section' => 'B',
                'courier_numreg' => '202600035053',
                'parcels' => 4.0,
                'parcel_id' => '028139054799869',
            ],
        ]);

        $resolver = new DocumentFulfillmentResolver();

        $method = new ReflectionMethod(
            DocumentFulfillmentResolver::class,
            'attachRows'
        );

        $method->invoke(
            $resolver,
            $document,
            $references
        );

        $fulfillment = $row->getAttribute(
            'document_fulfillment'
        );

        $this->assertIsArray(
            $fulfillment
        );

        $this->assertSame(
            1,
            $fulfillment['ditta']
        );

        $this->assertSame(
            '9237RDT',
            $fulfillment['sku']
        );

        $this->assertSame(
            'FATTURATO',
            $fulfillment['state']
        );

        $this->assertSame(
            6.0,
            $fulfillment['ordered_quantity']
        );

        $this->assertSame(
            6.0,
            $fulfillment['processed_quantity']
        );

        $this->assertSame(
            0.0,
            $fulfillment['remaining_quantity']
        );

        $this->assertSame(
            'BRT S.P.A.',
            $fulfillment['carrier_name']
        );

        $this->assertSame(
            '202600035053',
            $fulfillment['ddt_numreg']
        );
    }

    public function test_it_matches_same_progressive_to_different_skus_across_companies(): void
    {
        /*
         * Caso ERP reale:
         *
         * ordine 202600011663
         *
         * PROGRIGA 1:
         * - ditta 1 / 7090CFC
         * - ditta 3 / 16547
         *
         * Il progressivo è quindi ambiguo.
         * Lo SKU deve determinare il riferimento corretto.
         */
        $document = new DocumentHeader([
            'NUMREG_CO99' => '202600011663',
            'DITTA_CG18' => 1,
            'CLIFOR_CG44' => 31880,
            'TIPODOCDECOD_MG36' => 'ORDINE',
        ]);

        $rowDitta1 = new DocumentRow([
            'NUMREG_CO99' => '202600011663',
            'PROGRIGA_DO30' => 1,
            'CODART_MG66' => '7090CFC',
        ]);

        $rowDitta3 = new DocumentRow([
            'NUMREG_CO99' => '202600011663',
            'PROGRIGA_DO30' => 1,
            'CODART_MG66' => '16547',
        ]);

        $document->setRelation(
            'rows',
            collect([
                $rowDitta1,
                $rowDitta3,
            ])
        );

        $references = collect([
            [
                'ditta' => 1,
                'clifor' => 31880,
                'source_numreg' => '202600011663',
                'source_row' => 1,
                'sku' => '7090CFC',
                'state' => 'FATTURATO',
                'ordered_quantity' => 806.0,
                'processed_quantity' => 806.0,
                'remaining_quantity' => 0.0,
                'shipping_date' => '30/09/2026',
                'carrier_code' => '1',
                'carrier_name' => 'BRT S.P.A.',
                'order_numreg' => '202600011663',
                'ddt_numreg' => '202600035802',
                'invoice_numreg' => '202600036319',
                'courier_document' => '3508',
                'courier_section' => 'B',
                'courier_numreg' => '202600035802',
                'parcels' => 1.0,
                'parcel_id' => '028061054800191',
            ],
            [
                'ditta' => 3,
                'clifor' => 3477,
                'source_numreg' => '202600011663',
                'source_row' => 1,
                'sku' => '16547',
                'state' => 'FATTURATO',
                'ordered_quantity' => 20.0,
                'processed_quantity' => 20.0,
                'remaining_quantity' => 0.0,
                'shipping_date' => '25/05/2026',
                'carrier_code' => null,
                'carrier_name' => null,
                'order_numreg' => '202600011663',
                'ddt_numreg' => '202600011663',
                'invoice_numreg' => '202600012151',
                'courier_document' => null,
                'courier_section' => null,
                'courier_numreg' => null,
                'parcels' => null,
                'parcel_id' => null,
            ],
        ]);

        $resolver = new DocumentFulfillmentResolver();

        $method = new ReflectionMethod(
            DocumentFulfillmentResolver::class,
            'attachRows'
        );

        $method->invoke(
            $resolver,
            $document,
            $references
        );

        $fulfillmentDitta1 = $rowDitta1->getAttribute(
            'document_fulfillment'
        );

        $fulfillmentDitta3 = $rowDitta3->getAttribute(
            'document_fulfillment'
        );

        $this->assertIsArray(
            $fulfillmentDitta1
        );

        $this->assertIsArray(
            $fulfillmentDitta3
        );

        $this->assertSame(
            1,
            $fulfillmentDitta1['ditta']
        );

        $this->assertSame(
            '7090CFC',
            $fulfillmentDitta1['sku']
        );

        $this->assertSame(
            806.0,
            $fulfillmentDitta1['processed_quantity']
        );

        $this->assertSame(
            '202600036319',
            $fulfillmentDitta1['invoice_numreg']
        );

        $this->assertSame(
            3,
            $fulfillmentDitta3['ditta']
        );

        $this->assertSame(
            '16547',
            $fulfillmentDitta3['sku']
        );

        $this->assertSame(
            20.0,
            $fulfillmentDitta3['processed_quantity']
        );

        $this->assertSame(
            '202600012151',
            $fulfillmentDitta3['invoice_numreg']
        );
    }

    public function test_it_does_not_attach_order_progressives_to_ddt_physical_rows(): void
    {
        $document = new DocumentHeader([
            'NUMREG_CO99' => '202600035053',
            'DITTA_CG18' => 1,
            'CLIFOR_CG44' => 31594,
            'TIPODOCDECOD_MG36' => 'DDT',
        ]);

        /*
         * Sul DDT reale 202600035053 lo SKU 9237RDT è alla riga 23,
         * mentre nella correlazione dell'ordine è alla riga 22.
         */
        $row = new DocumentRow([
            'NUMREG_CO99' => '202600035053',
            'PROGRIGA_DO30' => 23,
            'CODART_MG66' => '9237RDT',
        ]);

        $document->setRelation(
            'rows',
            collect([$row])
        );

        $references = collect([
            [
                'ditta' => 1,
                'clifor' => 31594,
                'source_numreg' => '202600034866',
                'source_row' => 22,
                'sku' => '9237RDT',
                'state' => 'FATTURATO',
                'ordered_quantity' => 6.0,
                'processed_quantity' => 6.0,
                'remaining_quantity' => 0.0,
                'shipping_date' => '25/09/2026',
                'carrier_code' => '1',
                'carrier_name' => 'BRT S.P.A.',
                'order_numreg' => '202600034866',
                'ddt_numreg' => '202600035053',
                'invoice_numreg' => '202600036289',
                'courier_document' => '3427',
                'courier_section' => 'B',
                'courier_numreg' => '202600035053',
                'parcels' => 4.0,
                'parcel_id' => '028139054799869',
            ],
        ]);

        $resolver = new DocumentFulfillmentResolver();

        $method = new ReflectionMethod(
            DocumentFulfillmentResolver::class,
            'attachRows'
        );

        $method->invoke(
            $resolver,
            $document,
            $references
        );

        $this->assertNull(
            $row->getAttribute(
                'document_fulfillment'
            )
        );
    }
}