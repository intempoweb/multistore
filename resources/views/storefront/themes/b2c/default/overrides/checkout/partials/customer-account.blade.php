<div
    class="card border-0 shadow-sm mb-4"
    data-checkout-account
    data-account-status-url="{{ route('storefront.checkout.account.status') }}"
    data-account-exists-message="{{ __('themes_b2c.checkout.account_exists_message') }}"
    data-account-without-password-message="{{ __('themes_b2c.checkout.account_without_password_message') }}"
>
    <div class="card-body p-4">
        @if($customer)
            <div class="d-flex align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-semibold">{{ __('themes_b2c.checkout.purchase_as') }} {{ $customer->nomeconnweb ?: $customer->ragsoanag_cg16 }}</div>
                    <div class="small text-muted">{{ $customer->indemail_cg16 }}</div>
                </div>
                <span class="badge text-bg-success">{{ __('themes_b2c.checkout.account_active') }}</span>
            </div>
        @else
            <div class="mb-3">
                <h5 class="mb-1">{{ __('themes_b2c.checkout.your_details') }}</h5>
                <div class="small text-muted">
                    {{ __('themes_b2c.checkout.account_checkout_description') }}
                </div>
            </div>

            <form method="POST" action="{{ route('storefront.checkout.login') }}" id="checkout-account-login-form">
                @csrf
                <input
                    type="hidden"
                    name="email"
                    value="{{ old('checkout_login_email', $b2cShipping['email'] ?? '') }}"
                    data-checkout-login-email
                >

                <div
                    class="{{ session('checkout_account_exists') || $errors->has('checkout_password') ? '' : 'd-none' }}"
                    data-checkout-password-wrapper
                >
                    <div class="alert alert-light border small py-2 mb-3" data-checkout-account-message>
                        {{ __('themes_b2c.checkout.account_exists_message') }}
                    </div>

                    <label for="checkout_account_password" class="form-label">{{ __('themes_b2c.checkout.password') }}</label>
                    <input
                        type="password"
                        id="checkout_account_password"
                        name="password"
                        class="form-control @error('checkout_password') is-invalid @enderror"
                        autocomplete="current-password"
                        data-checkout-password
                    >
                    @error('checkout_password')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <div class="d-flex align-items-center justify-content-between gap-3 mt-3">
                        <label class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remember" value="1">
                            <span class="form-check-label small">{{ __('themes_b2c.checkout.remember_me') }}</span>
                        </label>
                        <a href="{{ route('storefront.password.request') }}" class="small">{{ __('themes_b2c.checkout.forgot_password') }}</a>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 mt-3" data-checkout-login-submit>
                        {{ __('themes_b2c.checkout.login_and_restore') }}
                    </button>
                </div>

                <div class="small text-muted d-none" data-checkout-account-guest-message>
                    {!! __('themes_b2c.checkout.guest_checkout_message', ['register_link' => '<a href="'.e(route('storefront.register', ['from' => 'checkout'])).'">'.e(__('themes_b2c.checkout.register')).'</a>']) !!}
                </div>
            </form>
        @endif
    </div>
</div>
