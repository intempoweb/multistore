<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionCondition extends Model
{
    protected $fillable = [
        'promotion_id',
        'condition_type',
        'operator',
        'condition_value',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'promotion_id' => 'integer',
        'condition_value' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getNormalizedValue(): mixed
    {
        return $this->condition_value;
    }
}