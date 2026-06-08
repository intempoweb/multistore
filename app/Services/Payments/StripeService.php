<?php

namespace App\Services\Payments;

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeService implements PaymentGatewayInterface
{
    public function createPaymentPreview(Cart $cart, array $totals): array
    {
        $amount = (int) round(((float) ($totals['grand_total'] ?? 0)) * 100);

        if ($amount <= 0) {
            throw new RuntimeException('Totale Stripe non valido.');
        }

        $response = Http::asForm()
            ->withToken($this->secret())
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => $amount,
                'currency' => $this->currency(),
                'payment_method_types[]' => 'card',
                'capture_method' => 'manual',
                'description' => 'Checkout carrello #' . $cart->id,
                'metadata[cart_id]' => (string) $cart->id,
                'metadata[store_id]' => (string) $cart->store_id,
                'metadata[source]' => 'checkout_preview',
                'metadata[capture_flow]' => 'manual_bo_stock_confirmation',
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Errore Stripe preview: ' . $response->body());
        }

        return $response->json() ?: [];
    }

    public function createCheckout(Order $order): array
    {
        $amount = $this->amountFromOrder($order);

        $response = Http::asForm()
            ->withToken($this->secret())
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => $amount,
                'currency' => $this->currency(),
                'payment_method_types[]' => 'card',
                'capture_method' => 'manual',
                'description' => 'Ordine #' . $order->order_number,
                'metadata[order_id]' => (string) $order->id,
                'metadata[order_number]' => (string) $order->order_number,
                'metadata[capture_flow]' => 'manual_bo_stock_confirmation',
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Errore Stripe: ' . $response->body());
        }

        return $response->json() ?: [];
    }

    public function retrievePayment(string $paymentId): array
    {
        $paymentId = $this->normalizePaymentId($paymentId);

        $response = Http::withToken($this->secret())
            ->get("https://api.stripe.com/v1/payment_intents/{$paymentId}");

        if (!$response->successful()) {
            throw new RuntimeException('Errore lettura Stripe: ' . $response->body());
        }

        return $response->json() ?: [];
    }

    /**
     * Acquisisce un PaymentIntent autorizzato.
     * Deve essere chiamato dal BO dopo conferma giacenza.
     */
    public function capturePayment(string $paymentId, ?Order $order = null): array
    {
        $paymentId = $this->normalizePaymentId($paymentId);

        $payment = $this->retrievePayment($paymentId);
        $status = strtolower((string) ($payment['status'] ?? ''));

        if ($status === 'succeeded') {
            return $payment;
        }

        if ($status !== 'requires_capture') {
            throw new RuntimeException('Pagamento Stripe non acquisibile. Stato attuale: ' . ($status ?: 'sconosciuto'));
        }

        $payload = [];

        if ($order instanceof Order) {
            $payload['amount_to_capture'] = $this->amountFromOrder($order);
            $payload['metadata[order_id]'] = (string) $order->id;
            $payload['metadata[order_number]'] = (string) $order->order_number;
            $payload['metadata[captured_from]'] = 'admin_bo';
        }

        $response = Http::asForm()
            ->withToken($this->secret())
            ->post("https://api.stripe.com/v1/payment_intents/{$paymentId}/capture", $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Errore capture Stripe: ' . $response->body());
        }

        return $response->json() ?: [];
    }

    /**
     * Rimborsa un pagamento Stripe già acquisito.
     * Se amount è null, Stripe rimborsa il residuo disponibile.
     */
    public function refundPayment(string $paymentId, mixed $amount = null, ?string $reason = null): array
    {
        $paymentId = $this->normalizePaymentId($paymentId);
        $payment = $this->retrievePayment($paymentId);

        $chargeId = $this->extractChargeId($payment);

        if ($chargeId === null) {
            throw new RuntimeException('Charge Stripe non trovato: impossibile rimborsare il pagamento.');
        }

        $payload = [
            'charge' => $chargeId,
            'metadata[payment_intent]' => $paymentId,
            'metadata[refunded_from]' => 'admin_bo',
        ];

        if ($amount !== null) {
            $amountInCents = (int) round((float) $amount * 100);

            if ($amountInCents <= 0) {
                throw new RuntimeException('Importo rimborso Stripe non valido.');
            }

            $payload['amount'] = $amountInCents;
        }

        if ($reason !== null && trim($reason) !== '') {
            $payload['reason'] = $this->normalizeRefundReason($reason);
        }

        $response = Http::asForm()
            ->withToken($this->secret())
            ->post('https://api.stripe.com/v1/refunds', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Errore refund Stripe: ' . $response->body());
        }

        return $response->json() ?: [];
    }

    /**
     * Cancella un PaymentIntent autorizzato ma non ancora acquisito.
     * Utile per ordine BO cancellato prima della lavorazione.
     */
    public function cancelPayment(string $paymentId, ?string $reason = null): array
    {
        $paymentId = $this->normalizePaymentId($paymentId);
        $payment = $this->retrievePayment($paymentId);
        $status = strtolower((string) ($payment['status'] ?? ''));

        if (in_array($status, ['canceled', 'cancelled'], true)) {
            return $payment;
        }

        if ($status === 'succeeded') {
            throw new RuntimeException('Pagamento Stripe già acquisito: usare refund, non cancel.');
        }

        $payload = [];

        if ($reason !== null && trim($reason) !== '') {
            $payload['cancellation_reason'] = $this->normalizeCancellationReason($reason);
        }

        $response = Http::asForm()
            ->withToken($this->secret())
            ->post("https://api.stripe.com/v1/payment_intents/{$paymentId}/cancel", $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Errore cancel Stripe: ' . $response->body());
        }

        return $response->json() ?: [];
    }

    private function amountFromOrder(Order $order): int
    {
        $amount = (int) round(((float) $order->grand_total) * 100);

        if ($amount <= 0) {
            throw new RuntimeException('Totale Stripe non valido.');
        }

        return $amount;
    }

    private function extractChargeId(array $payment): ?string
    {
        $chargeId = data_get($payment, 'latest_charge');

        if (is_string($chargeId) && trim($chargeId) !== '') {
            return trim($chargeId);
        }

        $chargeId = data_get($payment, 'charges.data.0.id');

        if (is_string($chargeId) && trim($chargeId) !== '') {
            return trim($chargeId);
        }

        return null;
    }

    private function normalizePaymentId(string $paymentId): string
    {
        $paymentId = trim($paymentId);

        if ($paymentId === '') {
            throw new RuntimeException('PaymentIntent Stripe mancante.');
        }

        return $paymentId;
    }

    private function normalizeRefundReason(string $reason): string
    {
        $reason = strtolower(trim($reason));

        return match ($reason) {
            'duplicate', 'fraudulent', 'requested_by_customer' => $reason,
            default => 'requested_by_customer',
        };
    }

    private function normalizeCancellationReason(string $reason): string
    {
        $reason = strtolower(trim($reason));

        return match ($reason) {
            'duplicate', 'fraudulent', 'requested_by_customer', 'abandoned' => $reason,
            default => 'requested_by_customer',
        };
    }

    private function secret(): string
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            throw new RuntimeException('STRIPE_SECRET non configurato.');
        }

        return (string) $secret;
    }

    private function currency(): string
    {
        return strtolower((string) config('services.stripe.currency', 'eur'));
    }
}