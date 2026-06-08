<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ConfigurableProduct extends Model
{
    protected $table = 'configurable_products';

    protected $fillable = [
        'ditta_cg18',
        'site_type',
        'parent_code',
        'photo',
        'dataultimoagg',
        'erp_lastchange',
    ];

    protected $casts = [
        'ditta_cg18'     => 'integer',
        'site_type'      => 'integer',
        'dataultimoagg'  => 'date',
        'erp_lastchange' => 'datetime',
    ];

    /**
     * Product configurabile (products.sku = configurable_products.parent_code)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_code', 'sku')
            ->where('type', 'configurable')
            ->where('ditta_cg18', (int) $this->ditta_cg18)
            ->where('site_type', (int) $this->site_type);
    }

    /**
     * Figli semplici (products.parent_code = configurable_products.parent_code)
     */
    public function children(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_code', 'parent_code')
            ->where('type', 'simple')
            ->where('ditta_cg18', (int) $this->ditta_cg18)
            ->where('site_type', (int) $this->site_type);
    }

    public function mediaAssets(): MorphMany
    {
        return $this->morphMany(MediaAsset::class, 'mediable')
            ->orderBy('sort_order');
    }

    public function mainImage(): ?MediaAsset
    {
        return $this->mediaAssets()
            ->where('role', MediaAsset::ROLE_MAIN)
            ->orderBy('sort_order')
            ->first();
    }

    public function galleryImages(): MorphMany
    {
        return $this->mediaAssets()
            ->where('role', MediaAsset::ROLE_GALLERY)
            ->orderBy('sort_order');
    }

    public function scopeForContext(Builder $query, int $ditta, int $siteType): Builder
    {
        return $query->where('ditta_cg18', $ditta)->where('site_type', $siteType);
    }
}