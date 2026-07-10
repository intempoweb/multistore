<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StorefrontPageBlockTranslation extends Model
{
    protected $fillable = [
        'storefront_page_block_id',
        'locale',
        'title',
        'subtitle',
        'content',
        'button_label',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(StorefrontPageBlock::class, 'storefront_page_block_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $translation) {
            $translation->locale = Str::lower(trim((string) $translation->locale));
            $translation->title = self::cleanString($translation->title);
            $translation->subtitle = self::cleanString($translation->subtitle);
            $translation->content = self::cleanString($translation->content);
            $translation->button_label = self::cleanString($translation->button_label);
        });
    }

    private static function cleanString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
