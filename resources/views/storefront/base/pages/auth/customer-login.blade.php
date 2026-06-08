@extends($storefrontLayout)

@section('title', 'Accesso clienti')

@section('content')
<div class="container py-5 customer-login-page">
    <div class="row justify-content-center align-items-center g-4">
        <div class="col-12 col-lg-5 col-xl-4">
            <div class="mb-4 text-center text-lg-start">
                @if(!empty($store?->logo_url))
                    <img src="{{ $store->logo_url }}" alt="{{ $store->name ?? 'Store' }}" class="mb-4" style="max-height: 64px; max-width: 220px; object-fit: contain;">
                @endif

                <div class="text-uppercase text-muted small fw-semibold mb-2">Area riservata B2B</div>
                <h1 class="h3 mb-3">Accedi al tuo account</h1>
                <p class="text-muted mb-0">
                    Entra con codice cliente o email e password oppure richiedi un link rapido via email.
                </p>
            </div>

            <div class="d-none d-lg-block border rounded-4 bg-white shadow-sm p-4">
                <div class="d-flex gap-3 mb-3">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Ordini B2B più veloci</div>
                        <div class="small text-muted">Consulta catalogo, listini dedicati e storico ordini dal tuo profilo cliente.</div>
                    </div>
                </div>

                <div class="d-flex gap-3">
                    <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px;">
                        <i class="fa-solid fa-link"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Accesso rapido</div>
                        <div class="small text-muted">Ricevi un link temporaneo valido {{ $magicLinkExpireMinutes }} minuti.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7 col-xl-5">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                            <i class="fa-solid fa-user-lock fa-lg"></i>
                        </div>
                        <h2 class="h4 mb-1">Login clienti</h2>
                        <div class="text-muted small">Inserisci le tue credenziali di accesso</div>
                    </div>

                    @if(session('status'))
                        <div class="alert alert-success border-0">{{ session('status') }}</div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <div class="fw-semibold mb-2">Controlla i dati inseriti:</div>
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('storefront.login.submit') }}" class="mb-4">
                        @csrf

                        <div class="mb-3">
                            <label for="customer_login" class="form-label fw-semibold" data-login-label>
                                Codice cliente o email
                            </label>

                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fa-solid fa-user text-muted" data-login-icon></i>
                                </span>

                                <input
                                    type="text"
                                    name="login"
                                    id="customer_login"
                                    class="form-control border-start-0 @error('login') is-invalid @enderror @error('email') is-invalid @enderror"
                                    value="{{ old('login', $login ?? $email ?? '') }}"
                                    placeholder="Codice cliente o nome@azienda.it"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    data-login-input
                                >
                            </div>

                            <div class="form-text" data-login-help>
                                Puoi usare codice cliente oppure email.
                            </div>

                            @error('login')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                            @error('email')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="customer_login_password" class="form-label fw-semibold">Password</label>

                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fa-solid fa-lock text-muted"></i>
                                </span>

                                <input
                                    type="password"
                                    name="password"
                                    id="customer_login_password"
                                    class="form-control border-start-0 border-end-0 @error('password') is-invalid @enderror"
                                    required
                                    autocomplete="current-password"
                                    data-password-toggle-input
                                >

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    data-password-toggle
                                    data-password-target="customer_login_password"
                                    aria-label="Mostra password"
                                    aria-pressed="false"
                                >
                                    <i class="fa-solid fa-eye" data-password-toggle-icon></i>
                                </button>
                            </div>

                            @error('password')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between align-items-center gap-3 mb-4 flex-wrap">
                            <div class="form-check">
                                <input type="checkbox" name="remember" value="1" class="form-check-input" id="remember" @checked(old('remember'))>
                                <label class="form-check-label" for="remember">Ricordami</label>
                            </div>

                            <a href="{{ route('storefront.password.request') }}" class="small text-decoration-none">
                                Hai dimenticato la password?
                            </a>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>
                            Accedi
                        </button>
                    </form>

                    <div class="position-relative my-4">
                        <hr>
                        <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 small text-muted">oppure</span>
                    </div>

                    <form method="POST" action="{{ route('storefront.magic-link.send') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="customer_magic_email" class="form-label fw-semibold">Accesso rapido via email</label>

                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fa-solid fa-wand-magic-sparkles text-muted"></i>
                                </span>

                                <input
                                    type="email"
                                    name="email"
                                    id="customer_magic_email"
                                    class="form-control border-start-0"
                                    value="{{ old('email', $email ?? '') }}"
                                    placeholder="nome@azienda.it"
                                    required
                                    autocomplete="email"
                                >
                            </div>
                        </div>

                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fa-solid fa-paper-plane me-2"></i>
                            Invia link di accesso
                        </button>

                        <div class="text-muted small mt-3 text-center">
                            Il link sarà valido per {{ $magicLinkExpireMinutes }} minuti.
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        (function () {
            const initSmartLoginInput = function () {
                const input = document.querySelector('[data-login-input]');
                const label = document.querySelector('[data-login-label]');
                const help = document.querySelector('[data-login-help]');
                const icon = document.querySelector('[data-login-icon]');

                if (!input || !label || !help || !icon) {
                    return;
                }

                const sync = function () {
                    const value = String(input.value || '').trim();
                    const isEmail = value.includes('@');

                    if (value === '') {
                        label.textContent = 'Codice cliente o email';
                        help.textContent = 'Puoi usare codice cliente oppure email.';
                        icon.className = 'fa-solid fa-user text-muted';
                        input.placeholder = 'Codice cliente o nome@azienda.it';
                        return;
                    }

                    if (isEmail) {
                        label.textContent = 'Email';
                        help.textContent = 'Accesso tramite email cliente.';
                        icon.className = 'fa-solid fa-envelope text-muted';
                        input.placeholder = 'nome@azienda.it';
                        return;
                    }

                    label.textContent = 'Codice cliente';
                    help.textContent = 'Accesso tramite codice cliente.';
                    icon.className = 'fa-solid fa-id-card text-muted';
                    input.placeholder = 'Codice cliente';
                };

                input.addEventListener('input', sync);
                sync();
            };

            const initPasswordToggles = function () {
                const buttons = Array.from(document.querySelectorAll('[data-password-toggle]'));

                buttons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        const targetId = button.getAttribute('data-password-target');
                        const input = targetId ? document.getElementById(targetId) : null;
                        const icon = button.querySelector('[data-password-toggle-icon]');

                        if (!input) {
                            return;
                        }

                        const isPassword = input.getAttribute('type') === 'password';

                        input.setAttribute('type', isPassword ? 'text' : 'password');
                        button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                        button.setAttribute('aria-label', isPassword ? 'Nascondi password' : 'Mostra password');

                        if (icon) {
                            icon.classList.toggle('fa-eye', !isPassword);
                            icon.classList.toggle('fa-eye-slash', isPassword);
                        }
                    });
                });
            };

            const init = function () {
                initSmartLoginInput();
                initPasswordToggles();
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
@endpush