<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Order extends Model
{
    protected $fillable = [
        'store_id',
        'channel',
        'ditta_cg18',
        'site_type',
        'cart_id',
        'customer_id',
        'customer_tipocf_cg44',
        'customer_clifor_cg44',
        'order_number',
        'legacy_magento_order_number',
        'erp_web_id',
        'erp_web_numreg',
        'erp_document_number',
        'erp_document_visible_number',
        'status',
        'payment_status',
        'fulfillment_status',
        'source',
        'currency',
        'invoice_required',
        'invoice_status',
        'erp_export_status',
        'erp_export_error',
        'customer_code',
        'customer_company_name',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_vat_number',
        'customer_tax_code',
        'billing_company',
        'billing_first_name',
        'billing_last_name',
        'billing_address_line_1',
        'billing_address_line_2',
        'billing_postcode',
        'billing_city',
        'billing_province',
        'billing_country_code',
        'billing_phone',
        'billing_email',
        'customer_shipping_address_id',
        'shipping_address_code',
        'shipping_company',
        'shipping_contact_name',
        'shipping_first_name',
        'shipping_last_name',
        'shipping_address_line_1',
        'shipping_address_line_2',
        'shipping_postcode',
        'shipping_city',
        'shipping_province',
        'shipping_country_code',
        'shipping_phone',
        'shipping_email',
        'payment_method_code',
        'payment_method_label',
        'payment_terms_code',
        'payment_terms_label',
        'payment_vat_percent',
        'bank_abi',
        'bank_cab',
        'bank_name',
        'bank_iban',
        'payment_gateway',
        'payment_transaction_id',
        'paid_at',
        'shipping_method_code',
        'shipping_method_label',
        'shipping_gateway',
        'shipping_carrier',
        'shipping_service_code',
        'shipping_tracking_number',
        'shipping_label_url',
        'shipping_label_created_at',
        'coupon_code',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'grand_total',
        'customer_notes',
        'internal_notes',
        'meta',
        'erp_exported_at',
        'erp_synced_at',
        'placed_at',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'ditta_cg18' => 'integer',
        'site_type' => 'integer',
        'cart_id' => 'integer',
        'customer_id' => 'integer',
        'customer_tipocf_cg44' => 'integer',
        'customer_clifor_cg44' => 'integer',
        'legacy_magento_order_number' => 'string',
        'erp_web_id' => 'integer',
        'erp_web_numreg' => 'string',
        'erp_document_number' => 'string',
        'erp_document_visible_number' => 'string',
        'shipping_address_code' => 'integer',
        'payment_terms_code' => 'integer',
        'bank_abi' => 'integer',
        'bank_cab' => 'integer',
        'invoice_required' => 'boolean',
        'payment_vat_percent' => 'decimal:2',
        'subtotal' => 'decimal:3',
        'discount_total' => 'decimal:3',
        'shipping_total' => 'decimal:3',
        'tax_total' => 'decimal:3',
        'grand_total' => 'decimal:3',
        'paid_at' => 'datetime',
        'shipping_label_created_at' => 'datetime',
        'erp_exported_at' => 'datetime',
        'erp_synced_at' => 'datetime',
        'placed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerShippingAddress::class, 'customer_shipping_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForContext(Builder $query, int $ditta, ?int $siteType = null): Builder
    {
        $query->where('ditta_cg18', $ditta);

        if ($siteType !== null) {
            $query->where('site_type', $siteType);
        }

        return $query;
    }

    public function scopeChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', trim($channel));
    }

    public function scopeB2b(Builder $query): Builder
    {
        return $query->where('channel', 'b2b');
    }

    public function scopeB2c(Builder $query): Builder
    {
        return $query->where('channel', 'b2c');
    }

    public function scopePlaced(Builder $query): Builder
    {
        return $query->whereNotNull('placed_at');
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', trim($status));
    }

    public function scopePaymentStatus(Builder $query, string $status): Builder
    {
        return $query->where('payment_status', trim($status));
    }

    public function scopeFulfillmentStatus(Builder $query, string $status): Builder
    {
        return $query->where('fulfillment_status', trim($status));
    }

    public function scopePendingErpExport(Builder $query): Builder
    {
        return $query->where('erp_export_status', 'pending');
    }

    public function scopeFailedErpExport(Builder $query): Builder
    {
        return $query->where('erp_export_status', 'failed');
    }

    public function scopeSkippedErpExport(Builder $query): Builder
    {
        return $query->where('erp_export_status', 'skipped');
    }

    public function scopeExportedToErp(Builder $query): Builder
    {
        return $query->where('erp_export_status', 'exported');
    }

    public function scopeInvoiceRequired(Builder $query): Builder
    {
        return $query->where('invoice_required', true);
    }

    public function scopeRequiresErpExport(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery): void {
            $subQuery
                ->where(function (Builder $b2bQuery): void {
                    $b2bQuery
                        ->where('channel', 'b2b')
                        ->where(function (Builder $allowedB2b): void {
                            $allowedB2b
                                ->where('ditta_cg18', '!=', 3)
                                ->orWhere('site_type', '!=', 1)
                                ->orWhereNull('site_type');
                        });
                })
                ->orWhere(function (Builder $b2cQuery): void {
                    $b2cQuery
                        ->where('channel', 'b2c')
                        ->where('invoice_required', true);
                });
        });
    }

    public function scopeWithTracking(Builder $query): Builder
    {
        return $query->whereNotNull('shipping_tracking_number');
    }

    public function isB2b(): bool
    {
        return (string) $this->channel === 'b2b';
    }

    public function isB2c(): bool
    {
        return (string) $this->channel === 'b2c';
    }

    public function isPlaced(): bool
    {
        return $this->placed_at !== null;
    }

    public function isPending(): bool
    {
        return (string) $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return (string) $this->status === 'processing';
    }

    public function isComplete(): bool
    {
        return (string) $this->status === 'complete';
    }

    public function isClosed(): bool
    {
        return (string) $this->status === 'closed';
    }

    public function isCanceled(): bool
    {
        return in_array((string) $this->status, ['canceled', 'cancelled'], true);
    }

    public function isPaid(): bool
    {
        return (string) $this->payment_status === 'paid';
    }

    public function isRefunded(): bool
    {
        return (string) $this->payment_status === 'refunded';
    }

    public function canConfirmStock(): bool
    {
        return $this->isB2c()
            && $this->canCapturePayment()
            && !$this->isProcessing()
            && !$this->isClosed()
            && !$this->isCanceled();
    }

    public function canCapturePayment(): bool
    {
        return $this->isB2c()
            && in_array((string) $this->payment_gateway, ['stripe', 'paypal'], true)
            && filled($this->payment_transaction_id)
            && in_array((string) $this->payment_status, ['pending', 'authorized'], true)
            && !$this->isCanceled()
            && !$this->isClosed();
    }

   public function canRefundPayment(): bool
    {

        return $this->isB2c()

            && in_array((string) $this->payment_gateway, ['stripe', 'paypal'], true)

            && filled($this->payment_transaction_id)

            && $this->isPaid()

            && !$this->isRefunded();

    }
    public function canRefundAndClose(): bool
    {
        return $this->canRefundPayment();
    }

    public function canMarkCompletedFromBo(): bool
    {
        return $this->isProcessing();
    }

    public function canCancelFromBo(): bool
    {
        return !$this->isClosed()
            && !$this->isCanceled()
            && !$this->isRefunded();
    }

    public function isExportedToErp(): bool
    {
        return (string) $this->erp_export_status === 'exported';
    }

    public function isPendingErpExport(): bool
    {
        return (string) $this->erp_export_status === 'pending';
    }

    public function isFailedErpExport(): bool
    {
        return (string) $this->erp_export_status === 'failed';
    }

    public function isSkippedErpExport(): bool
    {
        return (string) $this->erp_export_status === 'skipped';
    }

    public function requiresErpExport(): bool
    {
        if ($this->isFipellB2b()) {
            return false;
        }

        return $this->isB2b() || ($this->isB2c() && (bool) $this->invoice_required);
    }

    public function canExportToErp(): bool
    {
        return $this->requiresErpExport()
            && !$this->isExportedToErp()
            && !$this->isCanceled()
            && !$this->isClosed();
    }

    public function erpExportReason(): string
    {
        if ($this->isFipellB2b()) {
            return 'fipell_b2b_erp_disabled';
        }

        if ($this->isB2b()) {
            return 'b2b_order';
        }

        if ($this->isB2c() && (bool) $this->invoice_required) {
            return 'b2c_invoice_required';
        }

        return 'b2c_no_invoice';
    }

    public function isFipellB2b(): bool
    {
        return $this->isB2b()
            && (int) $this->ditta_cg18 === 3
            && (int) $this->site_type === 1;
    }

    public function hasCompleteShippingData(): bool
    {
        return filled($this->shipping_address_line_1)
            && filled($this->shipping_postcode)
            && filled($this->shipping_city)
            && filled($this->shipping_country_code)
            && filled($this->shipping_email);
    }

    public function requiresSendcloudShipment(): bool
    {

        return $this->isB2c()

            && (string) $this->shipping_gateway === 'sendcloud'

            && $this->isProcessing()

            && !$this->isClosed()

            && !$this->isCanceled()

            && !$this->isRefunded()

            && blank($this->sendcloudIncomingOrderId())

            && blank($this->sendcloudTrackingNumber())

            && blank($this->sendcloudBarcode())

            && blank($this->sendcloudLabelUrl());

    }

    public function canCreateSendcloudShipment(): bool
    {
        return $this->requiresSendcloudShipment()
            && $this->isPaid()
            && $this->hasCompleteShippingData();
    }

    public function hasTracking(): bool
    {
        return filled($this->sendcloudTrackingNumber());
    }

    public function hasSendcloudShipment(): bool
    {
        return (string) $this->shipping_gateway === 'sendcloud'
            && (
                filled($this->sendcloudIncomingOrderId())
                || filled($this->sendcloudParcelId())
                || filled($this->sendcloudTrackingNumber())
                || filled($this->sendcloudBarcode())
                || filled($this->sendcloudLabelUrl())
            );
    }

    public function sendcloudMeta(): array
    {
        $meta = $this->meta ?? [];

        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        $sendcloud = data_get(is_array($meta) ? $meta : [], 'sendcloud', []);

        return is_array($sendcloud) ? $sendcloud : [];
    }

    public function sendcloudIncomingOrderId(): ?string
    {
        return $this->nullableString(data_get($this->sendcloudMeta(), 'incoming_order_id'));
    }

    public function sendcloudParcelId(): ?string
    {
        return $this->nullableString(data_get($this->sendcloudMeta(), 'parcel_id'));
    }

    public function sendcloudTrackingNumber(): ?string
    {
        return $this->nullableString($this->shipping_tracking_number)
            ?? $this->nullableString(data_get($this->sendcloudMeta(), 'tracking_number'));
    }

    public function sendcloudBarcode(): ?string
    {
        return $this->nullableString(data_get($this->sendcloudMeta(), 'barcode'))
            ?? $this->sendcloudTrackingNumber();
    }

    public function sendcloudLabelUrl(): ?string
    {
        return $this->nullableString($this->shipping_label_url)
            ?? $this->nullableString(data_get($this->sendcloudMeta(), 'label_url'));
    }

    public function sendcloudTrackingUrl(): ?string
    {
        $meta = $this->sendcloudMeta();

        foreach ([
            'tracking_url',
            'parcel_payload.parcel.tracking_url',
            'parcel_payload.tracking_url',
            'webhook_payload.parcel.tracking_url',
            'webhook_payload.tracking_url',
        ] as $path) {
            $url = $this->nullableString(data_get($meta, $path));

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    public function sendcloudError(): ?string
    {
        return $this->nullableString(data_get($this->sendcloudMeta(), 'error'));
    }

    public function sendcloudSkippedReason(): ?string
    {
        return $this->nullableString(data_get($this->sendcloudMeta(), 'skipped_reason'));
    }

    public function sendcloudPendingWebhook(): bool
    {
        return (bool) data_get($this->sendcloudMeta(), 'pending_webhook', false);
    }

    public function sendcloudSyncedAt(): ?string
    {
        return $this->nullableString(data_get($this->sendcloudMeta(), 'synced_at'));
    }

    public function sendcloudUpdatedAt(): ?string
    {
        return $this->nullableString(data_get($this->sendcloudMeta(), 'updated_at'));
    }

    public function sendcloudLastUpdateForDisplay(): ?string
    {
        return $this->sendcloudUpdatedAt() ?? $this->sendcloudSyncedAt();
    }

    public function sendcloudStatusText(): string
    {
        if ((string) $this->shipping_gateway !== 'sendcloud') {
            return '-';
        }

        if (filled($this->sendcloudTrackingNumber())) {
            return (string) $this->sendcloudTrackingNumber();
        }

        if (filled($this->sendcloudBarcode())) {
            return (string) $this->sendcloudBarcode();
        }

        if ($this->sendcloudPendingWebhook()) {
            return 'In attesa webhook';
        }

        if (filled($this->sendcloudError())) {
            return 'Errore Sendcloud';
        }

        if (filled($this->sendcloudSkippedReason())) {
            return 'Saltato';
        }

        if (!$this->isPaid()) {
            return 'Pagamento da incassare';
        }

        if (!$this->isProcessing()) {
            return 'Dopo conferma giacenza';
        }

        if (!$this->hasCompleteShippingData()) {
            return 'Dati spedizione incompleti';
        }

        if ($this->requiresSendcloudShipment()) {
            return 'Da sincronizzare';
        }

        return 'In attesa';
    }

    public function hasErpDocument(): bool
    {
        return filled($this->erp_document_number)
            || filled($this->erp_document_visible_number);
    }

    public function erpDocumentForDisplay(): string
    {
        return (string) ($this->erp_document_visible_number
            ?: $this->erp_document_number
            ?: $this->erp_web_numreg
            ?: '-');
    }

    public function customerNotesForDisplay(): ?string
    {
        return $this->nullableString($this->customer_notes ?? $this->getAttribute('notes') ?? null);
    }

    public function b2cInvoiceMeta(): array
    {
        $meta = $this->meta ?? [];

        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?: [];
        }

        $invoice = data_get(is_array($meta) ? $meta : [], 'b2c_invoice', []);

        return is_array($invoice) ? $invoice : [];
    }

    public function b2cInvoicePec(): ?string
    {
        return $this->nullableString(data_get($this->b2cInvoiceMeta(), 'pec'));
    }

    public function b2cInvoiceSdi(): ?string
    {
        return $this->nullableString(data_get($this->b2cInvoiceMeta(), 'sdi'));
    }

    public function b2cInvoicePecForDisplay(): string
    {
        return $this->b2cInvoicePec() ?: '-';
    }

    public function b2cInvoiceSdiForDisplay(): string
    {
        return $this->b2cInvoiceSdi() ?: '-';
    }

    public function refundData(): array
    {
        return (array) data_get($this->meta ?? [], 'payment.refund', []);
    }

    public function refundId(): ?string
    {
        return $this->nullableString(
            data_get($this->refundData(), 'id')
            ?? data_get($this->refundData(), 'refund_id')
            ?? data_get($this->refundData(), 'transaction_id')
            ?? data_get($this->meta ?? [], 'payment.refund_id')
            ?? data_get($this->meta ?? [], 'payment.refund.id')
            ?? data_get($this->meta ?? [], 'stripe.refund_id')
            ?? data_get($this->meta ?? [], 'stripe.refund.id')
            ?? data_get($this->meta ?? [], 'paypal.refund_id')
            ?? data_get($this->meta ?? [], 'paypal.refund.id')
        );
    }

    public function refundedAt(): ?Carbon
    {
        $value = data_get($this->refundData(), 'refunded_at')
            ?? data_get($this->refundData(), 'created_at')
            ?? data_get($this->refundData(), 'created')
            ?? data_get($this->meta ?? [], 'payment.refunded_at')
            ?? data_get($this->meta ?? [], 'payment.refund.refunded_at')
            ?? data_get($this->meta ?? [], 'stripe.refunded_at')
            ?? data_get($this->meta ?? [], 'paypal.refunded_at');

        if (blank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        return Carbon::parse($value);
    }

    public function refundAmount(): ?float
    {
        $value = data_get($this->refundData(), 'amount')
            ?? data_get($this->refundData(), 'amount_refunded')
            ?? data_get($this->meta ?? [], 'payment.refund_amount')
            ?? data_get($this->meta ?? [], 'payment.refund.amount')
            ?? data_get($this->meta ?? [], 'stripe.refund_amount')
            ?? data_get($this->meta ?? [], 'paypal.refund_amount');

        if ($value === null || $value === '') {
            return null;
        }

        $amount = (float) $value;

        if ((string) $this->payment_gateway === 'stripe' && $amount >= 100) {
            return $amount / 100;
        }

        return $amount;
    }

    public function hasRefundData(): bool
    {
        return $this->isRefunded()
            || filled($this->refundId())
            || $this->refundedAt() !== null
            || $this->refundAmount() !== null;
    }

    public static function orderStatusLabels(): array
    {
        return [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'complete' => 'Completato',
            'closed' => 'Chiuso',
            'canceled' => 'Annullato',
            'cancelled' => 'Annullato',
        ];
    }

    public static function fulfillmentStatusLabels(): array
    {
        return [
            'pending' => 'Da evadere',
            'processing' => 'In lavorazione',
            'shipped' => 'Spedito',
            'complete' => 'Completato',
            'closed' => 'Chiuso',
            'canceled' => 'Annullato',
            'cancelled' => 'Annullato',
        ];
    }

    public static function paymentStatusLabels(): array
    {
        return [
            'not_required' => 'Non richiesto',
            'pending' => 'In attesa',
            'authorized' => 'Autorizzato',
            'paid' => 'Pagato',
            'failed' => 'Fallito',
            'refunded' => 'Rimborsato',
            'canceled' => 'Annullato',
            'cancelled' => 'Annullato',
        ];
    }

    public function orderStatusLabel(): string
    {
        return self::orderStatusLabels()[$this->status]
            ?? strtoupper((string) $this->status);
    }

    public function fulfillmentStatusLabel(): string
    {
        return self::fulfillmentStatusLabels()[$this->fulfillment_status]
            ?? strtoupper((string) $this->fulfillment_status);
    }

    public function paymentStatusLabel(): string
    {
        return self::paymentStatusLabels()[$this->payment_status]
            ?? strtoupper((string) $this->payment_status);
    }

    public function orderStatusBadgeClass(): string
    {
        return match ((string) $this->status) {
            'complete' => 'bg-success',
            'processing' => 'bg-warning text-dark',
            'closed' => 'bg-dark',
            'canceled', 'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function fulfillmentStatusBadgeClass(): string
    {
        return match ((string) $this->fulfillment_status) {
            'complete', 'shipped' => 'bg-success',
            'processing' => 'bg-warning text-dark',
            'closed' => 'bg-dark',
            'canceled', 'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function paymentStatusBadgeClass(): string
    {
        return match ((string) $this->payment_status) {
            'paid' => 'bg-success',
            'authorized' => 'bg-info text-dark',
            'pending' => 'bg-warning text-dark',
            'failed' => 'bg-danger',
            'refunded' => 'bg-secondary',
            'canceled', 'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
