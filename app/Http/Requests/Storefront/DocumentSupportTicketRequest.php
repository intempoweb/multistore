<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class DocumentSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:190'],
            'contact_name' => ['required', 'string', 'max:190'],
            'contact_email' => ['required', 'email:rfc', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:80'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:32'],
            'province' => ['nullable', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:5000'],
            'terms' => ['accepted'],
            'items' => ['nullable', 'array'],
            'items.*.selected' => ['nullable'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,txt',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => 'Inserisci un oggetto per il ticket.',
            'contact_name.required' => 'Inserisci il nome del referente.',
            'contact_email.required' => 'Inserisci una email di contatto.',
            'contact_email.email' => 'Inserisci una email valida.',
            'message.required' => 'Descrivi la richiesta di assistenza.',
            'terms.accepted' => 'Conferma la correttezza dei dati prima di inviare il ticket.',
            'attachments.max' => 'Puoi allegare al massimo 5 file.',
            'attachments.*.max' => 'Ogni allegato può pesare al massimo 10 MB.',
            'attachments.*.mimes' => 'Gli allegati devono essere PDF, immagini o documenti supportati.',
        ];
    }
}
