<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerShippingAddress extends Model
{
    protected $table = 'customer_shipping_addresses';

    protected $fillable = [
        'ditta_cg18',
        'tipocf_cg44',
        'clifor_cg44',
        'coddestin_mg22',

        'destragsoc_mg22',
        'destind_mg22',
        'destcap_mg22',
        'destcitta_mg22',
        'destprov_mg22',

        'desttel_mg22',
        'destcell_mg22',
        'destemail_mg22',
        'destfax_mg22',

        'destnote_mg22',
        'aliqrid_cg28',
        'statoest_cg07',
        'vett1_mg14',

        'erp_lastchange',
        'erp_last_seen_at',
        'is_active',
    ];

    protected $casts = [
        'ditta_cg18' => 'integer',
        'tipocf_cg44' => 'integer',
        'clifor_cg44' => 'integer',
        'coddestin_mg22' => 'integer',
        'statoest_cg07' => 'integer',

        'erp_lastchange' => 'date',
        'erp_last_seen_at' => 'datetime',

        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCustomer(Builder $query, int $ditta, int $tipoCf, int $clifor): Builder
    {
        return $query
            ->where('ditta_cg18', $ditta)
            ->where('tipocf_cg44', $tipoCf)
            ->where('clifor_cg44', $clifor);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'clifor_cg44', 'clifor_cg44')
            ->whereColumn('customers.ditta_cg18', 'customer_shipping_addresses.ditta_cg18')
            ->whereColumn('customers.tipocf_cg44', 'customer_shipping_addresses.tipocf_cg44');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->destragsoc_mg22
            ?: trim(implode(' ', array_filter([
                $this->destind_mg22,
                $this->destcap_mg22,
                $this->destcitta_mg22,
                $this->destprov_mg22,
            ])))
            ?: 'Destinazione ' . $this->coddestin_mg22;
    }
}