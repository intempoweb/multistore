<?php

namespace Tests\Unit;

use App\Models\Store;
use PHPUnit\Framework\TestCase;

class StoreTest extends TestCase
{
    public function test_it_exposes_b2b_derived_values(): void
    {
        $store = new Store([
            'is_b2b' => true,
            'default_locale' => 'it',
            'supported_locales' => ['it', 'en', 'it'],
        ]);

        $this->assertTrue($store->isB2B());
        $this->assertFalse($store->isB2C());
        $this->assertSame('b2b', $store->channel());
        $this->assertSame('B2B', $store->channelLabel());
        $this->assertSame(30, $store->cartLifetimeDays());
        $this->assertSame(3, $store->priceDecimals());
        $this->assertSame('it', $store->defaultLocale());
        $this->assertSame(['it', 'en'], $store->supportedLocales());
    }

    public function test_it_exposes_b2c_derived_values_with_locale_fallback(): void
    {
        $store = new Store([
            'is_b2b' => false,
            'default_locale' => null,
            'supported_locales' => [],
        ]);

        $this->assertFalse($store->isB2B());
        $this->assertTrue($store->isB2C());
        $this->assertSame('b2c', $store->channel());
        $this->assertSame('B2C', $store->channelLabel());
        $this->assertSame(7, $store->cartLifetimeDays());
        $this->assertSame(2, $store->priceDecimals());
        $this->assertSame('en', $store->defaultLocale('en'));
        $this->assertSame(['en'], $store->supportedLocales('en'));
    }
}
