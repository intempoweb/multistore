<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'ditta_cg18',
        'site_type',
        'name',
        'code',
        'type',
        'discount_type',
        'discount_value',
        'scope',
        'minimum_subtotal',
        'conditions',
        'actions',
        'priority',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'ditta_cg18' => 'integer',
        'site_type' => 'integer',
        'discount_value' => 'float',
        'minimum_subtotal' => 'float',
        'conditions' => 'array',
        'actions' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForContext(Builder $query, int $ditta, ?int $siteType = null): Builder
    {
        $query->where('ditta_cg18', $ditta);

        if ($siteType !== null) {
            $query->where('site_type', $siteType);
        }

        return $query;
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('starts_at')
              ->orWhere('starts_at', '<=', now());
        })->where(function ($q) {
            $q->whereNull('ends_at')
              ->orWhere('ends_at', '>=', now());
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('priority')
            ->orderBy('id');
    }

    public function isCurrentlyValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $this->starts_at->gt($now)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->lt($now)) {
            return false;
        }

        return true;
    }

    public function getConditions(): array
    {
        return is_array($this->conditions) ? $this->conditions : [];
    }

    public function getActions(): array
    {
        return is_array($this->actions) ? $this->actions : [];
    }
}