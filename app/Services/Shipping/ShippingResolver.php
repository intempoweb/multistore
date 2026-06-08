<?php

namespace App\Services\Shipping;

use App\Data\Shipping\ShippingContext;
use App\Data\Shipping\ShippingQuote;
use App\Services\Shipping\B2B\B2BShippingCalculator;
use App\Services\Shipping\B2C\B2CTableRateShippingCalculator;

class ShippingResolver
{
    public function __construct(
        protected B2BShippingCalculator $b2bCalculator,
        protected B2CTableRateShippingCalculator $b2cCalculator,
    ) {}

    public function quote(ShippingContext $context): ShippingQuote
    {
        if ($context->isB2b) {
            return $this->b2bCalculator->calculate($context);
        }

        if ($this->isFreeB2cShipping($context)) {
            return ShippingQuote::free(
                null,
                $this->resolveFreeB2cShippingMessage($context)
            );
        }

        return $this->b2cCalculator->calculate($context);
    }

    protected function isFreeB2cShipping(ShippingContext $context): bool
    {
        $country = $this->normalizeCountry($context->country) ?? 'ITA';
        $subtotal = (float) $context->subtotal;

        if ($country === 'ITA') {
            return $subtotal >= 60;
        }

        if ($this->isEuropeCountry($country)) {
            return $subtotal >= 120;
        }

        return false;
    }

    protected function resolveFreeB2cShippingMessage(ShippingContext $context): string
    {
        $country = $this->normalizeCountry($context->country) ?? 'ITA';

        if ($country === 'ITA') {
            return 'Spedizione gratuita in Italia per ordini superiori a € 60.';
        }

        return 'Spedizione gratuita in Europa per ordini superiori a € 120.';
    }

    protected function isEuropeCountry(string $country): bool
    {
        return in_array($country, [
            'ALB',
            'AND',
            'AUT',
            'BEL',
            'BGR',
            'BIH',
            'BLR',
            'CHE',
            'CYP',
            'CZE',
            'DEU',
            'DNK',
            'ESP',
            'EST',
            'FIN',
            'FRA',
            'GBR',
            'GRC',
            'HRV',
            'HUN',
            'IRL',
            'ISL',
            'LIE',
            'LTU',
            'LUX',
            'LVA',
            'MCO',
            'MDA',
            'MKD',
            'MLT',
            'MNE',
            'NLD',
            'NOR',
            'POL',
            'PRT',
            'ROU',
            'SMR',
            'SRB',
            'SVK',
            'SVN',
            'SWE',
            'TUR',
            'UKR',
            'VAT',
        ], true);
    }

    protected function normalizeCountry(?string $country): ?string
    {
        $country = strtoupper(trim((string) $country));

        if ($country === '') {
            return null;
        }

        return match ($country) {
            'IT' => 'ITA',
            default => $country,
        };
    }
}