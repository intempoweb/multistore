<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StorefrontPopupTranslation extends Model
{
    protected $fillable = [
        'storefront_popup_id',
        'locale',
        'title',
        'subtitle',
        'body',
        'cta_label',
    ];

    public function popup(): BelongsTo
    {
        return $this->belongsTo(StorefrontPopup::class, 'storefront_popup_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $translation) {
            $translation->locale = Str::lower(trim((string) $translation->locale));
        });
    }
}
