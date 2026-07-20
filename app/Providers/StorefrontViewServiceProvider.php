<?php

namespace App\Providers;

use App\View\Composers\StorefrontChromeComposer;
use App\View\Composers\StorefrontSidebarComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class StorefrontViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer([
            'storefront.base.partials.header',
            'storefront.base.partials.footer',
            'storefront.base.partials.topbar',
            'storefront.themes.b2b.intempodistribution.partials.header',
            'storefront.themes.b2b.intempodistribution.partials.topbar',
            'storefront.themes.b2b.fipell.partials.header',
            'storefront.themes.b2b.fipell.partials.topbar',
            'storefront.themes.b2c.ciak.partials.header',
            'storefront.themes.b2c.ciak.partials.footer',
            'storefront.themes.b2c.intemposhop.partials.header',
            'storefront.themes.b2c.intemposhop.partials.footer',
            'storefront.themes.b2c.teknikoshop.partials.header',
            'storefront.themes.b2c.teknikoshop.partials.footer',
        ], StorefrontChromeComposer::class);

        View::composer('storefront.base.partials.sidebar', StorefrontSidebarComposer::class);
    }
}
