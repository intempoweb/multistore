<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class ProductAttributeValue extends Model
{
    protected $table = 'product_attribute_values';

    protected $fillable = [
        'product_id',
        'attribute_id',
        'attribute_value_id',
        'raw_value',
        'value_key',       // la ricalcoliamo in saving
        'erp_lastchange',
    ];

    protected $casts = [
        'product_id'         => 'integer',
        'attribute_id'       => 'integer',
        'attribute_value_id' => 'integer',
        'raw_value'          => 'string',
        'erp_lastchange'     => 'datetime',
    ];

    public static function makeValueKey(?int $attributeValueId, ?string $rawValue): string
    {
        if ($attributeValueId) {
            return 'v:' . $attributeValueId;
        }

        $raw = trim((string) ($rawValue ?? ''));
        return 'r:' . $raw;
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {

            // normalize raw_value => NULL if empty
            if ($model->raw_value !== null) {
                $model->raw_value = trim((string) $model->raw_value);
                if ($model->raw_value === '') {
                    $model->raw_value = null;
                }
            }

            // if select => raw_value must be NULL
            if (!empty($model->attribute_value_id)) {
                $model->raw_value = null;
            }

            // guard: must have one of them
            if (empty($model->attribute_value_id) && $model->raw_value === null) {
                throw new InvalidArgumentException(
                    'ProductAttributeValue non valido: serve attribute_value_id oppure raw_value.'
                );
            }

            // value_key always computed
            $model->value_key = self::makeValueKey(
                $model->attribute_value_id ? (int) $model->attribute_value_id : null,
                $model->raw_value
            );
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, 'attribute_value_id');
    }
}