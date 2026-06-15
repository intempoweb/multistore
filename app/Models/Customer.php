<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable implements AuthenticatableContract
{
    use Notifiable;

    protected $table = 'customers';

    protected $fillable = [
        'ditta_cg18',
        'tipocf_cg44',
        'clifor_cg44',

        'codice_cg16',
        'ragsoanag_cg16',
        'partiva_cg16',
        'codfiscale_cg16',

        'cognomeconnweb',
        'nomeconnweb',

        'indemail_cg16',
        'password',
        'email_verified_at',
        'remember_token',
        'magic_login_token_hash',
        'magic_login_expires_at',
        'magic_login_used_at',
        'last_login_at',
        'indemailperfatt_cg16',
        'tel1num_cg16',
        'tel2num_cg16',
        'faxnum_cg16',
        'cellnum_cg16',
        'indweb_cg16',
        'email_pec_cg16',

        'indirizzo_cg16',
        'cap_cg16',
        'citta_cg16',
        'prov_cg16',

        'ragsocor_cg16',
        'indircor_cg16',
        'capcor_cg16',
        'cittacor_cg16',
        'provcor_cg16',

        'codpag_cg62',
        'descrizpag_cg62',
        'codice_cg28',
        'descr_cg28',
        'perciva_cg28',

        'agente_mg17',
        'ragsoanag_vwebdcg44',
        'indeemail_vwebdcg44',

        'codlistinoded',
        'codrifalf_mg19',

        'ccabi_mg35',
        'cccab_mg35',
        'desbanca_cg12_cg13',
        'iban_mg35',

        'filtroestr',

        'erp_lastchange',
        'erp_last_seen_at',

        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'magic_login_token_hash',
    ];

    protected $casts = [
        'ditta_cg18' => 'integer',
        'tipocf_cg44' => 'integer',
        'clifor_cg44' => 'integer',
        'codice_cg16' => 'integer',

        'perciva_cg28' => 'decimal:2',
        'codlistinoded' => 'integer',
        'ccabi_mg35' => 'integer',
        'cccab_mg35' => 'integer',
        'filtroestr' => 'integer',

        'is_active' => 'boolean',
        'erp_last_seen_at' => 'datetime',
        'erp_lastchange' => 'date',
        'email_verified_at' => 'datetime',
        'magic_login_expires_at' => 'datetime',
        'magic_login_used_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForErpKey(Builder $q, int $ditta, int $tipoCf, int $clifor): Builder
    {
        return $q->where('ditta_cg18', $ditta)
            ->where('tipocf_cg44', $tipoCf)
            ->where('clifor_cg44', $clifor);
    }

    public function scopeWebEnabled(Builder $q): Builder
    {
        return $q->where('codrifalf_mg19', 'PT');
    }

    public function scopeAuthEnabled(Builder $q): Builder
    {
        return $q->active()
            ->webEnabled()
            ->whereNotNull('indemail_cg16')
            ->where('indemail_cg16', '<>', '');
    }
    
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(CustomerWishlistItem::class);
    }

    public function shippingAddresses(): HasMany
    {
        return $this->hasMany(CustomerShippingAddress::class, 'clifor_cg44', 'clifor_cg44')
            ->whereColumn('customer_shipping_addresses.ditta_cg18', 'customers.ditta_cg18')
            ->whereColumn('customer_shipping_addresses.tipocf_cg44', 'customers.tipocf_cg44')
            ->orderBy('coddestin_mg22');
    }

    public function agentAuth(): HasOne
    {
        return $this->hasOne(AgentAuth::class, 'ditta_cg18', 'ditta_cg18')
            ->whereColumn('agent_auths.indeemail_vwebdcg44', 'customers.indeemail_vwebdcg44');
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getEmailForPasswordReset(): ?string
    {
        $email = trim((string) ($this->indemail_cg16 ?? ''));

        return $email !== '' ? $email : null;
    }

    public function hasUsablePassword(): bool
    {
        return !empty($this->password);
    }

    public function canReceiveMagicLink(): bool
    {
        return $this->is_active
            && strtoupper(trim((string) ($this->codrifalf_mg19 ?? ''))) === 'PT'
            && $this->getEmailForPasswordReset() !== null;
    }
}