<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'ditta_cg18',
        'site_type',
        'erp_web_row_id',
        'erp_web_numreg',
        'erp_web_row_number',
        'erp_row_type',
        'product_id',
        'sku',
        'product_name',
        'product_description',
        'product_thumbnail_url',
        'variant_attributes',
        'quantity',
        'min_qty',
        'step_qty',
        'price_source',
        'price',
        'price_net',
        'price_gross',
        'erp_price',
        'erp_price_tax',
        'erp_price_gross',
        'listino_id',
        'qty_from',
        'qty_to',
        'sc1',
        'sc2',
        'sc3',
        'sc4',
        'sc5',
        'sc6',
        'tax_percent',
        'tax_code',
        'tax_label',
        'row_subtotal',
        'row_discount_total',
        'row_tax_total',
        'row_total',
        'erp_row_subtotal',
        'erp_row_tax_total',
        'erp_row_net_total',
        'erp_row_cash_total',
        'price_payload',
        'stock_qty',
        'no_backorder',
        'meta',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'ditta_cg18' => 'integer',
        'site_type' => 'integer',
        'erp_web_row_id' => 'integer',
        'erp_web_numreg' => 'string',
        'erp_web_row_number' => 'integer',
        'erp_row_type' => 'integer',
        'product_id' => 'integer',
        'listino_id' => 'integer',

        'variant_attributes' => 'array',
        'price_payload' => 'array',
        'meta' => 'array',

        'quantity' => 'decimal:3',
        'min_qty' => 'decimal:3',
        'step_qty' => 'decimal:3',
        'price' => 'decimal:3',
        'price_net' => 'decimal:3',
        'price_gross' => 'decimal:3',
        'erp_price' => 'decimal:6',
        'erp_price_tax' => 'decimal:6',
        'erp_price_gross' => 'decimal:6',
        'qty_from' => 'decimal:3',
        'qty_to' => 'decimal:3',
        'sc1' => 'decimal:3',
        'sc2' => 'decimal:3',
        'sc3' => 'decimal:3',
        'sc4' => 'decimal:3',
        'sc5' => 'decimal:3',
        'sc6' => 'decimal:3',
        'tax_percent' => 'decimal:3',
        'row_subtotal' => 'decimal:3',
        'row_discount_total' => 'decimal:3',
        'row_tax_total' => 'decimal:3',
        'row_total' => 'decimal:3',
        'erp_row_subtotal' => 'decimal:3',
        'erp_row_tax_total' => 'decimal:3',
        'erp_row_net_total' => 'decimal:3',
        'erp_row_cash_total' => 'decimal:2',
        'stock_qty' => 'decimal:3',
        'no_backorder' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function scopeForOrder(Builder $query, int $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeForSku(Builder $query, string $sku): Builder
    {
        return $query->where('sku', trim($sku));
    }

    public function scopeForContext(Builder $query, int $ditta, ?int $siteType = null): Builder
    {
        $query->where('ditta_cg18', $ditta);

        if ($siteType !== null) {
            $query->where('site_type', $siteType);
        }

        return $query;
    }

    public function scopeForErpWebNumreg(Builder $query, string|int $numreg): Builder
    {
        return $query->where('erp_web_numreg', trim((string) $numreg));
    }

    public function scopeForErpWebRowId(Builder $query, int $rowId): Builder
    {
        return $query->where('erp_web_row_id', $rowId);
    }

    public function scopeOrderedRows(Builder $query): Builder
    {
        return $query->orderBy('erp_web_row_number')->orderBy('id');
    }

    public function scopeProductRows(Builder $query): Builder
    {
        return $query->whereNotNull('sku');
    }

    public function hasErpWebRow(): bool
    {
        return filled($this->erp_web_row_id)
            || filled($this->erp_web_numreg)
            || filled($this->erp_web_row_number);
    }

    public function erpRowNumberForDisplay(): ?int
    {
        if ($this->erp_web_row_number !== null) {
            return (int) $this->erp_web_row_number;
        }

        return null;
    }

    public function hasDiscounts(): bool
    {
        return (float) ($this->row_discount_total ?? 0) > 0
            || collect([
                $this->sc1,
                $this->sc2,
                $this->sc3,
                $this->sc4,
                $this->sc5,
                $this->sc6,
            ])->filter(fn ($value) => $value !== null && (float) $value > 0)->isNotEmpty();
    }

    public function hasStockSnapshot(): bool
    {
        return $this->stock_qty !== null;
    }

    public function isBackorderBlocked(): bool
    {
        return (bool) $this->no_backorder;
    }

    public function isAvailableInStock(): bool
    {
        if ($this->stock_qty === null) {
            return true;
        }

        return (float) $this->stock_qty >= (float) $this->quantity;
    }

    public function rowNetTotal(): float
    {
        return (float) ($this->row_subtotal ?? 0) - (float) ($this->row_discount_total ?? 0);
    }

    public function rowGrossTotal(): float
    {
        return (float) ($this->row_total ?? $this->rowNetTotal());
    }

    public function erpNetTotal(): float
    {
        return (float) ($this->erp_row_net_total ?? $this->erp_row_subtotal ?? $this->rowNetTotal());
    }

    public function erpGrossTotal(): float
    {
        return (float) ($this->erp_row_cash_total ?? $this->erp_row_net_total ?? $this->rowGrossTotal());
    }
}