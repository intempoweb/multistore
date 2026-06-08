<?php

namespace App\Data\Shipping;

use App\Models\ShippingRule;

final class ShippingQuote
{
    public function __construct(
        public readonly bool $available,
        public readonly float $amount,
        public readonly bool $isFree,
        public readonly ?ShippingRule $rule,
        public readonly string $message,
    ) {
    }

    public static function unavailable(string $message = 'Spedizione non disponibile'): self
    {
        return new self(false, 0.0, false, null, $message);
    }

    public static function free(?ShippingRule $rule = null, string $message = 'Spedizione gratuita'): self
    {
        return new self(true, 0.0, true, $rule, $message);
    }

    public static function paid(float $amount, ?ShippingRule $rule = null, string $message = ''): self
    {
        return new self(true, max(0, $amount), false, $rule, $message);
    }

    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'amount' => $this->formatAmount($this->amount),
            'is_free' => $this->isFree,
            'rule_id' => $this->rule?->id,
            'rule_name' => $this->rule?->name,
            'message' => $this->message,
        ];
    }

    private function formatAmount(float $value): float
    {
        return round($value, 3);
    }
}
