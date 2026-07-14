<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSupportTicket extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'ticket_number',
        'customer_id',
        'store_id',
        'ditta_cg18',
        'clifor_cg44',
        'numreg_co99',
        'document_number',
        'document_type',
        'document_date',
        'status',
        'subject',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address_line',
        'city',
        'postcode',
        'province',
        'message',
        'terms_accepted_at',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'store_id' => 'integer',
        'ditta_cg18' => 'integer',
        'clifor_cg44' => 'integer',
        'terms_accepted_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerSupportTicketItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CustomerRequestAttachment::class, 'request_id')
            ->where('request_type', CustomerRequestAttachment::TYPE_SUPPORT_TICKET);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_IN_REVIEW => 'In lavorazione',
            self::STATUS_CLOSED => 'Chiuso',
            default => 'Aperto',
        };
    }
}
