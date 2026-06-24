<div
    class="card border-0 shadow-sm mb-4"
    data-checkout-account
    data-account-status-url="{{ route('storefront.checkout.account.status') }}"
>
    <div class="card-body p-4">
        @if($customer)
            <div class="d-flex align-items-center justify-content-between gap-3">
                <div>
                    <div class="fw-semibold">Acquisto come {{ $customer->nomeconnweb ?: $customer->ragsoanag_cg16 }}</div>
                    <div class="small text-muted">{{ $customer->indemail_cg16 }}</div>
                </div>
                <span class="badge text-bg-success">Account attivo</span>
            </div>
        @else
            <div class="mb-3">
                <h5 class="mb-1">I tuoi dati</h5>
                <div class="small text-muted">
                    Inserisci l’email nei dati di spedizione. Se hai già un account potrai accedere senza lasciare il checkout.
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
                        Questa email è associata a un account. Inserisci la password per recuperare automaticamente i tuoi dati.
                    </div>

                    <label for="checkout_account_password" class="form-label">Password</label>
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
                            <span class="form-check-label small">Ricordami</span>
                        </label>
                        <a href="{{ route('storefront.password.request') }}" class="small">Password dimenticata?</a>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 mt-3" data-checkout-login-submit>
                        Accedi e recupera i dati
                    </button>
                </div>

                <div class="small text-muted d-none" data-checkout-account-guest-message>
                    Puoi continuare senza account oppure <a href="{{ route('storefront.register', ['from' => 'checkout']) }}">registrarti</a>.
                </div>
            </form>
        @endif
    </div>
</div>
