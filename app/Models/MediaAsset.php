<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class MediaAsset extends Model
{
    public const ROLE_MAIN    = 'main';
    public const ROLE_GALLERY = 'gallery';
    public const ROLE_SWATCH  = 'swatch';
    public const ROLE_ICON    = 'icon';
    public const ROLE_PDF     = 'pdf';

    public const ALLOWED_ROLES = [
        self::ROLE_MAIN,
        self::ROLE_GALLERY,
        self::ROLE_SWATCH,
        self::ROLE_ICON,
        self::ROLE_PDF,
    ];

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'ditta_cg18',
        'site_type',
        'role',
        'sort_order',
        'erp_path',
        'filename',
        'local_path',
        'meta_key',
        'meta_value',
        'erp_lastchange',
    ];

    protected $casts = [
        'mediable_id'    => 'integer',
        'ditta_cg18'     => 'integer',
        'site_type'      => 'integer',
        'sort_order'     => 'integer',
        'erp_lastchange' => 'datetime',
    ];

    protected $appends = [
        'erp_full_path',
        'url',
        'is_global_swatch',
    ];

    protected static function booted(): void
    {
        static::saving(function (MediaAsset $model) {

            $model->role = trim((string) ($model->role ?? ''));

            if (!in_array($model->role, self::ALLOWED_ROLES, true)) {
                throw new InvalidArgumentException("MediaAsset role non valido: {$model->role}");
            }

            foreach (['filename', 'erp_path', 'local_path'] as $f) {
                if ($model->{$f} !== null) {
                    $v = trim((string) $model->{$f});
                    $model->{$f} = ($v === '') ? null : str_replace('\\', '/', $v);
                }
            }

            // meta_key/meta_value SEMPRE string (mai NULL)
            $model->meta_key   = trim((string) ($model->meta_key ?? ''));
            $model->meta_value = trim((string) ($model->meta_value ?? ''));

            if (!$model->filename) {
                throw new InvalidArgumentException('MediaAsset: filename obbligatorio.');
            }
        });
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getErpFullPathAttribute(): ?string
    {
        if (!$this->filename) return null;

        $p = trim((string) ($this->erp_path ?? ''));
        $p = str_replace('\\', '/', $p);

        return $p !== ''
            ? rtrim($p, '/') . '/' . $this->filename
            : $this->filename;
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->local_path) return null;

        return Storage::disk(env('MEDIA_SYNC_DISK', 'public'))
            ->url(ltrim((string) $this->local_path, '/'));
    }

    public function getIsGlobalSwatchAttribute(): bool
    {
        return $this->role === self::ROLE_SWATCH
            && (int) $this->site_type === 0;
    }

    public function scopeForProduct($query, int $ditta, int $site)
    {
        return $query
            ->where(function ($q) use ($ditta) {
                $q->where('ditta_cg18', $ditta)
                  ->orWhere('ditta_cg18', 0);
            })
            ->where(function ($q) use ($site) {
                $q->where('site_type', $site)
                  ->orWhere('site_type', 0);
            });
    }
}