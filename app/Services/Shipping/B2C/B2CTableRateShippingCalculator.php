<?php

namespace App\Services\Shipping\B2C;

use App\Data\Shipping\ShippingContext;
use App\Data\Shipping\ShippingQuote;
use App\Models\ShippingRule;
use App\Services\Shipping\Contracts\ShippingCalculatorInterface;
use Illuminate\Support\Collection;

class B2CTableRateShippingCalculator implements ShippingCalculatorInterface
{
    public function calculate(ShippingContext $context): ShippingQuote
    {
        $effectiveCountry = $this->normalizeCountry($context->country) ?? 'ITA';
        $effectiveProvince = $this->normalizeNullableString($context->province, true);
        $effectiveCap = $this->normalizeNullableString($context->cap, true);
        $effectiveWeight = max(0, (float) $context->weight);
        $effectiveSubtotal = max(0, (float) $context->subtotal);

        $freeQuote = $this->resolveFreeShippingQuote(
            context: $context,
            country: $effectiveCountry,
            province: $effectiveProvince,
            cap: $effectiveCap,
            subtotal: $effectiveSubtotal
        );

        if ($freeQuote instanceof ShippingQuote) {
            return $freeQuote;
        }

        $rules = ShippingRule::query()
            ->forStore($context->store)
            ->where('type', 'table')
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderBy('country')
            ->orderBy('province')
            ->orderBy('cap')
            ->orderBy('weight_from')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            return ShippingQuote::unavailable(__('themes_b2c.checkout.shipping_rule_missing'));
        }

        $matched = $rules->filter(function (ShippingRule $rule) use (
            $effectiveCountry,
            $effectiveProvince,
            $effectiveCap,
            $effectiveWeight
        ) {
            if (!$this->matchesCountry($rule->country, $effectiveCountry)) {
                return false;
            }

            if (!$this->matchesLocation($rule->province, $effectiveProvince)) {
                return false;
            }

            if (!$this->matchesCap($rule->cap, $effectiveCap)) {
                return false;
            }

            if ($rule->weight_from !== null && $effectiveWeight < (float) $rule->weight_from) {
                return false;
            }

            return true;
        });

        if ($matched->isNotEmpty()) {
            /** @var ShippingRule $rule */
            $rule = $matched
                ->sortBy([
                    ['priority', 'desc'],
                    ['weight_from', 'desc'],
                    ['id', 'asc'],
                ])
                ->first();

            return ShippingQuote::paid(
                (float) ($rule->amount ?? 0),
                $rule,
                __('themes_b2c.checkout.shipping_calculated')
            );
        }

        if ($effectiveProvince === null && $effectiveCap === null) {
            $defaultRule = $rules->filter(function (ShippingRule $rule) use ($effectiveCountry) {
                return $this->matchesCountry($rule->country, $effectiveCountry);
            })->sortBy([
                ['priority', 'desc'],
                ['weight_from', 'asc'],
                ['id', 'asc'],
            ])->first();

            if ($defaultRule instanceof ShippingRule) {
                return ShippingQuote::paid(
                    (float) ($defaultRule->amount ?? 0),
                    $defaultRule,
                    __('themes_b2c.checkout.shipping_estimate_calculated')
                );
            }
        }

        return ShippingQuote::unavailable(__('themes_b2c.checkout.shipping_destination_unavailable'));
    }

    protected function resolveFreeShippingQuote(
        ShippingContext $context,
        string $country,
        ?string $province,
        ?string $cap,
        float $subtotal
    ): ?ShippingQuote {
        $rules = ShippingRule::query()
            ->forStore($context->store)
            ->where('type', 'free')
            ->where('is_active', true)
            ->where(function ($query) use ($subtotal) {
                $query->whereNull('min_amount')
                    ->orWhere('min_amount', '<=', $subtotal);
            })
            ->where(function ($query) use ($subtotal) {
                $query->whereNull('max_amount')
                    ->orWhere('max_amount', '>=', $subtotal);
            })
            ->orderByDesc('priority')
            ->orderByDesc('min_amount')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            return $this->resolveBuiltInFreeShippingQuote($country, $subtotal);
        }

        $matched = $rules->filter(function (ShippingRule $rule) use ($country, $province, $cap) {
            if (!$this->matchesCountry($rule->country, $country)) {
                return false;
            }

            if (!$this->matchesLocation($rule->province, $province)) {
                return false;
            }

            if (!$this->matchesCap($rule->cap, $cap)) {
                return false;
            }

            return true;
        });

        if ($matched->isEmpty()) {
            return $this->resolveBuiltInFreeShippingQuote($country, $subtotal);
        }

        /** @var ShippingRule $rule */
        $rule = $matched
            ->sortBy([
                ['priority', 'desc'],
                ['min_amount', 'desc'],
                ['id', 'asc'],
            ])
            ->first();

        return ShippingQuote::free(
            $rule,
            $rule->country === 'ITA'
                ? __('themes_b2c.checkout.free_shipping_italy_over_60')
                : __('themes_b2c.checkout.free_shipping_threshold_reached')
        );
    }

    protected function resolveBuiltInFreeShippingQuote(string $country, float $subtotal): ?ShippingQuote
    {
        if ($country === 'ITA' && $subtotal >= 60) {
            return ShippingQuote::free(
                null,
                __('themes_b2c.checkout.free_shipping_italy_over_60')
            );
        }

        if ($this->isEuropeCountry($country) && $subtotal >= 120) {
            return ShippingQuote::free(
                null,
                __('themes_b2c.checkout.free_shipping_europe_over_120')
            );
        }

        return null;
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
            'ITA',
            'LIE',
            'LTU',
            'LUX',
            'LVA',
            'MCO',
            'MDA',
            'MNE',
            'MKD',
            'MLT',
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

    protected function matchesCountry(?string $ruleValue, ?string $contextValue): bool
    {
        $ruleValue = $this->normalizeCountry($ruleValue);
        $contextValue = $this->normalizeCountry($contextValue);

        if ($ruleValue === null) {
            return true;
        }

        if ($contextValue === null) {
            return false;
        }

        return $ruleValue === $contextValue;
    }

    protected function matchesLocation(?string $ruleValue, ?string $contextValue): bool
    {
        $ruleValue = $this->normalizeNullableString($ruleValue, true);
        $contextValue = $this->normalizeNullableString($contextValue, true);

        if ($ruleValue === null) {
            return true;
        }

        if ($contextValue === null) {
            return false;
        }

        return $ruleValue === $contextValue;
    }

    protected function matchesCap(?string $ruleCap, ?string $cap): bool
    {
        $ruleCap = $this->normalizeNullableString($ruleCap, true);
        $cap = $this->normalizeNullableString($cap, true);

        if ($ruleCap === null) {
            return true;
        }

        if ($cap === null) {
            return false;
        }

        if (str_contains($ruleCap, '*')) {
            return str_starts_with($cap, rtrim($ruleCap, '*'));
        }

        return $ruleCap === $cap;
    }

    protected function normalizeCountry(?string $value): ?string
    {
        $value = $this->normalizeNullableString($value, true);

        if ($value === null) {
            return null;
        }

        return match ($value) {
            'IT', 'ITA' => 'ITA',
            default => $value,
        };
    }

    protected function normalizeNullableString(mixed $value, bool $uppercase = false): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $normalized = $uppercase ? strtoupper($value) : $value;
        $upper = strtoupper($normalized);

        if (in_array($upper, ['ALL', '*', '•', '-', '--'], true)) {
            return null;
        }

        return $normalized;
    }
}
