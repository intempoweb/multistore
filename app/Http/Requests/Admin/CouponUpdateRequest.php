<?php

namespace App\Http\Requests\Admin;

class CouponUpdateRequest extends CouponStoreRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }
}