<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'ditta_cg18',
        'site_type',
        'code',
        'promotion_id',
        'usage_limit',
        'usage_limit_per_customer',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'ditta_cg18' => 'integer',
        'site_type' => 'integer',
        'promotion_id' => 'integer',
        'usage_limit' => 'integer',
        'usage_limit_per_customer' => 'integer',
        'used_count' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(CouponCondition::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForContext(Builder $query, int $ditta, ?int $siteType = null): Builder
    {
        return $query
            ->where('ditta_cg18', $ditta)
            ->when(
                $siteType !== null,
                fn (Builder $q) => $q->where(function (Builder $subQuery) use ($siteType) {
                    $subQuery->whereNull('site_type')
                        ->orWhere('site_type', $siteType);
                })
            );
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', mb_strtoupper(trim($code)));
    }

    public function isCurrentlyValid(): bool
    {
        $now = now();

        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->gt($now)) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->lt($now)) {
            return false;
        }

        if ($this->usage_limit !== null && (int) $this->used_count >= (int) $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function getConditions(): array
    {
        if ($this->relationLoaded('conditions')) {
            return $this->conditions
                ->where('is_active', true)
                ->values()
                ->map(function (CouponCondition $condition) {
                    return [
                        'id' => $condition->id,
                        'type' => $condition->condition_type,
                        'operator' => $condition->operator,
                        'value' => $condition->condition_value,
                        'sort_order' => $condition->sort_order,
                        'is_active' => $condition->is_active,
                    ];
                })
                ->all();
        }

        return $this->conditions()
            ->where('is_active', true)
            ->get()
            ->map(function (CouponCondition $condition) {
                return [
                    'id' => $condition->id,
                    'type' => $condition->condition_type,
                    'operator' => $condition->operator,
                    'value' => $condition->condition_value,
                    'sort_order' => $condition->sort_order,
                    'is_active' => $condition->is_active,
                ];
            })
            ->all();
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}