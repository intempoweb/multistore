<?php

namespace Tests\Unit;

use App\Models\Cart;
use PHPUnit\Framework\TestCase;

class CartTest extends TestCase
{
    public function test_it_exposes_b2b_derived_values(): void
    {
        $cart = new Cart([
            'is_b2b' => true,
        ]);

        $this->assertTrue($cart->isB2B());
        $this->assertFalse($cart->isB2C());
        $this->assertSame(30, $cart->cartLifetimeDays());
    }

    public function test_it_exposes_b2c_derived_values(): void
    {
        $cart = new Cart([
            'is_b2b' => false,
        ]);

        $this->assertFalse($cart->isB2B());
        $this->assertTrue($cart->isB2C());
        $this->assertSame(7, $cart->cartLifetimeDays());
    }
}
