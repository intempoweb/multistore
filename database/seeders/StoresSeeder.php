<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

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
                'ditta_cg18' => 1,
                'erp_site_code' => 1,
                'is_b2b' => true,
                'theme' => 'intempodistribution',
                'default_locale' => 'it',
                'supported_locales' => ['it', 'en', 'es'],
                'is_active' => true,
            ],
            [
                'domain' => 'intemposhop.test',
                'name' => 'B2C INTEMPO',
                'company_code' => 'INTEMPO',
                'site_code' => 'INTEMPO_B2C',
                'ditta_cg18' => 1,
                'erp_site_code' => 2,
                'is_b2b' => false,
                'theme' => 'intemposhop',
                'default_locale' => 'it',
                'supported_locales' => ['it', 'en', 'es'],
                'is_active' => true,
            ],
            [
                'domain' => 'ciak.test',
                'name' => 'B2C CIAK',
                'company_code' => 'INTEMPO',
                'site_code' => 'CIAK',
                'ditta_cg18' => 1,
                'erp_site_code' => 5,
                'is_b2b' => false,
                'theme' => 'ciak',
                'default_locale' => 'it',
                'supported_locales' => ['it', 'en', 'es'],
                'is_active' => true,
            ],
            [
                'domain' => 'teknikoshop.test',
                'name' => 'TEKNIKO B2C',
                'company_code' => 'INTEMPO',
                'site_code' => 'TEKNIKO',
                'ditta_cg18' => 1,
                'erp_site_code' => 6,
                'is_b2b' => false,
                'theme' => 'tekniko',
                'default_locale' => 'it',
                'supported_locales' => ['it', 'en', 'es'],
                'is_active' => true,
            ],
            [
                'domain' => 'fipell.test',
                'name' => 'B2B FIPELL SERVICE',
                'company_code' => 'FIPELL',
                'site_code' => 'FIPELL_B2B',
                'ditta_cg18' => 3,
                'erp_site_code' => 1,
                'is_b2b' => true,
                'theme' => 'fipell',
                'default_locale' => 'it',
                'supported_locales' => ['it', 'en', 'es'],
                'is_active' => true,
            ],
        ];

       foreach ($stores as $data) {

            Store::updateOrCreate(

                [

                    'ditta_cg18' => $data['ditta_cg18'],

                    'erp_site_code' => $data['erp_site_code'],

                ],

                $data

            );

        }
    }
}