<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class CorporateGiftInquiryRequest extends FormRequest
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
            'company' => ['required', 'string', 'max:140'],
            'product_type' => ['required', 'in:agenda,taccuino'],
            'quantity' => ['required', 'integer', 'min:100', 'max:1000000'],
            'email' => ['required', 'email:rfc', 'max:180'],
            'phone' => ['required', 'string', 'max:40'],
            'logo_file' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml,application/pdf,application/x-pdf', 'max:5120'],
            'content_attachment' => ['nullable', 'file', 'mimetypes:application/pdf,application/x-pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/rtf,application/rtf,application/zip,application/x-zip-compressed,multipart/x-zip', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'privacy_acceptance' => ['accepted'],
        ];
    }
}
