<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class CustomerImpersonationController extends Controller
{
    public function handle(string $token): RedirectResponse
    {
        abort(404);
    }
}