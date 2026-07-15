<?php

namespace App\Http\Requests\Storefront;

use App\Rules\Recaptcha;
use Illuminate\Foundation\Http\FormRequest;

class ContactInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', 'max:80'],
            'company' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:180'],
            'phone' => ['required', 'string', 'max:40'],
            'subject' => ['required', 'string', 'max:140'],
            'message' => ['required', 'string', 'max:4000'],
            'privacy_acceptance' => ['accepted'],
            'g-recaptcha-response' => [new Recaptcha('contact')],
        ];
    }
}
