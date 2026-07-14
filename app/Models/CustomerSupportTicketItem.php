<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSupportTicketItem extends Model
{
    protected $fillable = [
        'customer_support_ticket_id',
        'erp_row_number',
        'sku',
        'description',
        'unit',
        'document_quantity',
    ];

    protected $casts = [
        'customer_support_ticket_id' => 'integer',
        'document_quantity' => 'decimal:3',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(CustomerSupportTicket::class, 'customer_support_ticket_id');
    }
}
