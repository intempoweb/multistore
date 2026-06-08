<?php

namespace App\Services\Payments;

use App\Models\Cart;
use App\Models\Order;

interface PaymentGatewayInterface
{
    public function createPaymentPreview(Cart $cart, array $totals): array;

    public function createCheckout(Order $order): array;

    public function retrievePayment(string $paymentId): array;

    public function capturePayment(string $paymentId, ?Order $order = null): array;

    public function refundPayment(string $paymentId, mixed $amount = null, ?string $reason = null): array;
}