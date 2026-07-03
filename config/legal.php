<?php

return [
    'updated_at' => env('LEGAL_UPDATED_AT', '03/07/2026'),

    'profiles' => [
        'intempo' => [
            'company' => env('LEGAL_INTEMPO_COMPANY'),
            'address' => env('LEGAL_INTEMPO_ADDRESS'),
            'city' => env('LEGAL_INTEMPO_CITY'),
            'country' => env('LEGAL_INTEMPO_COUNTRY', 'Italia'),
            'vat' => env('LEGAL_INTEMPO_VAT'),
            'tax_code' => env('LEGAL_INTEMPO_TAX_CODE'),
            'sdi' => env('LEGAL_INTEMPO_SDI'),
            'email' => env('LEGAL_INTEMPO_EMAIL'),
            'pec' => env('LEGAL_INTEMPO_PEC'),
            'phone' => env('LEGAL_INTEMPO_PHONE'),
            'website' => env('LEGAL_INTEMPO_WEBSITE'),
        ],

        'fipell' => [
            'company' => env('LEGAL_FIPELL_COMPANY'),
            'address' => env('LEGAL_FIPELL_ADDRESS'),
            'city' => env('LEGAL_FIPELL_CITY'),
            'country' => env('LEGAL_FIPELL_COUNTRY', 'Italia'),
            'vat' => env('LEGAL_FIPELL_VAT'),
            'tax_code' => env('LEGAL_FIPELL_TAX_CODE'),
            'sdi' => env('LEGAL_FIPELL_SDI'),
            'email' => env('LEGAL_FIPELL_EMAIL'),
            'pec' => env('LEGAL_FIPELL_PEC'),
            'phone' => env('LEGAL_FIPELL_PHONE'),
            'website' => env('LEGAL_FIPELL_WEBSITE'),
        ],

        'diarpell' => [
            'company' => env('LEGAL_DIARPELL_COMPANY'),
            'address' => env('LEGAL_DIARPELL_ADDRESS'),
            'city' => env('LEGAL_DIARPELL_CITY'),
            'country' => env('LEGAL_DIARPELL_COUNTRY', 'Italia'),
            'vat' => env('LEGAL_DIARPELL_VAT'),
            'tax_code' => env('LEGAL_DIARPELL_TAX_CODE'),
            'sdi' => env('LEGAL_DIARPELL_SDI'),
            'email' => env('LEGAL_DIARPELL_EMAIL'),
            'pec' => env('LEGAL_DIARPELL_PEC'),
            'phone' => env('LEGAL_DIARPELL_PHONE'),
            'rea' => env('LEGAL_DIARPELL_REA'),
            'company_register' => env('LEGAL_DIARPELL_COMPANY_REGISTER'),
            'website' => env('LEGAL_DIARPELL_WEBSITE'),
        ],

        'papiro' => [
            'company' => env('LEGAL_PAPIRO_COMPANY'),
            'address' => env('LEGAL_PAPIRO_ADDRESS'),
            'city' => env('LEGAL_PAPIRO_CITY'),
            'country' => env('LEGAL_PAPIRO_COUNTRY', 'Italia'),
            'vat' => env('LEGAL_PAPIRO_VAT'),
            'tax_code' => env('LEGAL_PAPIRO_TAX_CODE'),
            'sdi' => env('LEGAL_PAPIRO_SDI'),
            'email' => env('LEGAL_PAPIRO_EMAIL'),
            'pec' => env('LEGAL_PAPIRO_PEC'),
            'phone' => env('LEGAL_PAPIRO_PHONE'),
            'rea' => env('LEGAL_PAPIRO_REA'),
            'company_register' => env('LEGAL_PAPIRO_COMPANY_REGISTER'),
            'website' => env('LEGAL_PAPIRO_WEBSITE'),
        ],
    ],

    'store_profiles' => [
        'intempo' => 'intempo',
        'intemposhop' => 'intempo',
        'ciak' => 'intempo',
        'ready' => 'intempo',
        'tekniko' => 'intempo',
        'teknikoshop' => 'intempo',

        'fipell' => 'fipell',
        'diarpell' => 'diarpell',
        'papiro' => 'papiro',
        'ilpapiro' => 'papiro',
    ],

    'default_profile' => 'intempo',

    'shipping_returns' => [
        'b2c' => [
            'return_days' => (int) env('LEGAL_B2C_RETURN_DAYS', 14),
            'customer_care_email' => env('LEGAL_B2C_CUSTOMER_CARE_EMAIL'),
            'return_deposit_address' => env('LEGAL_B2C_RETURN_DEPOSIT_ADDRESS', 'Via Forlanini 38, località Osmannoro, 50019 Sesto Fiorentino (FI)'),
        ],

        'b2b' => [
            'customer_care_email' => env('LEGAL_B2B_CUSTOMER_CARE_EMAIL'),
        ],
    ],

    'cookie_consent' => [
        'name' => env('LEGAL_COOKIE_CONSENT_NAME', 'storefront_cookie_consent'),
        'version' => env('LEGAL_COOKIE_CONSENT_VERSION', '1'),
        'days' => (int) env('LEGAL_COOKIE_CONSENT_DAYS', 180),
    ],
];
