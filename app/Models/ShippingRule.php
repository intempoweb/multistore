<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ShippingRule extends Model
{
    protected $fillable = [
        'store_id',

        // contesto ERP fallback
        'ditta_cg18',
        'erp_site_code',

        // tipo regola
        'type',

        // regole economiche
        'min_amount',
        'max_amount',

        // regole geografiche / table rate B2C
        'country',
        'province',
        'cap',
        'weight_from',

        // valore
        'amount',

        // ordinamento / stato
        'priority',
        'is_active',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'ditta_cg18' => 'integer',
        'erp_site_code' => 'integer',

        'min_amount' => 'decimal:3',
        'max_amount' => 'decimal:3',
        'weight_from' => 'decimal:3',
        'amount' => 'decimal:3',

        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(
            Store::class,
            'shipping_rule_store',
            'shipping_rule_id',
            'store_id'
        )->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForStore(Builder $query, ?Store $store): Builder
    {
        if (!$store instanceof Store) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($store) {
            $q->where('store_id', $store->id)
                ->orWhereHas('stores', function (Builder $pivotQuery) use ($store) {
                    $pivotQuery->where('stores.id', $store->id);
                })
                ->orWhere(function (Builder $q2) use ($store) {
                    $q2->whereNull('store_id')
                        ->where('ditta_cg18', (int) $store->ditta_cg18)
                        ->where('erp_site_code', (int) $store->erp_site_code);
                });
        });
    }

    public function scopeForB2cTableRate(
        Builder $query,
        ?Store $store,
        ?string $country = null,
        ?string $province = null,
        ?string $cap = null
    ): Builder {
        $country = self::normalizeNullableString($country, true);
        $province = self::normalizeNullableString($province, true);
        $cap = self::normalizeNullableString($cap, true);

        return $query
            ->forStore($store)
            ->active()
            ->where('type', 'table')
            ->where(function (Builder $q) use ($country) {
                if ($country !== null) {
                    $q->where('country', $country)
                        ->orWhereNull('country');
                    return;
                }

                $q->whereNull('country');
            })
            ->where(function (Builder $q) use ($province) {
                if ($province !== null) {
                    $q->where('province', $province)
                        ->orWhereNull('province');
                    return;
                }

                $q->whereNull('province');
            })
            ->where(function (Builder $q) use ($cap) {
                if ($cap !== null) {
                    $q->where('cap', $cap)
                        ->orWhere('cap', 'like', preg_replace('/\*+$/', '', $cap) . '%')
                        ->orWhereNull('cap');
                    return;
                }

                $q->whereNull('cap');
            });
    }

    public static function normalizeCountry(?string $value): ?string
    {
        return self::normalizeNullableString($value, true);
    }

    public static function normalizeProvince(?string $value): ?string
    {
        return self::normalizeNullableString($value, true);
    }

    public static function normalizeCap(?string $value): ?string
    {
        return self::normalizeNullableString($value, true);
    }

    protected static function normalizeNullableString(?string $value, bool $uppercase = false): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $normalized = $uppercase ? strtoupper($value) : $value;
        $upper = strtoupper($normalized);

        if (in_array($upper, ['ALL', '*', '•', '-', '--'], true)) {
            return null;
        }

        return $normalized;
    }
}