@extends($storefrontLayout)

@section('title', 'Accesso clienti')

@section('content')
@php
    $currentAuthMode = old('auth_mode', 'customer') === 'agent' ? 'agent' : 'customer';
@endphp

<div class="container py-5 customer-login-page">
    <div class="row justify-content-center align-items-center g-4">
        <div class="col-12 col-lg-5 col-xl-4">
            <div class="mb-4 text-center text-lg-start">
                @if(!empty($store?->logo_url))
                    <img
                        src="{{ $store->logo_url }}"
                        alt="{{ $store->name ?? 'Store' }}"
                        class="storefront-auth-logo mb-4"
                    >
                @endif

                <div class="text-uppercase text-muted small fw-semibold mb-2">Area riservata B2B</div>
                <h1 class="h3 mb-3">Accedi al tuo account</h1>
                <p class="text-muted mb-0">
                    Accedi come cliente con codice cliente o email cliente. Se sei un agente, usa la scheda dedicata.
                </p>
            </div>

            <div class="d-none d-lg-block border rounded-4 bg-white shadow-sm p-4">
                <div class="d-flex gap-3 mb-3">
                    <div class="storefront-icon-42 rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center flex-shrink-0">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Accesso cliente</div>
                        <div class="small text-muted">Consulta catalogo, listini dedicati e storico ordini dal tuo profilo cliente.</div>
                    </div>
                </div>

                <div class="d-flex gap-3">
                    <div class="storefront-icon-42 rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Accesso agente</div>
                        <div class="small text-muted">Gli agenti accedono con email agente e password agente, poi scelgono il cliente assegnato.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7 col-xl-5">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="storefront-icon-56 rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3">
                            <i class="fa-solid fa-user-lock fa-lg"></i>
                        </div>
                        <h2 class="h4 mb-1">Login area riservata</h2>
                        <div class="text-muted small">Scegli il tipo di accesso e inserisci le credenziali corrette</div>
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

                    <div class="nav nav-pills nav-fill mb-4" role="tablist" aria-label="Tipo accesso">
                        <button
                            type="button"
                            class="nav-link {{ $currentAuthMode !== 'agent' ? 'active' : '' }}"
                            data-auth-mode-tab
                            data-auth-mode="customer"
                            aria-pressed="{{ $currentAuthMode !== 'agent' ? 'true' : 'false' }}"
                        >
                            Cliente
                        </button>

                        <button
                            type="button"
                            class="nav-link {{ $currentAuthMode === 'agent' ? 'active' : '' }}"
                            data-auth-mode-tab
                            data-auth-mode="agent"
                            aria-pressed="{{ $currentAuthMode === 'agent' ? 'true' : 'false' }}"
                        >
                            Agente
                        </button>
                    </div>

                    <form method="POST" action="{{ route('storefront.login.submit') }}" class="mb-4">
                        @csrf

                        <input type="hidden" name="auth_mode" value="{{ $currentAuthMode }}" data-auth-mode-input>

                        <div class="mb-3">
                            <label for="customer_login" class="form-label fw-semibold" data-login-label>
                                {{ $currentAuthMode === 'agent' ? 'Email agente' : 'Codice cliente o email cliente' }}
                            </label>

                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fa-solid {{ $currentAuthMode === 'agent' ? 'fa-user-tie' : 'fa-user' }} text-muted" data-login-icon></i>
                                </span>

                                <input
                                    type="text"
                                    name="login"
                                    id="customer_login"
                                    class="form-control border-start-0 @error('login') is-invalid @enderror @error('email') is-invalid @enderror"
                                    value="{{ old('login', $login ?? $email ?? '') }}"
                                    placeholder="{{ $currentAuthMode === 'agent' ? 'email agente' : 'Codice cliente o email cliente' }}"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    data-login-input
                                >
                            </div>

                            <div class="form-text" data-login-help>
                                {{ $currentAuthMode === 'agent' ? 'Usa la tua email agente e la password agente.' : 'Usa codice cliente oppure email cliente.' }}
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
                                    placeholder="Inserisci password"
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
                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                    class="form-check-input"
                                    id="remember"
                                    @checked(old('remember'))
                                >
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

                        <input type="hidden" name="auth_mode" value="{{ $currentAuthMode }}" data-magic-auth-mode-input>

                        <div class="mb-3">
                            <label for="customer_magic_email" class="form-label fw-semibold" data-magic-email-label>
                                {{ $currentAuthMode === 'agent' ? 'Accesso rapido via email agente' : 'Accesso rapido via email cliente' }}
                            </label>

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
                                    placeholder="{{ $currentAuthMode === 'agent' ? 'email agente' : 'email cliente' }}"
                                    required
                                    autocomplete="email"
                                    data-magic-email-input
                                >
                            </div>
                        </div>

                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fa-solid fa-paper-plane me-2"></i>
                            Invia link di accesso
                        </button>

                        <div class="text-muted small mt-3 text-center" data-magic-email-help data-expire-minutes="{{ $magicLinkExpireMinutes ?? 30 }}">
                            Il link {{ $currentAuthMode === 'agent' ? 'agente' : 'cliente' }} sarà valido per {{ $magicLinkExpireMinutes ?? 30 }} minuti.
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
