<?php

use App\Http\Controllers\Admin\SendcloudShipmentController;
use App\Http\Controllers\Storefront\PayPalWebhookController;
use App\Http\Controllers\Storefront\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/sendcloud', [SendcloudShipmentController::class, 'webhook'])
    ->name('webhooks.sendcloud');


Route::post('/webhooks/paypal', [PayPalWebhookController::class, 'handle'])
    ->name('webhooks.paypal');

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');
