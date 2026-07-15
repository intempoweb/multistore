<?php

namespace App\Rules;

use App\Services\Security\RecaptchaVerifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Recaptcha implements ValidationRule
{
    public function __construct(private readonly string $action)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $token = is_string($value) ? $value : null;

        if (!app(RecaptchaVerifier::class)->verify($token, $this->action, request())) {
            $fail(__('validation.recaptcha'));
        }
    }
}
