<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Storefront\CustomerImpersonationController;
use App\Http\Controllers\Storefront\OrderProductImagesController;
use App\Http\Controllers\Storefront\SitemapController;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/*
|--------------------------------------------------------------------------
| ROOT (redirect alla versione localizzata)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect(LaravelLocalization::getLocalizedURL(app()->getLocale(), '/'));
});

/*
|--------------------------------------------------------------------------
| Customer impersonation (NO prefisso lingua)
|--------------------------------------------------------------------------
| Il link viene aperto dal BO/admin e deve funzionare anche senza /it, /en...
*/
Route::middleware(['web', 'store.context'])->group(function () {
    Route::get('/customer-impersonation/{token}', [CustomerImpersonationController::class, 'handle'])
        ->name('customer.impersonation');
    Route::get('/order-assets/{order:order_number}/product-images/{file}', [OrderProductImagesController::class, 'download'])
        ->where('file', '[A-Za-z0-9._-]+\.zip')
        ->middleware('auth:customer')
        ->name('storefront.orders.product-images.download');
    Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('storefront.sitemap');
    Route::get('/sitemaps/catalog/{locale}.xml', [SitemapController::class, 'catalog'])->name('storefront.sitemap.catalog');
    Route::get('/sitemaps/products/{locale}/{page}.xml', [SitemapController::class, 'products'])
        ->whereNumber('page')
        ->name('storefront.sitemap.products');
});

/*
|--------------------------------------------------------------------------
| Auth routes (Breeze)
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| Frontend localizzato + Store Context
|--------------------------------------------------------------------------
*/
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => [
        'store.context',
        'localeSessionRedirect',
        'localizationRedirect',
        'localeViewPath',
    ],
    'as' => 'storefront.',
], function () {

    /*
    |--------------------------------------------------------------------------
    | Storefront routes
    |--------------------------------------------------------------------------
    */
    require __DIR__ . '/storefront.php';

    /*
    |--------------------------------------------------------------------------
    | Profile (utente autenticato)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth')->group(function () {

        Route::get('/profile', [ProfileController::class, 'edit'])
            ->name('profile.edit');

        Route::patch('/profile', [ProfileController::class, 'update'])
            ->name('profile.update');

        Route::delete('/profile', [ProfileController::class, 'destroy'])
            ->name('profile.destroy');

    });

});
