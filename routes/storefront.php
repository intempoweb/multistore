<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\Auth\CustomerAuthController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\PaymentController;
use App\Http\Controllers\Storefront\WishlistController;
use App\Http\Controllers\Storefront\CustomerDocumentsController;
use App\Http\Controllers\Storefront\AgentCustomerController;
use App\Services\Storefront\ThemeResolver;

/*
|--------------------------------------------------------------------------
| STOREFRONT
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::match(['GET', 'POST'], '/logout', [CustomerAuthController::class, 'logout'])
    ->name('logout');

Route::get('/catalog', [CatalogController::class, 'index'])
    ->name('catalog.index');

Route::get('/search', [SearchController::class, 'index'])
    ->name('search.index');

Route::get('/search/suggest', [SearchController::class, 'suggest'])
    ->name('search.suggest');

Route::get('/category/{slug}', [CategoryController::class, 'show'])
    ->where('slug', '.*')
    ->name('category.legacy');

Route::get('/product/{sku}', [ProductController::class, 'show'])
    ->name('product.show');

/*
|--------------------------------------------------------------------------
| CART
|--------------------------------------------------------------------------
*/

Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])
        ->name('index');

    Route::get('/mini', [CartController::class, 'mini'])
        ->name('mini');

    Route::post('/add', [CartController::class, 'add'])
        ->name('add');

    Route::get('/import/template', [CartController::class, 'downloadImportTemplate'])
        ->middleware('auth:customer')
        ->name('import.template');

    Route::post('/import', [CartController::class, 'import'])
        ->middleware('auth:customer')
        ->name('import');

    Route::get('/export', [CartController::class, 'export'])
        ->middleware('auth:customer')
        ->name('export');

    Route::post('/update/{item}', [CartController::class, 'update'])
        ->name('update');

    Route::delete('/remove/{item}', [CartController::class, 'remove'])
        ->name('remove');

    Route::post('/coupon', [CartController::class, 'applyCoupon'])
        ->name('coupon.apply');

    Route::delete('/coupon', [CartController::class, 'removeCoupon'])
        ->name('coupon.remove');
});

/*
|--------------------------------------------------------------------------
| CHECKOUT
|--------------------------------------------------------------------------
*/

Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', [CheckoutController::class, 'show'])
        ->name('show');

    Route::get('/success/{order:order_number}', [CheckoutController::class, 'success'])
        ->name('success');

    Route::post('/payment-preview', [CheckoutController::class, 'paymentPreview'])
        ->name('payment.preview');

    Route::post('/place-order', [CheckoutController::class, 'placeOrder'])
        ->name('place');
});

/*
|--------------------------------------------------------------------------
| PAYMENTS
|--------------------------------------------------------------------------
*/

Route::prefix('payment')->name('payment.')->group(function () {
    Route::get('/stripe/success', [PaymentController::class, 'stripeSuccess'])
        ->name('stripe.success');

    Route::get('/paypal/success', [PaymentController::class, 'paypalSuccess'])
        ->name('paypal.success');

    Route::post('/paypal/capture', [PaymentController::class, 'paypalCapture'])
        ->name('paypal.capture');

    Route::get('/cancel', [PaymentController::class, 'cancel'])
        ->name('cancel');
});

/*
|--------------------------------------------------------------------------
| WISHLIST
|--------------------------------------------------------------------------
*/

Route::prefix('wishlist')
    ->name('wishlist.')
    ->middleware('auth:customer')
    ->group(function () {
        Route::get('/', [WishlistController::class, 'index'])
            ->name('index');

        Route::post('/toggle', [WishlistController::class, 'toggle'])
            ->name('toggle');

        Route::post('/add', [WishlistController::class, 'add'])
            ->name('add');

        Route::delete('/remove/{item}', [WishlistController::class, 'remove'])
            ->name('remove');

        Route::post('/move-to-cart/{item}', [WishlistController::class, 'moveToCart'])
            ->name('move-to-cart');
    });

/*
|--------------------------------------------------------------------------
| CUSTOMER AUTH
|--------------------------------------------------------------------------
*/

Route::middleware('guest:customer')->group(function () {
    Route::get('/login', [CustomerAuthController::class, 'showLoginForm'])
        ->name('login');

    Route::post('/login', [CustomerAuthController::class, 'login'])
        ->name('login.submit');

    Route::post('/magic-link', [CustomerAuthController::class, 'sendMagicLink'])
        ->name('magic-link.send');

    Route::get('/magic-login/{customer}', [CustomerAuthController::class, 'consumeMagicLink'])
        ->whereNumber('customer')
        ->name('magic-login.consume');

    Route::get('/forgot-password', [CustomerAuthController::class, 'showForgotPasswordForm'])
        ->name('password.request');

    Route::post('/forgot-password', [CustomerAuthController::class, 'sendResetPasswordLink'])
        ->name('password.email');

    Route::get('/reset-password/{token}', [CustomerAuthController::class, 'showResetPasswordForm'])
        ->name('password.reset');

    Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword'])
        ->name('password.update');
});

Route::middleware('auth:customer')->group(function () {
    Route::get('/account', function () {
        if (session('agent_mode') === true && session('agent_impersonating') !== true) {
            return redirect()->route('storefront.agent.customers');
        }

        $store = app('currentStore');
        $themeResolver = app(ThemeResolver::class);

        return view($themeResolver->view('account.index', $store), [
            'store' => $store,
            'storefrontLayout' => $themeResolver->layout($store),
        ]);
    })->name('account.index');

    Route::prefix('agent')
        ->name('agent.')
        ->group(function () {
            Route::get('/customers', [AgentCustomerController::class, 'index'])
                ->name('customers');

            Route::post('/customers/{customer}/impersonate', [AgentCustomerController::class, 'impersonate'])
                ->whereNumber('customer')
                ->name('customers.impersonate');

            Route::post('/stop', [AgentCustomerController::class, 'stop'])
                ->name('stop');
        });

    Route::prefix('account/documents')
        ->name('account.documents.')
        ->group(function () {
            Route::get('/', [CustomerDocumentsController::class, 'index'])
                ->name('index');

            Route::get('/{document}', [CustomerDocumentsController::class, 'show'])
                ->name('show');
        });
});

/*
|--------------------------------------------------------------------------
| SEO CATEGORY CATCH-ALL
|--------------------------------------------------------------------------
*/

Route::get('/{slug}', [CategoryController::class, 'show'])
    ->where('slug', '^(?!admin(?:/|$)|catalog(?:/|$)|search(?:/|$)|category(?:/|$)|product(?:/|$)|cart(?:/|$)|checkout(?:/|$)|payment(?:/|$)|wishlist(?:/|$)|login(?:/|$)|logout(?:/|$)|account(?:/|$)|agent(?:/|$)|forgot-password(?:/|$)|reset-password(?:/|$)|magic-login(?:/|$)|magic-link(?:/|$)).+')
    ->name('category.show');