<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CustomerRequestAttachment extends Model
{
    public const TYPE_RETURN = 'return';
    public const TYPE_SUPPORT_TICKET = 'support_ticket';

    protected $fillable = [
        'request_type',
        'request_id',
        'customer_id',
        'store_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'request_id' => 'integer',
        'customer_id' => 'integer',
        'store_id' => 'integer',
        'size' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (CustomerRequestAttachment $attachment): void {
            if ($attachment->disk && $attachment->path) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
