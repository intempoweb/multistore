<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = [
        'store_id',
        'channel',
        'cart_token',
        'ditta_cg18',
        'site_type',
        'store_code',
        'is_b2b',
        'customer_id',
        'session_id',
        'expires_at',
        'status',
        'customer_name',
        'customer_email',
        'customer_clifor_cg44',
        'shipping_address_id',
        'shipping_name',
        'shipping_address',
        'shipping_zip',
        'shipping_city',
        'shipping_province',
        'shipping_country',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'grand_total',
        'currency',
        'notes',
        'meta',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'ditta_cg18' => 'integer',
        'site_type' => 'integer',
        'is_b2b' => 'boolean',
        'customer_id' => 'integer',
        'customer_clifor_cg44' => 'integer',
        'shipping_address_id' => 'integer',

        'subtotal' => 'decimal:3',
        'discount_total' => 'decimal:3',
        'shipping_total' => 'decimal:3',
        'tax_total' => 'decimal:3',
        'grand_total' => 'decimal:3',

        'expires_at' => 'datetime',
        'meta' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerShippingAddress::class, 'shipping_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForContext(Builder $query, int $ditta, ?int $siteType = null): Builder
    {
        $query->where('ditta_cg18', $ditta);

        if ($siteType !== null) {
            $query->where('site_type', $siteType);
        }

        return $query;
    }

    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', trim($sessionId));
    }

    public function scopeForToken(Builder $query, string $token): Builder
    {
        return $query->where('cart_token', trim($token));
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $sub) {
            $sub->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isGuest(): bool
    {
        return $this->customer_id === null;
    }
}