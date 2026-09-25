<?php

namespace Tests\Unit;

use App\Models\Store;
use Tests\TestCase;

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

    public function test_it_exposes_ready_meta_pixel_id_only_for_ready_store(): void
    {
        config(['services.meta_pixel.ready.pixel_id' => '980692425978402']);

        $ready = new Store([
            'theme' => 'ready',
            'site_code' => 'READY',
        ]);

        $intempo = new Store([
            'theme' => 'intemposhop',
            'site_code' => 'INTEMPO_B2C',
        ]);

        $this->assertSame('980692425978402', $ready->metaPixelId());
        $this->assertNull($intempo->metaPixelId());
    }
}
