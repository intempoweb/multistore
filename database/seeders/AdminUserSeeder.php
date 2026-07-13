<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'mperu85@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'admin_role' => 'super_admin',
                'locale' => 'it',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'marcoperuzzi1985@gmail.com'],
            [
                'name' => 'Customer Care',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'admin_role' => 'customer_care',
                'locale' => 'it',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'm.pazzaglia@intempo.it'],
            [
                'name' => 'B2C / Digital',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'admin_role' => 'b2c_manager',
                'locale' => 'it',
                'email_verified_at' => now(),
            ]
        );
    }
}