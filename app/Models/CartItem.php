<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected array $viewOnlyAttributes = [];

    protected array $viewOnlyAttributeKeys = [
        'quantity_min',
        'quantity_step',
        'pack_multiple',
        'show_pack_multiple',
        'product_url',
    ];

    protected $fillable = [
        'cart_id',
        'ditta_cg18',
        'site_type',
        'product_id',
        'sku',
        'product_name',
        'product_description',
        'product_thumbnail_url',
        'quantity',
        'price',
        'price_net',
        'price_gross',
        'base_price',
        'base_row_total',
        'web_discount_total',
        'final_price',
        'final_row_total',
        'listino_id',
        'qty_from',
        'qty_to',
        'sc1',
        'sc2',
        'sc3',
        'sc4',
        'sc5',
        'sc6',
        'row_subtotal',
        'row_discount_total',
        'row_tax_total',
        'row_total',
        'stock_qty',
        'no_backorder',
    ];

    protected $casts = [
        'cart_id' => 'integer',
        'ditta_cg18' => 'integer',
        'site_type' => 'integer',
        'product_id' => 'integer',
        'listino_id' => 'integer',

        'quantity' => 'decimal:3',
        'price' => 'decimal:3',
        'price_net' => 'decimal:3',
        'price_gross' => 'decimal:3',
        'base_price' => 'decimal:3',
        'base_row_total' => 'decimal:3',
        'web_discount_total' => 'decimal:3',
        'final_price' => 'decimal:3',
        'final_row_total' => 'decimal:3',
        'qty_from' => 'decimal:3',
        'qty_to' => 'decimal:3',

        'sc1' => 'decimal:3',
        'sc2' => 'decimal:3',
        'sc3' => 'decimal:3',
        'sc4' => 'decimal:3',
        'sc5' => 'decimal:3',
        'sc6' => 'decimal:3',
        'row_subtotal' => 'decimal:3',
        'row_discount_total' => 'decimal:3',
        'row_tax_total' => 'decimal:3',

        'row_total' => 'decimal:3',
        'stock_qty' => 'decimal:3',
        'no_backorder' => 'boolean',
    ];

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->viewOnlyAttributeKeys, true)) {
            $this->viewOnlyAttributes[$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        if (in_array($key, $this->viewOnlyAttributeKeys, true)) {
            return $this->viewOnlyAttributes[$key] ?? null;
        }

        return parent::getAttribute($key);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'sku', 'sku')
            ->whereColumn('products.ditta_cg18', 'cart_items.ditta_cg18');
    }

    public function scopeForCart(Builder $query, int $cartId): Builder
    {
        return $query->where('cart_id', $cartId);
    }

    public function scopeForSku(Builder $query, string $sku): Builder
    {
        return $query->where('sku', trim($sku));
    }
}