<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontPageBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'storefront_page_id',
        'type',
        'name',
        'sort_order',
        'is_active',
        'title',
        'subtitle',
        'content',
        'image_path',
        'mobile_image_path',
        'video_path',
        'button_label',
        'button_url',
        'button_new_tab',
        'background_color',
        'text_color',
        'overlay_color',
        'overlay_opacity',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'button_new_tab' => 'boolean',
        'overlay_opacity' => 'integer',
        'sort_order' => 'integer',
        'settings' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(StorefrontPage::class, 'storefront_page_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
