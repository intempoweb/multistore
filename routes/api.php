<?php

use App\Http\Controllers\Admin\SendcloudShipmentController;
use App\Http\Controllers\Storefront\PayPalWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/sendcloud', [SendcloudShipmentController::class, 'webhook'])
    ->name('webhooks.sendcloud');


Route::post('/webhooks/paypal', [PayPalWebhookController::class, 'handle'])
    ->name('webhooks.paypal');