<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTranslation extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'product_id',
        'locale',
        'name',
        'description',
        'short_description',
        'seo_title',
        'seo_description',
        'erp_lastchange',
    ];

    protected $casts = [
        'product_id'     => 'integer',
        'locale'         => 'string',
        'erp_lastchange' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // normalize locale
            $model->locale = trim((string) $model->locale);

            // normalize strings (empty => null)
            foreach (['name','description','short_description','seo_title','seo_description'] as $f) {
                if ($model->{$f} !== null) {
                    $v = trim((string) $model->{$f});
                    $model->{$f} = ($v === '') ? null : $v;
                }
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function scopeLocale(Builder $q, string $locale): Builder
    {
        return $q->where('locale', trim($locale));
    }
}