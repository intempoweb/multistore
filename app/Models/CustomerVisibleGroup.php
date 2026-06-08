<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerVisibleGroup extends Model
{
    protected $table = 'customer_visible_groups';

    protected $fillable = [
        // ERP key / contesto
        'ditta_cg18',
        'flg_b2b_b2c_webt81',
        'tipocf_cg44',
        'clifor_cg44',

        // Gruppo fisico
        'codice_xx32',
        'descrizione_xx32',

        // ERP flags / date
        'flgattivo_xx32',
        'dataultimoagg_xx32',

        // Local watermarks
        'is_active',
        'erp_last_seen_at',
    ];

    protected $casts = [
        'ditta_cg18' => 'integer',
        'tipocf_cg44' => 'integer',
        'clifor_cg44' => 'integer',
        'flgattivo_xx32' => 'integer',
        'dataultimoagg_xx32' => 'date',

        'is_active' => 'boolean',
        'erp_last_seen_at' => 'datetime',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForCustomer(Builder $q, int $ditta, int $tipoCf, int $clifor): Builder
    {
        return $q->where('ditta_cg18', $ditta)
            ->where('tipocf_cg44', $tipoCf)
            ->where('clifor_cg44', $clifor);
    }

    public function scopeForGroup(Builder $q, int $ditta, string $groupCode, ?string $siteFlag = null): Builder
    {
        $q->where('ditta_cg18', $ditta)
          ->where('codice_xx32', $groupCode);

        if ($siteFlag !== null) {
            $q->where('flg_b2b_b2c_webt81', $siteFlag);
        }

        return $q;
    }
}