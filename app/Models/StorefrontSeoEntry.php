<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontSeoEntry extends Model
{
    protected $fillable = [
        'store_id', 'locale', 'entity_type', 'entity_key', 'meta_title',
        'meta_description', 'heading', 'intro', 'canonical_url', 'robots',
        'og_title', 'og_description', 'og_image_path', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
