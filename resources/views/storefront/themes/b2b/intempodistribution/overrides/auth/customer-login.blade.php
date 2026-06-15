{{-- resources/views/storefront/themes/b2b/intempodistribution/overrides/auth/customer-login.blade.php --}}

@extends($storefrontLayout)

@section('title', 'Accesso clienti')

@section('content')
@php

    $logoUrl = $store?->logo_url;

@endphp

<div class="storefront-auth-wrapper">
    <div class="storefront-auth-card">

        <div class="storefront-auth-brand">
            @if($logoUrl)
                <img
                    src="{{ $logoUrl }}"
                    alt="{{ $store->name ?? 'Store' }}"
                    class="storefront-auth-logo text-center mx-auto"
                >
            @else
                <div class="storefront-auth-logo-fallback">
                    {{ strtoupper(substr($store->name ?? 'B2B', 0, 2)) }}
                </div>
            @endif

            <div class="storefront-auth-brand-content">
                <div class="storefront-auth-eyebrow">
                    Area clienti
                </div>

                <h1 class="storefront-auth-title">
                    Accedi al portale
                </h1>

                <p class="storefront-auth-subtitle">
                    Accedi con codice cliente o email cliente. Se sei un agente, usa la scheda dedicata.
                </p>
            </div>
        </div>

        @if(session('status'))
            <div class="alert alert-success rounded-4 border-0 mb-4">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger rounded-4 border-0 mb-4">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('storefront.login.submit') }}"
            class="storefront-auth-form"
        >
            @csrf

            <div class="storefront-auth-tabs nav nav-pills nav-fill mb-4" role="tablist" aria-label="Tipo accesso">
                <button
                    type="button"
                    class="nav-link active"
                    data-auth-mode-tab
                    data-auth-mode="customer"
                    aria-pressed="true"
                >
                    Cliente
                </button>

                <button
                    type="button"
                    class="nav-link"
                    data-auth-mode-tab
                    data-auth-mode="agent"
                    aria-pressed="false"
                >
                    Agente
                </button>
            </div>

            <input type="hidden" name="auth_mode" value="customer" data-auth-mode-input>

            <div class="mb-3">
                <label for="customer_login" class="form-label" data-login-label>
                    Codice cliente o email cliente
                </label>

                <input
                    type="text"
                    name="login"
                    id="customer_login"
                    value="{{ old('login', $login ?? '') }}"
                    class="form-control storefront-auth-input"
                    placeholder="Codice cliente o email cliente"
                    required
                    autofocus
                    autocomplete="username"
                    data-login-input
                >

                <div class="form-text mt-2" data-login-help>
                    Usa le credenziali del tuo account cliente.
                </div>
            </div>

            <div class="mb-4">
                <label for="customer_login_password" class="form-label">
                    Password
                </label>

                <div class="input-group">
                    <input
                        type="password"
                        name="password"
                        id="customer_login_password"
                        class="form-control storefront-auth-input"
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
            </div>

            <div class="d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap">
                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="remember"
                        id="remember"
                        value="1"
                        @checked(old('remember'))
                    >

                    <label class="form-check-label" for="remember">
                        Ricordami
                    </label>
                </div>

                <a
                    href="{{ route('storefront.password.request') }}"
                    class="storefront-auth-link"
                >
                    Password dimenticata?
                </a>
            </div>

            <button
                type="submit"
                class="btn storefront-auth-submit-btn w-100"
            >
                <i class="fa-solid fa-right-to-bracket me-2"></i>
                Accedi
            </button>
        </form>

        <div class="storefront-auth-divider">
            <span>oppure</span>
        </div>

        <form
            method="POST"
            action="{{ route('storefront.magic-link.send') }}"
            class="storefront-auth-magic-form"
        >
            @csrf

            <div class="mb-3">
                <label for="customer_magic_email" class="form-label">
                    Accesso rapido via email
                </label>

                <input
                    type="email"
                    name="email"
                    id="customer_magic_email"
                    value="{{ old('email', $email ?? '') }}"
                    class="form-control storefront-auth-input"
                    placeholder="Inserisci la tua email"
                    required
                    autocomplete="email"
                >
            </div>

            <button
                type="submit"
                class="btn btn-outline-dark w-100 rounded-4 py-3 fw-semibold"
            >
                <i class="fa-regular fa-envelope me-2"></i>
                Ricevi magic link
            </button>

            <div class="storefront-auth-note">
                Il link di accesso scade dopo {{ $magicLinkExpireMinutes ?? 30 }} minuti.
            </div>
        </form>

    </div>
</div>
@endsection

@push('scripts')
    <script>
        (function () {
            const initAuthModeTabs = function () {
                const tabs = Array.from(document.querySelectorAll('[data-auth-mode-tab]'));
                const modeInput = document.querySelector('[data-auth-mode-input]');
                const loginLabel = document.querySelector('[data-login-label]');
                const loginInput = document.querySelector('[data-login-input]');
                const loginHelp = document.querySelector('[data-login-help]');

                if (!tabs.length || !modeInput || !loginLabel || !loginInput || !loginHelp) {
                    return;
                }

                const content = {
                    customer: {
                        label: 'Codice cliente o email cliente',
                        placeholder: 'Codice cliente o email cliente',
                        help: 'Usa le credenziali del tuo account cliente.',
                    },
                    agent: {
                        label: 'Email agente',
                        placeholder: 'Inserisci email agente',
                        help: 'Gli agenti saranno indirizzati all’elenco clienti assegnati.',
                    },
                };

                const setMode = function (mode) {
                    const selected = content[mode] ? mode : 'customer';

                    modeInput.value = selected;
                    loginLabel.textContent = content[selected].label;
                    loginInput.placeholder = content[selected].placeholder;
                    loginHelp.textContent = content[selected].help;

                    tabs.forEach(function (tab) {
                        const isActive = tab.getAttribute('data-auth-mode') === selected;

                        tab.classList.toggle('active', isActive);
                        tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    });
                };

                tabs.forEach(function (tab) {
                    tab.addEventListener('click', function () {
                        setMode(tab.getAttribute('data-auth-mode'));
                    });
                });

                setMode(modeInput.value || 'customer');
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
                initAuthModeTabs();
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