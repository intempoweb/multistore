<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'slug',
        'title',
        'description',
        'template',
        'layout',
        'meta_title',
        'meta_description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(StorefrontPageBlock::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeBlocks(): HasMany
    {
        return $this->hasMany(StorefrontPageBlock::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }
}