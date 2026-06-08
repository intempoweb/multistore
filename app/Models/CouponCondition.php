<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponCondition extends Model
{
    protected $fillable = [
        'coupon_id',
        'condition_type',
        'operator',
        'condition_value',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'coupon_id' => 'integer',
        'condition_value' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getNormalizedValue(): mixed
    {
        return $this->condition_value;
    }
}