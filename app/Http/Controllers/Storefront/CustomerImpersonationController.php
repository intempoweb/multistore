<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CustomerImpersonationToken;
use Illuminate\Support\Facades\Auth;

class CustomerImpersonationController extends Controller
{
    public function handle(string $token)
    {
        $hash = hash('sha256', $token);

        $record = CustomerImpersonationToken::where('token_hash', $hash)->first();

        if (!$record || !$record->isValid()) {
            abort(404);
        }

        // login customer (guard customer)
        Auth::guard('customer')->loginUsingId($record->customer_id);

        // marca token come usato
        $record->update([
            'used_at' => now(),
        ]);

        return redirect()->route('storefront.account.index');
    }
}