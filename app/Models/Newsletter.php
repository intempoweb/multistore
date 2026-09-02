<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Newsletter extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SYNCED = 'synced';
    public const STATUS_ERROR = 'error';

    public const SELECTION_MANUAL = 'manual';
    public const SELECTION_OFFERS = 'offers';
    public const SELECTION_PROMOTIONS = 'promotions';
    public const SELECTION_NEW_PRODUCTS = 'new_products';
    public const SELECTION_CAMPAIGNS = 'campaigns';
    public const SELECTION_MIXED = 'mixed';

    public const PROVIDER_MAILCHIMP = 'mailchimp';
    public const PROVIDER_BREVO = 'brevo';

    public const SELECTION_LABELS = [
        self::SELECTION_MANUAL => 'Selezione manuale',
        self::SELECTION_OFFERS => 'Offerte ERP',
        self::SELECTION_PROMOTIONS => 'Promozioni ERP',
        self::SELECTION_NEW_PRODUCTS => 'Novita ERP',
        self::SELECTION_CAMPAIGNS => 'Campagne ERP',
        self::SELECTION_MIXED => 'Mix criteri ERP',
    ];

    public const PROVIDER_LABELS = [
        self::PROVIDER_MAILCHIMP => 'Mailchimp',
        self::PROVIDER_BREVO => 'Brevo',
    ];

    protected $fillable = [
        'store_id',
        'ditta_cg18',
        'site_type',
        'name',
        'subject',
        'preview_text',
        'locale',
        'status',
        'selection_type',
        'provider',
        'listino_id',
        'price_mode',
        'hero_title',
        'hero_text',
        'hero_image_url',
        'intro_html',
        'filters',
        'settings',
        'rendered_html',
        'provider_campaign_id',
        'provider_web_id',
        'provider_edit_url',
        'provider_synced_at',
        'created_by',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'ditta_cg18' => 'integer',
        'site_type' => 'integer',
        'listino_id' => 'integer',
        'filters' => 'array',
        'settings' => 'array',
        'provider_synced_at' => 'datetime',
        'created_by' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function newsletterProducts(): HasMany
    {
        return $this->hasMany(NewsletterProduct::class)->orderBy('sort_order');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'newsletter_products')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('newsletter_products.sort_order');
    }

    public function scopeForStore(Builder $query, Store $store): Builder
    {
        return $query->where('store_id', $store->id);
    }

    public function contextKey(): string
    {
        return ((int) $this->ditta_cg18) . ':' . ((int) $this->site_type);
    }

    public function providerLabel(): string
    {
        return self::PROVIDER_LABELS[$this->provider] ?? $this->provider;
    }

    public function selectionLabel(): string
    {
        return self::SELECTION_LABELS[$this->selection_type] ?? $this->selection_type;
    }
}
