<?php

namespace App\Http\Requests\Admin;

class PromotionUpdateRequest extends PromotionStoreRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }
}