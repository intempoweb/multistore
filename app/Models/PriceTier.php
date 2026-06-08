<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PriceTier extends Model
{
    protected $table = 'price_tiers';

    protected $fillable = [
        'ditta_cg18',
        'listino_id',
        'sku',
        'qty_from',
        'qty_to',
        'price_net',
        'sc1',
        'sc2',
        'sc3',
        'sc4',
        'sc5',
        'sc6',
        'erp_lastchange',
        'erp_last_seen_at',
    ];

    protected $casts = [
        'ditta_cg18'       => 'integer',
        'listino_id'       => 'integer',
        'qty_from'         => 'decimal:3',
        'qty_to'           => 'decimal:3',
        'price_net'        => 'decimal:6',
        'sc1'              => 'decimal:3',
        'sc2'              => 'decimal:3',
        'sc3'              => 'decimal:3',
        'sc4'              => 'decimal:3',
        'sc5'              => 'decimal:3',
        'sc6'              => 'decimal:3',
        'erp_lastchange'   => 'date',
        'erp_last_seen_at' => 'datetime',
    ];

    public function scopeForProduct(Builder $query, int $ditta, int $listinoId, string $sku): Builder
    {
        return $query->where('ditta_cg18', $ditta)
            ->where('listino_id', $listinoId)
            ->where('sku', trim($sku));
    }

    public function scopeForQuantity(Builder $query, float|int $qty): Builder
    {
        return $query->where('qty_from', '<=', $qty)
            ->where(function ($sub) use ($qty) {
                $sub->whereNull('qty_to')
                    ->orWhere('qty_to', 0)
                    ->orWhere('qty_to', '>=', $qty);
            })
            ->orderByDesc('qty_from');
    }
}