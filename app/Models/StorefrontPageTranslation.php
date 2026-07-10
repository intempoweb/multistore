<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StorefrontPageTranslation extends Model
{
    protected $fillable = [
        'storefront_page_id',
        'store_id',
        'locale',
        'slug',
        'title',
        'description',
        'meta_title',
        'meta_description',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(StorefrontPage::class, 'storefront_page_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $translation) {
            $translation->locale = Str::lower(trim((string) $translation->locale));
            $translation->slug = self::cleanString($translation->slug);
            $translation->title = self::cleanString($translation->title);
            $translation->description = self::cleanString($translation->description);
            $translation->meta_title = self::cleanString($translation->meta_title);
            $translation->meta_description = self::cleanString($translation->meta_description);
        });
    }

    private static function cleanString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
