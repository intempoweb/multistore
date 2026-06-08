<?php

namespace App\Services\Shipping\B2B;

use App\Data\Shipping\ShippingContext;
use App\Data\Shipping\ShippingQuote;
use App\Models\ShippingRule;
use App\Services\Shipping\Contracts\ShippingCalculatorInterface;

class B2BShippingCalculator implements ShippingCalculatorInterface
{
    public function calculate(ShippingContext $context): ShippingQuote
    {
        $rules = ShippingRule::query()
            ->where('is_active', true)
            ->whereIn('type', ['fixed', 'free_over'])
            ->where(function ($query) use ($context) {
                $query->where('store_id', $context->store->id)
                    ->orWhere(function ($fallback) use ($context) {
                        $fallback->whereNull('store_id')
                            ->where('ditta_cg18', $context->store->ditta_cg18)
                            ->where('erp_site_code', $context->store->erp_site_code);
                    });
            })
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        if ($rules->isEmpty()) {
            return ShippingQuote::unavailable('Nessuna regola spedizione B2B configurata.');
        }

        foreach ($rules as $rule) {
            if (!$this->matchesLocation($rule->country, $context->country)) {
                continue;
            }

            if ($rule->type === 'free_over') {
                $minAmount = (float) ($rule->min_amount ?? 0);

                if ($context->subtotal >= $minAmount) {
                    return ShippingQuote::free($rule, 'Spedizione gratuita');
                }

                continue;
            }

            if ($rule->type === 'fixed') {
                return ShippingQuote::paid((float) ($rule->amount ?? 0), $rule, 'Spedizione fissa');
            }
        }

        return ShippingQuote::unavailable('Nessuna regola applicabile.');
    }

    private function matchesLocation(?string $ruleValue, ?string $contextValue): bool
    {
        $ruleValue = $this->normalizeNullableString($ruleValue, true);
        $contextValue = $this->normalizeNullableString($contextValue, true);

        if ($ruleValue === null) {
            return true;
        }

        return $ruleValue === $contextValue;
    }

    private function normalizeNullableString(mixed $value, bool $uppercase = false): ?string
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