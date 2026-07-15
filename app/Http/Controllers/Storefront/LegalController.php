<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\Legal\CookieCatalog;
use App\Services\Storefront\LegalProfileResolver;
use App\Services\Storefront\StorefrontContext;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Contracts\View\View;

class LegalController extends Controller
{
    public function __construct(
        private StorefrontContext $context,
        private ThemeResolver $themeResolver,
        private LegalProfileResolver $legalProfileResolver,
        private CookieCatalog $cookieCatalog,
    ) {}

    public function privacy(): View
    {
        return $this->show('privacy');
    }

    public function cookies(): View
    {
        return $this->show('cookies');
    }

    public function shippingReturns(): View
    {
        return $this->show('shipping-returns');
    }

    private function show(string $page): View
    {
        $store = $this->context->store();
        $legalProfile = $this->legalProfileResolver->resolve($store);
        $isB2b = $store->isB2B();

        if (blank($legalProfile['company'] ?? null)) {
            $legalProfile['company'] = $store?->name ?? config('app.name', 'Store');
        }

        $legalServices = [
            'google_analytics_enabled' => filled(config('services.google_analytics.measurement_id')),
            'google_ads_enabled' => filled(config('services.google_ads.conversion_id')),
            'google_maps_enabled' => filled(config('services.google_maps.api_key')) || filled(config('services.google_maps.geocoding_api_key')),
            'instagram_enabled' => filled(config('services.instagram.access_token')),
            'stripe_enabled' => filled(config('services.stripe.key')),
            'paypal_enabled' => filled(config('services.paypal.client_id')),
            'sendcloud_enabled' => filled(config('services.sendcloud.public_key')) && filled(config('services.sendcloud.secret_key')),
        ];

        $legalMode = $store->channel();
        $shippingReturnsProfile = config('legal.shipping_returns.' . $legalMode, []);

        return view($this->themeResolver->view("legal.{$page}", $store), [
            'store' => $store,
            'legalProfile' => $legalProfile,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'legalUpdatedAt' => (string) config('legal.updated_at', '01/01/2026'),
            'legalServices' => $legalServices,
            'legalMode' => $legalMode,
            'isB2b' => $isB2b,
            'shippingReturnsProfile' => $shippingReturnsProfile,
            'cookieCatalog' => $this->cookieCatalog->forStore($store),
        ]);
    }
}
