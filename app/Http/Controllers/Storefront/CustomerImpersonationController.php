<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerImpersonationToken;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerImpersonationController extends Controller
{
    public function handle(Request $request, string $token): RedirectResponse
    {
        $tokenHash = hash('sha256', $token);

        /** @var CustomerImpersonationToken|null $impersonationToken */
        $impersonationToken = CustomerImpersonationToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$impersonationToken instanceof CustomerImpersonationToken) {
            abort(404);
        }

        /** @var Customer|null $customer */
        $customer = Customer::query()
            ->where('id', (int) $impersonationToken->customer_id)
            ->where('is_active', true)
            ->first();

        if (!$customer instanceof Customer || !$customer->canReceiveMagicLink()) {
            abort(404);
        }

        /** @var Store|null $store */
        $store = Store::query()
            ->where('id', (int) $impersonationToken->store_id)
            ->where('is_active', true)
            ->first();

        if (!$store instanceof Store) {
            abort(404);
        }

        if (!$store->is_b2b || (int) $customer->ditta_cg18 !== (int) $store->ditta_cg18) {
            abort(404);
        }

        if ($customer->account_origin === 'storefront' || (int) ($customer->clifor_cg44 ?? 0) <= 0) {
            abort(404);
        }

        /** @var Store|null $currentStore */
        $currentStore = app()->bound('currentStore') ? app('currentStore') : null;

        if ($currentStore instanceof Store && (int) $currentStore->id !== (int) $store->id) {
            $targetUrl = rtrim((string) $store->domain, '/');

            if (!preg_match('#^https?://#i', $targetUrl)) {
                $targetUrl = $request->getScheme() . '://' . $targetUrl;
            }

            return redirect()->away($targetUrl . '/customer-impersonation/' . $token);
        }

        $impersonationToken->forceFill([
            'used_at' => now(),
        ])->save();

        Auth::guard('customer')->logout();
        $request->session()->forget([
            'agent_mode',
            'agent_customer_id',
            'agent_customer_name',
            'agent_contexts',
        ]);
        $request->session()->regenerate();

        Auth::guard('customer')->login($customer, false);

        $request->session()->put('admin_impersonation', true);
        $request->session()->put('admin_impersonation_customer_id', (int) $customer->id);
        $request->session()->put('admin_impersonation_admin_user_id', (int) $impersonationToken->admin_user_id);
        $request->session()->put('admin_impersonation_store_id', (int) $store->id);
        $request->session()->put('admin_impersonation_started_at', now()->toDateTimeString());

        $request->session()->regenerateToken();

        return redirect()->route('storefront.home');
    }
}
