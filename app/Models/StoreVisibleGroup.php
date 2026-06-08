<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StoreVisibleGroup extends Model
{
    protected $table = 'store_visible_groups';

    protected $fillable = [
        'ditta_cg18',
        'site_type',
        'codice_xx32',
        'descrizione_xx32',
        'erp_last_seen_at',
    ];

    protected $casts = [
        'ditta_cg18'       => 'integer',
        'site_type'        => 'integer',
        'erp_last_seen_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForContext(Builder $q, int $ditta, int $siteType): Builder
    {
        return $q->where('ditta_cg18', $ditta)
                 ->where('site_type', $siteType);
    }

    public function scopeForGroup(Builder $q, int $ditta, int $siteType, string $groupCode): Builder
    {
        return $q->where('ditta_cg18', $ditta)
                 ->where('site_type', $siteType)
                 ->where('codice_xx32', $groupCode);
    }
}