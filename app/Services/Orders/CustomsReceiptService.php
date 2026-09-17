<?php

namespace App\Services\Orders;

use App\Models\Order;

final class CustomsReceiptService
{
    private const EU_COUNTRIES = [
        'AT', 'AUT', 'BE', 'BEL', 'BG', 'BGR', 'HR', 'HRV', 'CY', 'CYP', 'CZ', 'CZE',
        'DK', 'DNK', 'EE', 'EST', 'FI', 'FIN', 'FR', 'FRA', 'DE', 'DEU', 'GR', 'GRC',
        'HU', 'HUN', 'IE', 'IRL', 'IT', 'ITA', 'LV', 'LVA', 'LT', 'LTU', 'LU', 'LUX',
        'MT', 'MLT', 'NL', 'NLD', 'PL', 'POL', 'PT', 'PRT', 'RO', 'ROU', 'SK', 'SVK',
        'SI', 'SVN', 'ES', 'ESP', 'SE', 'SWE',
    ];

    public function isEligible(Order $order): bool
    {
        if (!$order->isB2c()) {
            return false;
        }

        $country = strtoupper(trim((string) $order->shipping_country_code));

        return $country !== "" && !in_array($country, self::EU_COUNTRIES, true);
    }
}
