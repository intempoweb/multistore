<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReturnItem extends Model
{
    protected $fillable = [
        'customer_return_id',
        'erp_row_number',
        'sku',
        'description',
        'unit',
        'document_quantity',
        'requested_quantity',
        'reason',
        'notes',
    ];

    protected $casts = [
        'customer_return_id' => 'integer',
        'document_quantity' => 'decimal:3',
        'requested_quantity' => 'decimal:3',
    ];

    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }
}
