<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductComparison extends Model
{
    use HasFactory;

    protected $fillable = [
        'ditta_cg18',
        'site_type',
        'sku',
        'source',
        'comparison_sku',
        'erp_lastchange',
    ];

    protected $casts = [
        'ditta_cg18' => 'integer',
        'site_type' => 'integer',
        'erp_lastchange' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'sku', 'sku')
            ->whereColumn('products.ditta_cg18', 'product_comparisons.ditta_cg18')
            ->whereColumn('products.site_type', 'product_comparisons.site_type');
    }
}
