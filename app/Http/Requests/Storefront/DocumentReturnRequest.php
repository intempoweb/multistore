<?php

namespace App\Http\Requests\Storefront;

use App\Rules\Recaptcha;
use Illuminate\Foundation\Http\FormRequest;

class DocumentReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:190'],
            'contact_email' => ['required', 'email:rfc', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:80'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:32'],
            'province' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['accepted'],
            'items' => ['required', 'array'],
            'items.*.selected' => ['nullable'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:190'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,txt',
            ],
            'g-recaptcha-response' => [new Recaptcha('document_return')],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_name.required' => 'Inserisci il nome del referente.',
            'contact_email.required' => 'Inserisci una email di contatto.',
            'contact_email.email' => 'Inserisci una email valida.',
            'terms.accepted' => 'Conferma la correttezza dei dati prima di inviare la richiesta.',
            'items.required' => 'Seleziona almeno una riga documento.',
            'attachments.max' => 'Puoi allegare al massimo 5 file.',
            'attachments.*.max' => 'Ogni allegato può pesare al massimo 10 MB.',
            'attachments.*.mimes' => 'Gli allegati devono essere PDF, immagini o documenti supportati.',
        ];
    }
}
