<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'admin_role',
        'locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function adminRole(): string
    {
        if (!$this->isAdmin()) {
            return 'user';
        }

        $role = trim((string) ($this->admin_role ?? ''));

        return $role !== '' ? $role : 'super_admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->adminRole() === 'super_admin';
    }

    public function isCustomerCare(): bool
    {
        return $this->adminRole() === 'customer_care';
    }

    public function isB2cManager(): bool
    {
        return $this->adminRole() === 'b2c_manager';
    }

    public function canAccessAdminSection(string $section): bool
    {
        if (!$this->isAdmin()) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return match ($section) {
            'dashboard' => true,
            'commercial', 'b2b_impersonation' => $this->isCustomerCare(),
            'orders', 'payments', 'sendcloud', 'storefront_seo', 'static_pages', 'b2c' => $this->isB2cManager(),
            default => false,
        };
    }

    public function canAccessAdminStore(?\App\Models\Store $store): bool
    {
        if (!$this->isAdmin() || !$store) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isCustomerCare()) {
            return $store->isB2B();
        }

        if ($this->isB2cManager()) {
            return $store->isB2C();
        }

        return false;
    }

    public function adminRoleLabel(): string
    {
        return match ($this->adminRole()) {
            'super_admin' => 'Super Admin',
            'customer_care' => 'Customer Care',
            'b2c_manager' => 'B2C / Digital',
            default => 'User',
        };
    }
}
