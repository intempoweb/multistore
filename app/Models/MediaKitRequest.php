<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaKitRequest extends Model
{
    public const SOURCE_CATALOG = 'catalog';
    public const SOURCE_UPLOADED_DDT = 'uploaded_ddt';
    public const SOURCE_DOCUMENT = 'document';
    public const SOURCE_ORDER = 'order';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DELETED = 'deleted';

    public const EMAIL_NOT_SENT = 'not_sent';
    public const EMAIL_QUEUED = 'queued';
    public const EMAIL_SENDING = 'sending';
    public const EMAIL_SENT = 'sent';
    public const EMAIL_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'store_id',
        'customer_id',
        'actor_type',
        'actor_id',
        'source_type',
        'source_reference',
        'status',
        'progress',
        'product_count',
        'asset_count',
        'input_disk',
        'input_path',
        'output_disk',
        'output_path',
        'output_filename',
        'output_size',
        'started_at',
        'completed_at',
        'expires_at',
        'downloaded_at',
        'download_count',
        'delete_after',
        'deleted_at',
        'delete_reason',
        'error_message',
        'email_to',
        'email_status',
        'email_sent_at',
        'email_error_message',
        'email_attempts',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'delete_after' => 'datetime',
        'deleted_at' => 'datetime',
        'email_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->deleted_at === null
            && $this->output_disk
            && $this->output_path
            && (!$this->expires_at || $this->expires_at->isFuture());
    }

    public function scopeDueForCleanup(Builder $query): Builder
    {
        return $query
            ->whereNull('deleted_at')
            ->whereNotNull('output_path')
            ->where(function (Builder $due): void {
                $due->where(function (Builder $downloaded): void {
                    $downloaded->whereNotNull('downloaded_at')
                        ->whereNotNull('delete_after')
                        ->where('delete_after', '<=', now());
                })->orWhere(function (Builder $expired): void {
                    $expired->whereNull('downloaded_at')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now());
                });
            });
    }
}
