<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerListinoAssignment extends Model
{
    protected $table = 'customer_listino_assignments';

    /**
     * Mass assignment
     */
    protected $fillable = [
        'ditta_cg18',
        'clifor_cg44',
        'listino_id',
        'is_active',
        'erp_last_seen_at',
    ];

    protected $casts = [
        'ditta_cg18'       => 'integer',
        'clifor_cg44'      => 'integer',
        'listino_id'       => 'integer',
        'is_active'        => 'boolean',
        'erp_last_seen_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDitta(Builder $query, int $ditta): Builder
    {
        return $query->where('ditta_cg18', $ditta);
    }

    public function scopeForCustomer(Builder $query, int $ditta, int $clifor): Builder
    {
        return $query->where('ditta_cg18', $ditta)
            ->where('clifor_cg44', $clifor);
    }

    public function scopeForListino(Builder $query, int $ditta, int $listinoId): Builder
    {
        return $query->where('ditta_cg18', $ditta)
            ->where('listino_id', $listinoId);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'clifor_cg44', 'clifor_cg44');
    }
}