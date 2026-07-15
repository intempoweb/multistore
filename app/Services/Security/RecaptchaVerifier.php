<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecaptchaVerifier
{
    public function enabled(): bool
    {
        return (bool) config('services.recaptcha.enabled')
            && filled($this->siteKey())
            && filled($this->secretKey());
    }

    public function siteKey(): ?string
    {
        $siteKey = config('services.recaptcha.site_key');

        return is_string($siteKey) && $siteKey !== '' ? $siteKey : null;
    }

    public function verify(?string $token, string $action, ?Request $request = null): bool
    {
        if (!$this->enabled()) {
            return true;
        }

        if (!is_string($token) || trim($token) === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.recaptcha.timeout', 5))
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $this->secretKey(),
                    'response' => $token,
                    'remoteip' => $request?->ip(),
                ]);
        } catch (Throwable $exception) {
            Log::warning('reCAPTCHA verification request failed.', [
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        if (!$response->successful()) {
            return false;
        }

        $payload = $response->json();

        if (!is_array($payload) || ($payload['success'] ?? false) !== true) {
            return false;
        }

        $responseAction = (string) ($payload['action'] ?? '');

        if ($responseAction !== '' && $responseAction !== $action) {
            return false;
        }

        return (float) ($payload['score'] ?? 0) >= (float) config('services.recaptcha.min_score', 0.5);
    }

    private function secretKey(): ?string
    {
        $secretKey = config('services.recaptcha.secret_key');

        return is_string($secretKey) && $secretKey !== '' ? $secretKey : null;
    }
}
