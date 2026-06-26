<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
         api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            require base_path('routes/admin.php');
        },
    )

    ->withCommands([
        \App\Console\Commands\ErpSyncStores::class,
        \App\Console\Commands\ErpSyncAttributes::class,
        \App\Console\Commands\ErpSyncCustomerAcl::class,
        \App\Console\Commands\ErpSyncCustomerListini::class,
        \App\Console\Commands\ErpSyncCustomerShippingAddresses::class,
        \App\Console\Commands\ErpSyncCustomers::class,
        \App\Console\Commands\ErpSyncExportOrders::class,
        \App\Console\Commands\ErpSyncGroupDescriptions::class,
        \App\Console\Commands\ErpSyncMedia::class,
        \App\Console\Commands\ErpSyncPriceTiers::class,
        \App\Console\Commands\ErpSyncProductAttributeValues::class,
        \App\Console\Commands\ErpSyncProductComparisons::class,
        \App\Console\Commands\ErpSyncProducts::class,
        \App\Console\Commands\ErpSyncPublicPrices::class,
        \App\Console\Commands\ErpSyncStock::class,
        \App\Console\Commands\ErpSyncStoreVisibleGroups::class,
        \App\Console\Commands\DispatchDailyErpSyncs::class,
        \App\Console\Commands\SyncStoreLocatorLocations::class,
        \App\Console\Commands\SyncStorefrontBladePages::class,
    ])

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'localize'              => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes::class,
            'localizationRedirect'  => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect' => \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            'localeCookieRedirect'  => \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            'localeViewPath'        => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath::class,

            'store.context'         => \App\Http\Middleware\StoreContext::class,

            'admin.locale' => \App\Http\Middleware\AdminSetLocale::class,
            'admin.only'   => \App\Http\Middleware\AdminOnly::class,
            'admin.section' => \App\Http\Middleware\AdminSectionAccess::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            return route('storefront.login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
