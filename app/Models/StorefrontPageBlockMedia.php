<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontPageBlockMedia extends Model
{
    protected $table = 'storefront_page_block_media';

    protected $fillable = [
        'storefront_page_block_id',
        'media_type',
        'desktop_path',
        'mobile_path',
        'poster_path',
        'alt_text',
        'sort_order',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(StorefrontPageBlock::class, 'storefront_page_block_id');
    }
}
