<?php

namespace App\Services\Admin\Orders;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OrderSalesExportService
{
    private const HEADERS = [
        'ID',
        'Numero ordine',
        'Punto di acquisto',
        'Canale',
        'Data di acquisto',
        'Fattura intestata a',
        'Nome destinatario',
        'Totale complessivo (Base)',
        'Totale complessivo (acquistato)',
        'Valuta',
        'Stato',
        'Stato pagamento',
        'Indirizzo di fatturazione',
        'Indirizzo di spedizione',
        'Informazioni di spedizione',
        'Email cliente',
        'Subtotale',
        'Spedizione e gestione',
        'Nome cliente',
        'Metodo di pagamento',
        'Totale rimborsato',
        'Shipping Country',
    ];

    public function build(CarbonInterface $month, ?int $storeId = null, ?array $allowedStoreIds = null, ?string $channel = null): string
    {
        $month = Carbon::parse($month)->startOfMonth();
        $orders = $this->queryForMonth($month, $storeId, $allowedStoreIds, $channel)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($month->format('Y-m'));
        $sheet->fromArray(self::HEADERS, null, 'A1');

        $rowNumber = 2;

        foreach ($orders as $order) {
            $sheet->fromArray([$this->row($order)], null, 'A' . $rowNumber);
            $rowNumber++;
        }

        $lastRow = max(2, $rowNumber - 1);
        $sheet->getStyle('A1:V1')->getFont()->setBold(true);
        $sheet->getStyle('A1:V' . $lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('H2:I' . $lastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('Q2:R' . $lastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('U2:U' . $lastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:V' . $lastRow);

        foreach (range('A', 'V') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $directory = storage_path('app/tmp/order-sales-exports');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory . '/' . $this->filename($month, $storeId);
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function filename(CarbonInterface $month, ?int $storeId = null): string
    {
        $suffix = $storeId !== null ? '-store-' . $storeId : '';

        return 'corrispettivi-ordini-' . Carbon::parse($month)->format('Y-m') . $suffix . '.xlsx';
    }

    public function queryForMonth(CarbonInterface $month, ?int $storeId = null, ?array $allowedStoreIds = null, ?string $channel = null): Builder
    {
        $start = Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $startDate = $start->toDateTimeString();
        $endDate = $end->toDateTimeString();

        return Order::query()
            ->with(['store', 'customer'])
            ->where(function (Builder $query) use ($startDate, $endDate) {
                $query
                    ->whereBetween('placed_at', [$startDate, $endDate])
                    ->orWhere(function (Builder $fallback) use ($startDate, $endDate) {
                        $fallback
                            ->whereNull('placed_at')
                            ->whereBetween('created_at', [$startDate, $endDate]);
                    });
            })
            ->when($storeId !== null, fn (Builder $query) => $query->where('store_id', $storeId))
            ->when($allowedStoreIds !== null, fn (Builder $query) => $query->whereIn('store_id', $allowedStoreIds))
            ->when($channel !== null, fn (Builder $query) => $query->where('channel', $channel))
            ->orderByRaw('COALESCE(placed_at, created_at) asc')
            ->orderBy('id');
    }

    private function row(Order $order): array
    {
        return [
            $order->id,
            $order->order_number,
            $this->storeLabel($order),
            strtoupper((string) $order->channel),
            optional($order->placed_at ?? $order->created_at)?->format('d/m/Y H:i'),
            $this->invoiceName($order),
            $this->shippingName($order),
            $this->money($order->grand_total),
            $this->money($order->grand_total),
            $order->currency ?: 'EUR',
            $order->orderStatusLabel(),
            $order->paymentStatusLabel(),
            $this->address([
                $order->billing_company,
                trim((string) $order->billing_first_name . ' ' . (string) $order->billing_last_name),
                $order->billing_address_line_1,
                $order->billing_address_line_2,
                trim((string) $order->billing_postcode . ' ' . (string) $order->billing_city),
                $order->billing_province,
                $order->billing_country_code,
            ]),
            $this->address([
                $order->shipping_company,
                $order->shipping_contact_name,
                trim((string) $order->shipping_first_name . ' ' . (string) $order->shipping_last_name),
                $order->shipping_address_line_1,
                $order->shipping_address_line_2,
                trim((string) $order->shipping_postcode . ' ' . (string) $order->shipping_city),
                $order->shipping_province,
                $order->shipping_country_code,
            ]),
            $this->shippingInfo($order),
            $order->customer_email ?: ($order->billing_email ?: $order->shipping_email),
            $this->money($order->subtotal),
            $this->money($order->shipping_total),
            $order->customer_name,
            $order->payment_method_label ?: ($order->payment_gateway ? strtoupper((string) $order->payment_gateway) : ''),
            $this->money($order->refundAmount() ?? 0),
            $order->shipping_country_code,
        ];
    }

    private function storeLabel(Order $order): string
    {
        $store = $order->store;

        if ($store === null) {
            return 'Store #' . (string) $order->store_id;
        }

        return trim((string) $store->name . ($store->domain ? ' - ' . $store->domain : ''));
    }

    private function invoiceName(Order $order): string
    {
        return $this->firstFilled([
            $order->billing_company,
            $order->customer_company_name,
            trim((string) $order->billing_first_name . ' ' . (string) $order->billing_last_name),
            $order->customer_name,
        ]);
    }

    private function shippingName(Order $order): string
    {
        return $this->firstFilled([
            $order->shipping_contact_name,
            $order->shipping_company,
            trim((string) $order->shipping_first_name . ' ' . (string) $order->shipping_last_name),
            $order->customer_name,
        ]);
    }

    private function shippingInfo(Order $order): string
    {
        return $this->address([
            $order->shipping_method_label,
            $order->shipping_gateway ? 'Gateway: ' . $order->shipping_gateway : null,
            $order->shipping_carrier ? 'Corriere: ' . $order->shipping_carrier : null,
            $order->shipping_service_code ? 'Servizio: ' . $order->shipping_service_code : null,
            $order->sendcloudTrackingNumber() ? 'Tracking: ' . $order->sendcloudTrackingNumber() : null,
        ]);
    }

    private function address(array $parts): string
    {
        return collect($parts)
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->unique()
            ->implode(', ');
    }

    private function firstFilled(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
