<?php

namespace App\Models\Erp;

use App\Models\MediaAsset;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRow extends Model
{
    protected $connection = 'erp';
    protected $table = 'DOCCORPOBASE_DO30';

    public $incrementing = false;
    public $timestamps = false;

    protected $guarded = [];

    public function header(): BelongsTo
    {
        return $this->belongsTo(DocumentHeader::class, 'NUMREG_CO99', 'NUMREG_CO99');
    }

    public function scopeForDocument(Builder $query, string|int $numreg): Builder
    {
        return $query->where('NUMREG_CO99', $numreg);
    }

    public function scopeForSku(Builder $query, string $sku): Builder
    {
        return $query->where('CODART_MG66', trim($sku));
    }

    public function scopeProductRows(Builder $query): Builder
    {
        return $query
            ->whereNotNull('CODART_MG66')
            ->where('CODART_MG66', '<>', '');
    }

    public function isProductRow(): bool
    {
        return trim((string) ($this->CODART_MG66 ?? '')) !== '';
    }

    public function skuForDisplay(): string
    {
        return trim((string) ($this->CODART_MG66 ?? '')) ?: '-';
    }

    public function descriptionForDisplay(): string
    {
        return trim((string) ($this->DESCART_DO30 ?? '')) ?: '-';
    }

    public function attachedProduct(): ?Product
    {
        $product = $this->getAttribute('document_product');

        return $product instanceof Product ? $product : null;
    }

    public function mainMediaAsset(): ?MediaAsset
    {
        $product = $this->attachedProduct();

        if (!$product instanceof Product) {
            return null;
        }

        $assets = $product->relationLoaded('mediaAssets')
            ? $product->mediaAssets
            : collect();

        return $assets->firstWhere('role', MediaAsset::ROLE_MAIN)
            ?? $assets->first();
    }

    public function thumbnailUrl(): ?string
    {
        return $this->mainMediaAsset()?->url;
    }

    public function mainMediaFilename(): ?string
    {
        $asset = $this->mainMediaAsset();

        if (!$asset instanceof MediaAsset) {
            return null;
        }

        return trim((string) ($asset->filename ?: basename((string) $asset->local_path))) ?: null;
    }
}
