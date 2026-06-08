<?php

namespace App\Services\Payments;

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PayPalService implements PaymentGatewayInterface
{
    public function createPaymentPreview(Cart $cart, array $totals): array
    {
        $amount = $this->formatAmount($totals['grand_total'] ?? 0);

        $response = Http::timeout(30)
            ->withToken($this->accessToken())
            ->withHeaders([
                'PayPal-Request-Id' => 'cart-preview-' . $cart->id . '-' . (string) Str::uuid(),
            ])
            ->post($this->baseUrl() . '/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => 'cart_' . $cart->id,
                    'custom_id' => (string) $cart->id,
                    'amount' => [
                        'currency_code' => $this->currency(),
                        'value' => $amount,
                    ],
                ]],
                'application_context' => [
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                ],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Errore PayPal preview: ' . $this->paypalErrorMessage($response));
        }

        return $response->json() ?: [];
    }

    public function createCheckout(Order $order): array
    {
        $amount = $this->formatAmount($order->grand_total);

        $response = Http::timeout(30)
            ->withToken($this->accessToken())
            ->withHeaders([
                'PayPal-Request-Id' => 'order-checkout-' . $order->id . '-' . (string) Str::uuid(),
            ])
            ->post($this->baseUrl() . '/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'custom_id' => (string) $order->id,
                    'amount' => [
                        'currency_code' => $this->currency(),
                        'value' => $amount,
                    ],
                ]],
                'application_context' => [
                    'return_url' => route('storefront.payment.paypal.success', [
                        'order' => $order->order_number,
                    ], true),
                    'cancel_url' => route('storefront.payment.cancel', [
                        'order' => $order->order_number,
                        'gateway' => 'paypal',
                    ], true),
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                ],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Errore PayPal: ' . $this->paypalErrorMessage($response));
        }

        return $response->json() ?: [];
    }

    public function retrievePayment(string $paymentId): array
    {
        $paymentId = $this->normalizePaymentId($paymentId, 'ID pagamento PayPal mancante.');

        $response = Http::timeout(30)
            ->withToken($this->accessToken())
            ->get($this->baseUrl() . "/v2/checkout/orders/{$paymentId}");

        if (!$response->successful()) {
            throw new RuntimeException('Errore lettura PayPal: ' . $this->paypalErrorMessage($response));
        }

        return $response->json() ?: [];
    }

  public function capturePayment(string $paymentId, ?Order $order = null): array
{
    $paymentId = $this->normalizePaymentId($paymentId, 'ID ordine PayPal mancante.');
    $payment = $this->retrievePayment($paymentId);

    if ($this->extractCaptureId($payment) !== null) {
        return $payment;
    }

    $status = strtoupper((string) ($payment['status'] ?? ''));

    if ($status !== 'APPROVED') {
        throw new RuntimeException('Ordine PayPal non approvato. Stato attuale: ' . ($status ?: 'sconosciuto'));
    }

    $response = Http::timeout(30)
        ->withToken($this->accessToken())
        ->withHeaders([
            'PayPal-Request-Id' => 'capture-order-' . $paymentId . '-' . (string) Str::uuid(),
            'Prefer' => 'return=representation',
        ])
        ->post($this->baseUrl() . "/v2/checkout/orders/{$paymentId}/capture", []);

    if ($response->successful()) {
        return $response->json() ?: [];
    }

    $payload = $response->json() ?: [];
    $issue = strtoupper((string) data_get($payload, 'details.0.issue', ''));
    $name = strtoupper((string) data_get($payload, 'name', ''));

    if (
        in_array($issue, ['ORDER_ALREADY_CAPTURED', 'ORDER_ALREADY_COMPLETED'], true)
        || $name === 'UNPROCESSABLE_ENTITY'
    ) {
        $payment = $this->retrievePayment($paymentId);

        if (
            $this->extractCaptureId($payment) !== null
            || strtoupper((string) ($payment['status'] ?? '')) === 'COMPLETED'
        ) {
            return $payment;
        }
    }

    throw new RuntimeException('Errore capture PayPal: ' . $this->paypalErrorMessage($response));
}

    public function refundPayment(string $paymentId, mixed $amount = null, ?string $reason = null): array
    {
        $paymentId = $this->normalizePaymentId($paymentId, 'ID ordine PayPal mancante.');
        $payment = $this->retrievePayment($paymentId);
        $captureId = $this->extractCaptureId($payment);

        if ($captureId === null) {
            throw new RuntimeException('Capture PayPal mancante: impossibile rimborsare.');
        }

        $refundPayload = [];

        if ($reason !== null && trim($reason) !== '') {
            $refundPayload['note_to_payer'] = $this->normalizeRefundReasonForPayer($reason);
        }

        if ($amount !== null) {
            $refundPayload['amount'] = [
                'currency_code' => $this->currency(),
                'value' => $this->formatAmount($amount),
            ];
        }

        $response = Http::timeout(30)
            ->withToken($this->accessToken())
            ->withHeaders([
                'PayPal-Request-Id' => 'refund-' . $captureId . '-' . (string) Str::uuid(),
                'Prefer' => 'return=representation',
            ])
            ->post($this->baseUrl() . "/v2/payments/captures/{$captureId}/refund", $refundPayload);

        if (!$response->successful()) {
            throw new RuntimeException('Errore refund PayPal: ' . $this->paypalErrorMessage($response));
        }

        return $response->json() ?: [];
    }

    private function normalizeRefundReasonForPayer(string $reason): string
    {
        $reason = trim($reason);

        return match ($reason) {
            'sendcloud_label_cancelled' => 'Spedizione annullata: rimborso automatico ordine.',
            'requested_by_customer' => 'Rimborso richiesto dal cliente.',
            default => $reason,
        };
    }

    private function extractCaptureId(array $payment): ?string
    {
        $captureId = data_get($payment, 'purchase_units.0.payments.captures.0.id')
            ?? data_get($payment, 'payments.captures.0.id');

        $captureId = trim((string) $captureId);

        return $captureId !== '' ? $captureId : null;
    }

    private function accessToken(): string
    {
        $clientId = config('services.paypal.client_id');
        $secret = config('services.paypal.client_secret');

        if (!$clientId || !$secret) {
            throw new RuntimeException('Credenziali PayPal non configurate.');
        }

        $response = Http::asForm()
            ->timeout(30)
            ->withBasicAuth($clientId, $secret)
            ->post($this->baseUrl() . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Errore token PayPal: ' . $this->paypalErrorMessage($response));
        }

        $token = trim((string) $response->json('access_token'));

        if ($token === '') {
            throw new RuntimeException('Token PayPal mancante.');
        }

        return $token;
    }

    private function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function currency(): string
    {
        return strtoupper((string) config('services.paypal.currency', 'EUR'));
    }

    private function formatAmount(mixed $amount): string
    {
        $amount = number_format((float) $amount, 2, '.', '');

        if ((float) $amount <= 0) {
            throw new RuntimeException('Totale PayPal non valido.');
        }

        return $amount;
    }

    private function normalizePaymentId(string $paymentId, string $message): string
    {
        $paymentId = trim($paymentId);

        if ($paymentId === '') {
            throw new RuntimeException($message);
        }

        return $paymentId;
    }

    private function paypalErrorMessage(Response $response): string
    {
        $payload = $response->json();

        if (is_array($payload)) {
            $message = data_get($payload, 'message')
                ?: data_get($payload, 'details.0.description')
                ?: data_get($payload, 'details.0.issue')
                ?: data_get($payload, 'name');

            if (is_string($message) && trim($message) !== '') {
                return trim($message) . ' [' . $response->status() . ']';
            }
        }

        return trim($response->body()) !== ''
            ? trim($response->body()) . ' [' . $response->status() . ']'
            : 'Errore HTTP ' . $response->status();
    }
}