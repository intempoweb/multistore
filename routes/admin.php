<?php

use App\Http\Controllers\Admin\AdminCatalogController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminErpSyncController;
use App\Http\Controllers\Admin\AdminLocaleController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\AttributeValueController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerVisibleGroupController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\SendcloudShipmentController;
use App\Http\Controllers\Admin\ShippingRuleController;
use App\Http\Controllers\Admin\ShippingTableImportController;
use App\Http\Controllers\Admin\StorefrontPageController;
use App\Http\Controllers\Admin\StorefrontSeoController;
use App\Http\Controllers\Admin\StoreVisibleGroupController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['web', 'store.context', 'admin.locale'])
    ->as('admin.')
    ->group(function () {
        Route::middleware('guest')->group(function () {
            Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.submit');
        });

        Route::middleware(['auth', 'admin.only'])->group(function () {
            Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('root');

            Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

            Route::post('/store/set/{store}', function (Request $request, Store $store) {
                abort_unless($store->is_active, 404);

                $user = $request->user();

                if (!$user || !method_exists($user, 'canAccessAdminStore') || !$user->canAccessAdminStore($store)) {
                    return redirect()
                        ->route('admin.dashboard')
                        ->with('warning', 'Non hai i permessi per amministrare questo store.');
                }

                $request->session()->put('admin_store_id', $store->id);

                return back();
            })->name('store.set');

            Route::controller(AdminErpSyncController::class)
                ->prefix('erp-sync')
                ->as('erp-sync.')
                ->middleware('admin.section:super')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/run', 'run')->name('run');
                });

            Route::controller(ProductController::class)
                ->prefix('products')
                ->as('products.')
                ->middleware('admin.section:super')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{product}', 'show')->name('show');
                    Route::put('/{product}', 'update')->name('update');
                    Route::delete('/{product}', 'destroy')->name('destroy');
                });

            Route::controller(AttributeController::class)
                ->prefix('attributes')
                ->as('attributes.')
                ->middleware('admin.section:super')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{attribute}', 'show')->name('show');
                    Route::put('/{attribute}', 'update')->name('update');
                    Route::delete('/{attribute}', 'destroy')->name('destroy');
                });

            Route::controller(AttributeValueController::class)
                ->prefix('attribute-values')
                ->as('attribute-values.')
                ->middleware('admin.section:super')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{attributeValue}', 'show')->name('show');
                    Route::put('/{attributeValue}', 'update')->name('update');
                    Route::delete('/{attributeValue}', 'destroy')->name('destroy');
                });

            Route::controller(AdminCatalogController::class)
                ->prefix('catalog')
                ->as('catalog.')
                ->middleware('admin.section:super')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/{fam}/{sfam?}/{gruppo?}/{sgruppo?}', 'show')->name('show');
                });

            Route::controller(StorefrontPageController::class)
                ->prefix('storefront-pages')
                ->as('storefront-pages.')
                ->middleware('admin.section:static_pages')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{storefrontPage}/edit', 'edit')->name('edit');
                    Route::put('/{storefrontPage}', 'update')->name('update');
                    Route::put('/{storefrontPage}/blocks', 'updateBlocks')->name('blocks.update');
                    Route::delete('/{storefrontPage}', 'destroy')->name('destroy');
                });

            Route::controller(StorefrontSeoController::class)
                ->prefix('storefront-seo')
                ->as('storefront-seo.')
                ->middleware('admin.section:storefront_seo')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::put('/', 'update')->name('update');
                });

            Route::controller(CustomerController::class)
                ->prefix('customers')
                ->as('customers.')
                ->middleware('admin.section:commercial')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/{customer}', 'show')->name('show');
                    Route::post('/{customer}/login-as', 'loginAsCustomer')
                        ->middleware('admin.section:b2b_impersonation')
                        ->name('login-as');
                });

            Route::controller(CustomerVisibleGroupController::class)
                ->prefix('customer-visible-groups')
                ->as('customer-visible-groups.')
                ->middleware('admin.section:commercial')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                });

            Route::controller(StoreVisibleGroupController::class)
                ->prefix('store-visible-groups')
                ->as('store-visible-groups.')
                ->middleware('admin.section:commercial')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/{id}', 'show')->name('show');
                });

            Route::controller(ShippingRuleController::class)
                ->prefix('shipping-rules')
                ->as('shipping-rules.')
                ->middleware('admin.section:super')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::post('/share', 'updateSharedStores')->name('share.update');
                    Route::get('/{shippingRule}/edit', 'edit')->name('edit');
                    Route::put('/{shippingRule}', 'update')->name('update');
                    Route::delete('/{shippingRule}', 'destroy')->name('destroy');
                });

            Route::controller(ShippingTableImportController::class)
                ->prefix('shipping-rules/import')
                ->as('shipping-rules.import.')
                ->middleware('admin.section:super')
                ->group(function () {
                    Route::post('/', 'store')->name('store');
                    Route::get('/export', 'export')->name('export');
                });

            Route::controller(PromotionController::class)
                ->prefix('promotions')
                ->as('promotions.')
                ->middleware('admin.section:super')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{promotion}/edit', 'edit')->name('edit');
                    Route::put('/{promotion}', 'update')->name('update');
                    Route::delete('/{promotion}', 'destroy')->name('destroy');
                });

            Route::controller(CouponController::class)
                ->prefix('coupons')
                ->as('coupons.')
                ->middleware('admin.section:super')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{coupon}/edit', 'edit')->name('edit');
                    Route::put('/{coupon}', 'update')->name('update');
                    Route::delete('/{coupon}', 'destroy')->name('destroy');
                });

            Route::controller(PaymentController::class)
                ->prefix('payments')
                ->as('payments.')
                ->middleware('admin.section:payments')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/{order}', 'show')->name('show');
                    Route::post('/{order}/capture', 'capture')->name('capture');
                    Route::post('/{order}/refund', 'refund')->name('refund');
                });

            Route::controller(AdminOrderController::class)
                ->prefix('orders')
                ->as('orders.')
                ->middleware('admin.section:orders')
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/{order}', 'show')->name('show');
                    Route::patch('/{order}/status', 'updateStatus')->name('status.update');
                    Route::patch('/{order}/payment-status', 'updatePaymentStatus')->name('payment-status.update');

                    Route::post('/{order}/confirm-stock', 'confirmStock')->name('confirm-stock');
                    Route::post('/{order}/capture-payment', 'capturePayment')->name('capture-payment');
                    Route::post('/{order}/refund-payment', 'refundPayment')->name('refund-payment');
                    Route::post('/{order}/mark-processing', 'markProcessing')->name('mark-processing');
                    Route::post('/{order}/mark-completed', 'markCompleted')->name('mark-completed');
                    Route::post('/{order}/export-erp', 'exportToErp')->name('export-erp');
                    Route::post('/{order}/close', 'close')->name('close');
                    Route::post('/{order}/cancel', 'cancel')->name('cancel');
                });

            Route::controller(SendcloudShipmentController::class)
                ->prefix('orders/{order}/sendcloud')
                ->as('orders.sendcloud.')
                ->middleware('admin.section:sendcloud')
                ->group(function () {
                    Route::post('/shipment', 'create')->name('shipment.create');
                    Route::post('/shipment/cancel', 'cancel')->name('shipment.cancel');
                    Route::post('/incoming-order/cancel', 'cancel')->name('incoming-order.cancel');
                    Route::get('/label', 'label')->name('label');
                });

            Route::post('/locale/{locale}', [AdminLocaleController::class, 'set'])
                ->where('locale', 'it|en|es')
                ->name('locale.set');
        });
    });
