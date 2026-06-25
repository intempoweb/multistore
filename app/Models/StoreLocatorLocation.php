<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreLocatorLocation extends Model
{
    protected $fillable = [
        'store_id',
        'customer_id',
        'customer_shipping_address_id',
        'source_type',
        'source_key',
        'latitude',
        'longitude',
        'geocoded_at',
        'geocode_status',
        'geocode_error',
        'address_fingerprint',
        'is_active',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'customer_id' => 'integer',
        'customer_shipping_address_id' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'geocoded_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForStore(Builder $query, Store|int $store): Builder
    {
        return $query->where('store_id', $store instanceof Store ? (int) $store->id : (int) $store);
    }

    public function scopeGeocoded(Builder $query): Builder
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerShippingAddress::class, 'customer_shipping_address_id');
    }

    public function sourceName(): string
    {
        if ($this->shippingAddress) {
            return $this->shippingAddress->display_name;
        }

        return trim((string) ($this->customer?->ragsoanag_cg16 ?? '')) ?: 'Punto vendita';
    }

    public function sourceAddressParts(): array
    {
        if ($this->shippingAddress) {
            return [
                'address' => $this->shippingAddress->destind_mg22,
                'postcode' => $this->shippingAddress->destcap_mg22,
                'city' => $this->shippingAddress->destcitta_mg22,
                'province' => $this->shippingAddress->destprov_mg22,
                'phone' => $this->shippingAddress->desttel_mg22 ?: $this->shippingAddress->destcell_mg22,
                'email' => $this->shippingAddress->destemail_mg22,
            ];
        }

        $customer = $this->customer;
        $hasCorrespondenceAddress = filled($customer?->indircor_cg16)
            || filled($customer?->capcor_cg16)
            || filled($customer?->cittacor_cg16)
            || filled($customer?->provcor_cg16);

        return [
            'address' => $hasCorrespondenceAddress ? $customer?->indircor_cg16 : $customer?->indirizzo_cg16,
            'postcode' => $hasCorrespondenceAddress ? $customer?->capcor_cg16 : $customer?->cap_cg16,
            'city' => $hasCorrespondenceAddress ? $customer?->cittacor_cg16 : $customer?->citta_cg16,
            'province' => $hasCorrespondenceAddress ? $customer?->provcor_cg16 : $customer?->prov_cg16,
            'phone' => $customer?->tel1num_cg16 ?: $customer?->cellnum_cg16,
            'email' => $customer?->indemail_cg16,
        ];
    }

    public function sourceAddressLine(): string
    {
        $parts = $this->sourceAddressParts();

        return trim(collect([
            $parts['address'] ?? null,
            collect([$parts['postcode'] ?? null, $parts['city'] ?? null, $parts['province'] ?? null])
                ->filter(fn ($value) => filled($value))
                ->implode(' '),
        ])->filter(fn ($value) => filled($value))->implode(', '));
    }
}
