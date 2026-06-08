<?php

namespace App\Services\Shipping\Contracts;

use App\Data\Shipping\ShippingContext;
use App\Data\Shipping\ShippingQuote;

interface ShippingCalculatorInterface
{
    public function calculate(ShippingContext $context): ShippingQuote;
}