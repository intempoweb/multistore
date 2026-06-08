<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Store;

class StoresSeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            [
                'domain' => 'intempodistribution.test',
                'name' => 'Intempo Distribution',
                'company_code' => 'INTEMPO',
                'site_code' => 'INTEMPO_DISTRIBUTION',
                'is_b2b' => true,
                'theme' => 'intempodistribution',
                'default_locale' => 'it',
                'supported_locales' => ['it','en','es'],
                'is_active' => true,
            ],
            [
                'domain' => 'intemposhop.test',
                'name' => 'Intempo Shop',
                'company_code' => 'INTEMPO',
                'site_code' => 'INTEMPO_SHOP',
                'is_b2b' => false,
                'theme' => 'intemposhop',
                'default_locale' => 'it',
                'supported_locales' => ['it','en','es'],
                'is_active' => true,
            ],
            [
                'domain' => 'ciak.test',
                'name' => 'Ciak',
                'company_code' => 'INTEMPO',
                'site_code' => 'CIAK',
                'is_b2b' => false,
                'theme' => 'ciak',
                'default_locale' => 'it',
                'supported_locales' => ['it','en','es'],
                'is_active' => true,
            ],
            [
                'domain' => 'teknikoshop.test',
                'name' => 'Tekniko',
                'company_code' => 'INTEMPO',
                'site_code' => 'TEKNIKO',
                'is_b2b' => false,
                'theme' => 'tekniko',
                'default_locale' => 'it',
                'supported_locales' => ['it','en','es'],
                'is_active' => true,
            ],
            [
                'domain' => 'fipell.test',
                'name' => 'Fipell',
                'company_code' => 'FIPELL',
                'site_code' => 'FIPELL_B2B',
                'is_b2b' => true,
                'theme' => 'fipell',
                'default_locale' => 'it',
                'supported_locales' => ['it','en','es'],
                'is_active' => true,
            ],
        ];

        foreach ($stores as $data) {
            Store::updateOrCreate(
                ['domain' => $data['domain']],
                $data
            );
        }
    }
}