<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AgentAuth extends Model
{
    protected $table = 'agent_auths';

    protected $fillable = [
        'ditta_cg18',
        'agente_mg17',
        'indeemail_vwebdcg44',
        'password',
        'email_verified_at',
        'remember_token',
        'magic_login_token_hash',
        'magic_login_expires_at',
        'magic_login_used_at',
        'last_login_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'magic_login_token_hash',
    ];

    protected $casts = [
        'ditta_cg18' => 'integer',
        'email_verified_at' => 'datetime',
        'magic_login_expires_at' => 'datetime',
        'magic_login_used_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForAgent(Builder $query, int $ditta, string $agentCode, string $agentEmail): Builder
    {
        return $query
            ->where('ditta_cg18', $ditta)
            ->where('agente_mg17', trim($agentCode))
            ->whereRaw('LOWER(indeemail_vwebdcg44) = ?', [Str::lower(trim($agentEmail))]);
    }

    public function hasUsablePassword(): bool
    {
        return !empty($this->password);
    }

    public function passwordMatches(string $plainPassword): bool
    {
        return $this->hasUsablePassword()
            && Hash::check($plainPassword, (string) $this->password);
    }

    public function getEmailForPasswordReset(): ?string
    {
        $email = Str::lower(trim((string) $this->indeemail_vwebdcg44));

        return $email !== '' ? $email : null;
    }

    public function canReceiveMagicLink(): bool
    {
        return (bool) $this->is_active
            && $this->getEmailForPasswordReset() !== null;
    }
}
