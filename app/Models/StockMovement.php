<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'product_id',
        'ditta_cg18',
        'site_type',
        'sku',
        'type',
        'qty_delta',
        'stock_before',
        'stock_after',
        'meta',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'order_item_id' => 'integer',
        'product_id' => 'integer',
        'ditta_cg18' => 'integer',
        'site_type' => 'integer',
        'qty_delta' => 'decimal:3',
        'stock_before' => 'decimal:3',
        'stock_after' => 'decimal:3',
        'meta' => 'array',
    ];
}