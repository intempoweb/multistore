<?php

namespace App\Services\Payments;

use App\Models\Cart;
use App\Models\Order;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        protected StripeService $stripe,
        protected PayPalService $paypal,
    ) {
    }

    public function createPaymentPreview(string $gateway, Cart $cart, array $totals): array
    {
        return $this->gatewayService($gateway)->createPaymentPreview($cart, $totals);
    }

    public function createCheckout(Order $order, ?string $gateway = null): array
    {
        return $this->gatewayService($gateway ?: $order->payment_gateway)->createCheckout($order);
    }

    public function retrievePayment(string $gateway, string $paymentId): array
    {
        return $this->gatewayService($gateway)->retrievePayment($paymentId);
    }

    public function capturePayment(string $gateway, string $paymentId, ?Order $order = null): array
    {
        return $this->gatewayService($gateway)->capturePayment($paymentId, $order);
    }

    public function refundPayment(
        string $gateway,
        string $paymentId,
        mixed $amount = null,
        ?string $reason = null
    ): array {
        return $this->gatewayService($gateway)->refundPayment($paymentId, $amount, $reason);
    }

    public function authorizePayment(string $gateway, string $paymentId): array
    {
        $service = $this->gatewayService($gateway);

        if (!method_exists($service, 'authorizePayment')) {
            throw new InvalidArgumentException('Authorize non supportato per questo gateway.');
        }

        return $service->authorizePayment($paymentId);
    }

    public function cancelPayment(string $gateway, string $paymentId, ?string $reason = null): array
    {
        $service = $this->gatewayService($gateway);

        if (!method_exists($service, 'cancelPayment')) {
            throw new InvalidArgumentException('Cancel non supportato per questo gateway.');
        }

        return $service->cancelPayment($paymentId, $reason);
    }

    public function capturePayPal(string $paypalOrderId, ?Order $order = null): array
    {
        return $this->capturePayment('paypal', $paypalOrderId, $order);
    }

    public function authorizePayPal(string $paypalOrderId): array
    {
        return $this->authorizePayment('paypal', $paypalOrderId);
    }

    private function gatewayService(?string $gateway): PaymentGatewayInterface
    {
        $gateway = strtolower(trim((string) $gateway));

        return match ($gateway) {
            'stripe' => $this->stripe,
            'paypal' => $this->paypal,
            default => throw new InvalidArgumentException('Gateway pagamento non valido.'),
        };
    }
}