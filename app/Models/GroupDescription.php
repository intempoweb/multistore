<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GroupDescription extends Model
{
    protected $table = 'group_descriptions';

    protected $fillable = [
        'ditta_cg18',
        'site_type',
        'locale',
        'fam_code',
        'sfam_code',
        'gruppo_code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'ditta_cg18' => 'integer',
        'site_type' => 'integer',
        'locale' => 'string',
        'fam_code' => 'string',
        'sfam_code' => 'string',
        'gruppo_code' => 'string',
        'description' => 'string',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForContext(Builder $query, int $ditta, int $siteType): Builder
    {
        return $query
            ->where('ditta_cg18', $ditta)
            ->where('site_type', $siteType);
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', trim($locale));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Catalog level helpers
    |--------------------------------------------------------------------------
    */

    public function scopeFamiglie(Builder $query): Builder
    {
        return $query
            ->whereNull('sfam_code')
            ->whereNull('gruppo_code');
    }

    public function scopeSottofamiglie(Builder $query): Builder
    {
        return $query
            ->whereNotNull('sfam_code')
            ->whereNull('gruppo_code');
    }

    public function scopeGruppi(Builder $query): Builder
    {
        return $query->whereNotNull('gruppo_code');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isFamiglia(): bool
    {
        return $this->sfam_code === null && $this->gruppo_code === null;
    }

    public function isSottofamiglia(): bool
    {
        return $this->sfam_code !== null && $this->gruppo_code === null;
    }

    public function isGruppo(): bool
    {
        return $this->gruppo_code !== null;
    }
}