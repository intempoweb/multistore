<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerWishlistItem extends Model
{
    protected $table = 'customer_wishlist_items';

    protected $fillable = [
        'customer_id',
        'store_id',
        'product_id',
        'ditta_cg18',
        'site_type',
        'sku',
        'meta',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'store_id' => 'integer',
        'product_id' => 'integer',
        'ditta_cg18' => 'integer',
        'site_type' => 'integer',
        'meta' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)
            ->with([
                'translations',
                'mediaAssets',
            ]);
    }

    public function primaryImage(): ?MediaAsset
    {
        $product = $this->product;

        if (!$product instanceof Product) {
            return null;
        }

        return $product->mediaAssets
            ->firstWhere('role', MediaAsset::ROLE_MAIN)
            ?? $product->mediaAssets->first();
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForContext(
        Builder $query,
        int $ditta,
        ?int $siteType = null
    ): Builder {
        $query->where('ditta_cg18', $ditta);

        if ($siteType !== null) {
            $query->where('site_type', $siteType);
        }

        return $query;
    }

    public function scopeForSku(Builder $query, string $sku): Builder
    {
        return $query->where('sku', trim($sku));
    }
}
