<?php

namespace App\Services\Erp;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class OrderExportService
{
    public function export(Order $order): Order
    {
        $order->loadMissing(['items', 'store', 'customer']);

        if (!$order->requiresErpExport()) {
            $order->forceFill([
                'erp_export_status' => 'skipped',
                'erp_export_error' => null,
            ])->save();

            return $order->fresh(['items']);
        }

        if ($order->isExportedToErp()) {
            return $order->fresh(['items']);
        }

        if (filled($order->erp_web_numreg) || filled($order->erp_web_id)) {
            throw new RuntimeException('Ordine già associato a un NUMREG ERP locale: export bloccato.');
        }

        if (!$order->isPlaced()) {
            throw new InvalidArgumentException('Ordine non ancora piazzato.');
        }

        if ($order->items->isEmpty()) {
            throw new InvalidArgumentException('Ordine senza righe.');
        }

        return DB::connection($this->connection())->transaction(function () use ($order) {
            $numreg = $this->nextNumreg($order);

            if ($this->erpHeaderExists($numreg)) {
                throw new RuntimeException("NUMREG ERP già esistente prima dell’inserimento: {$numreg}");
            }

            DB::connection($this->connection())
                ->table('dbo.WDO11_DOCTESTATA_WEB')
                ->insert($this->buildHeaderPayload($order, $numreg));

            $erpWebId = $this->resolveHeaderId($numreg);

            foreach ($order->items()->orderedRows()->get() as $index => $item) {
                $rowNumber = $item->erp_web_row_number ?: ($index + 1);

                DB::connection($this->connection())
                    ->table('dbo.WDO30_DOCCORPO_WEB')
                    ->insert($this->buildRowPayload($order, $item, $numreg, $rowNumber));

                $rowId = $this->resolveRowId($numreg, $rowNumber);

                $item->forceFill([
                    'erp_web_row_id' => $rowId,
                    'erp_web_numreg' => (string) $numreg,
                    'erp_web_row_number' => $rowNumber,
                    'erp_row_type' => $this->resolveErpRowType($item),
                ])->save();
            }

            $order->forceFill([
                'erp_web_id' => $erpWebId,
                'erp_web_numreg' => (string) $numreg,
                'erp_export_status' => 'exported',
                'erp_export_error' => null,
                'erp_exported_at' => now(),
                'erp_synced_at' => now(),
            ])->save();

            return $order->fresh(['items']);
        });
    }

    protected function connection(): string
    {
        return 'erp';
    }

    protected function nextNumreg(Order $order): int
    {
        return 8000 + (int) $order->id;
    }

    protected function erpHeaderExists(int $numreg): bool
    {
        return DB::connection($this->connection())
            ->table('dbo.WDO11_DOCTESTATA_WEB')
            ->where('WDO11_NUMREG_MAGE', $numreg)
            ->exists();
    }

    protected function resolveHeaderId(int $numreg): ?int
    {
        $id = DB::connection($this->connection())
            ->table('dbo.WDO11_DOCTESTATA_WEB')
            ->where('WDO11_NUMREG_MAGE', $numreg)
            ->value('WDO11_IDWEB');

        return $id !== null ? (int) $id : null;
    }

    protected function resolveRowId(int $numreg, int $rowNumber): ?int
    {
        $id = DB::connection($this->connection())
            ->table('dbo.WDO30_DOCCORPO_WEB')
            ->where('WDO30_NUMREG_MAGE_WDO11', $numreg)
            ->where('WDO30_PROGRESRIGA', $rowNumber)
            ->value('WDO30_IDWEB');

        return $id !== null ? (int) $id : null;
    }

    protected function buildHeaderPayload(Order $order, int $numreg): array
    {
        $now = now();

        return [
            'WDO11_NUMREG_MAGE' => $numreg,
            'WDO11_ANNODOC_MAGE' => (int) $now->format('Y'),
            'WDO11_DATADOC_MAGE' => $order->placed_at ?: $now,
            'WDO11_NUMDOC_MAGE' => $this->numericOrderNumber($order),
            'WDO11_SEZDOC_MAGE' => 'WE',
            'WDO11_CODPAG_CG62' => $order->payment_method_code,
            'WDO11_DESCRPAG_NOTE' => $order->payment_method_label,
            'WDO11_DITTA_CG18' => (int) $order->ditta_cg18,
            'WDO11_FLG_B2B_B2C_WEBT81' => $this->webFlag($order),

            'WDO11_INDEMAIL' => $order->customer_email,
            'WDO11_CLIFOR_CG44' => $order->isB2b() ? $order->customer_clifor_cg44 : null,

            'WDO11_RAGSOANAG_EMAIL' => $order->customer_company_name ?: $order->customer_name,
            'WDO11_COGNOME_EMAIL' => $order->billing_last_name,
            'WDO11_NOME_EMAIL' => $order->billing_first_name,
            'WDO11_RAGSOANAGEX_EMAIL' => null,
            'WDO11_INDIRIZZO_EMAIL' => $order->billing_address_line_1,
            'WDO11_CAP_EMAIL' => $order->billing_postcode,
            'WDO11_CITTA_EMAIL' => $order->billing_city,
            'WDO11_PROV_EMAIL' => $order->billing_province,
            'WDO11_DESCRSTATO_EMAIL' => $this->country2($order->billing_country_code),
            'WDO11_CELLNUM_EMAIL' => $order->customer_phone,
            'WDO11_TEL1NUM_EMAIL' => $order->billing_phone,

            'WDO11_PROGR_COD_SPED' => $order->isB2b() ? ($order->shipping_address_code ?: 0) : 0,
            'WDO11_RAGSOANAG_SPED' => $order->shipping_company ?: $order->shipping_contact_name,
            'WDO11_COGNOME_SPED' => $order->shipping_last_name,
            'WDO11_NOME_SPED' => $order->shipping_first_name,
            'WDO11_RAGSOANAGEX_SPED' => null,
            'WDO11_INDIRIZZO_SPED' => $order->shipping_address_line_1,
            'WDO11_CAP_SPED' => $order->shipping_postcode,
            'WDO11_CITTA_SPED' => $order->shipping_city,
            'WDO11_PROV_SPED' => $order->shipping_province,
            'WDO11_DESCRSTATO_SPED' => $this->country2($order->shipping_country_code),
            'WDO11_CELLNUM_SPED' => $order->shipping_phone,
            'WDO11_TEL1NUM_SPED' => null,

            'WDO11_PROGR_COD_FATT' => null,
            'WDO11_FLG_SIFATT' => $order->invoice_required ? 1 : 0,
            'WDO11_PARTIVA' => $order->customer_vat_number,
            'WDO11_CODICEFISCALE' => $order->customer_tax_code,
            'WDO11_RAGSOANAG_FT' => $order->billing_company ?: $order->customer_company_name,
            'WDO11_RAGSOANAGEX_FT' => null,
            'WDO11_FLGPRSFIS_FT' => $order->customer_vat_number ? 0 : 1,
            'WDO11_COGNOME_FT' => $order->billing_last_name,
            'WDO11_NOME_FT' => $order->billing_first_name,
            'WDO11_INDIRIZZO_FT' => $order->billing_address_line_1,
            'WDO11_CAP_FT' => $order->billing_postcode,
            'WDO11_CITTA_FT' => $order->billing_city,
            'WDO11_PROV_FT' => $order->billing_province,
            'WDO11_DESCRSTATO_FT' => $this->country2($order->billing_country_code),
            'WDO11_CELLNUM_FT' => $order->billing_phone,
            'WDO11_TEL_FT' => null,
            'WDO11_EMAIL_PEC_FT' => data_get($order->meta, 'b2c_invoice.pec'),
            'WDO11_CODICE_SDI_FT' => data_get($order->meta, 'b2c_invoice.sdi'),

            'WDO11_DATACREAZ' => $now,
            'WDO11_LASTCHANGE' => $now,
            'WDO11_CODICE_CORRIERE_MG14' => $order->shipping_service_code,
            'WDO11_RAGSOC_CORRIERE' => strtoupper((string) $order->shipping_carrier) ?: null,
            'WDO11_TRACKNUMBER' => $order->shipping_tracking_number,
        ];
    }

    protected function buildRowPayload(Order $order, OrderItem $item, int $numreg, int $rowNumber): array
    {
        $erpRowType = $this->resolveErpRowType($item);

        return [
            'WDO30_NUMREG_MAGE_WDO11' => $numreg,
            'WDO30_PROGRESRIGA' => $rowNumber,
            'WDO30_DITTA_CG18' => (int) $order->ditta_cg18,
            'WDO30_FLG_B2B_B2C_WEBT81' => $this->webFlag($order),
            'WDO30_INDTIPORIGA' => $erpRowType,
            'WDO30_CODART_MG66' => $item->sku,
            'WDO30_DESCART' => $item->product_name,
            'WDO30_DESCARTL' => $item->product_description ?: $item->product_name,
            'WDO30_CODVALUTA_CG08' => $order->currency ?: 'EUR',
            'WDO30_VALUTA_NOTE' => null,
            'WDO30_QTA1' => $this->decimal($item->quantity, 3),
            'WDO30_PREZZO1' => $this->erpMoney($order, $item->erp_price ?? $item->price_net ?? $item->price ?? 0),
            'WDO30_PREZZOIVA' => $this->erpMoney($order, $item->erp_price_tax ?? 0),
            'WDO30_PREZZOIVATO' => $this->erpMoney($order, $item->erp_price_gross ?? $item->price_gross ?? $item->price ?? 0),
            'WDO30_SCPER1' => $this->shouldExportDiscountPercentages($order, $item) ? $this->decimal($item->sc1 ?? 0, 3) : 0.000,
            'WDO30_SCPER2' => $this->shouldExportDiscountPercentages($order, $item) && $item->sc2 !== null ? $this->decimal($item->sc2, 3) : 0.000,
            'WDO30_SCPER3' => $this->shouldExportDiscountPercentages($order, $item) && $item->sc3 !== null ? $this->decimal($item->sc3, 3) : 0.000,
            'WDO30_ALIQIVA' => $this->decimal($item->tax_percent ?? 0, 3),
            'WDO30_CODIVA_CG28' => $item->tax_code ?: 'IVA',
            'WDO30_DESCRIVA' => $item->tax_label,
            'WDO30_IMPNESCTR' => $this->erpMoney($order, $item->erp_row_subtotal ?? $item->row_subtotal ?? 0),
            'WDO30_IMPNESTR_VAL' => null,
            'WDO30_IMPIVATR' => $this->erpMoney($order, $item->erp_row_tax_total ?? $item->row_tax_total ?? 0),
            'WDO30_IMPIVATR_VAL' => null,
            'WDO30_IMPNESCTOTR' => $this->erpMoney($order, $item->erp_row_net_total ?? $item->rowNetTotal()),
            'WDO30_IMPNESCTOTR_VAL' => null,
            'WDO30_IMPTOTRIGAINCAS' => $this->cashMoney($order, $item->erp_row_cash_total ?? $item->rowGrossTotal()),
            'WDO30_IMPTOTRIGAINCAS_VAL' => null,
        ];
    }

    protected function numericOrderNumber(Order $order): int
    {
        if (filled($order->legacy_magento_order_number)) {
            $number = preg_replace('/\D+/', '', (string) $order->legacy_magento_order_number);

            if ($number !== '' && strlen($number) <= 10) {
                return (int) $number;
            }
        }

        if (filled($order->erp_document_number)) {
            $number = preg_replace('/\D+/', '', (string) $order->erp_document_number);

            if ($number !== '' && strlen($number) <= 10) {
                return (int) $number;
            }
        }

        return 2000000000 + (int) $order->id;
    }

    protected function webFlag(Order $order): int
    {
        return $order->site_type !== null
            ? (int) $order->site_type
            : ($order->isB2b() ? 1 : 5);
    }

    protected function country2(?string $country): ?string
    {
        $country = strtoupper(trim((string) $country));

        return match ($country) {
            '' => null,
            'ITA' => 'IT',
            'ITALIA' => 'IT',
            default => substr($country, 0, 2),
        };
    }

    protected function erpMoney(Order $order, mixed $value): float
    {
        return $this->decimal($value, $order->isB2b() ? 3 : 2);
    }

    protected function cashMoney(Order $order, mixed $value): float
    {
        return $this->decimal($value, $order->isB2b() ? 3 : 2);
    }

    protected function shouldExportDiscountPercentages(Order $order, OrderItem $item): bool
    {

        if ($this->isCouponDiscountItem($item)) {

            return false;

        }

        if ($this->orderHasCouponDiscountItem($order)) {

            return false;

        }

        return true;

    }

    protected function orderHasCouponDiscountItem(Order $order): bool

    {

        return $order->items

            ->contains(fn (OrderItem $item) => $this->isCouponDiscountItem($item));

    }

    protected function resolveErpRowType(OrderItem $item): int
    {
        if ($this->isCouponDiscountItem($item)) {
            return 0;
        }

        return $item->erp_row_type ?? (filled($item->sku) ? 0 : 3);
    }

    protected function isCouponDiscountItem(OrderItem $item): bool
    {
        $sku = strtoupper(trim((string) ($item->sku ?? '')));

        return str_starts_with($sku, 'MTBUONO');
    }

    protected function decimal(mixed $value, int $decimals): float
    {
        return (float) number_format((float) ($value ?? 0), $decimals, '.', '');
    }
}