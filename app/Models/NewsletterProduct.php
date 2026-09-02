<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterProduct extends Model
{
    protected $fillable = [
        'newsletter_id',
        'product_id',
        'sort_order',
    ];

    protected $casts = [
        'newsletter_id' => 'integer',
        'product_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
