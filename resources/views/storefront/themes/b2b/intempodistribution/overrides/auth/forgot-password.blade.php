@extends($storefrontLayout)

@section('title', 'Recupera password')

@section('content')
@php
    $logoUrl = $store?->logo_url;
    $agentLoginVisible = false;
    $selectedAuthMode = old('auth_mode', request('auth_mode', 'customer'));
    $selectedAuthMode = $selectedAuthMode === 'agent' ? 'agent' : 'customer';

    if (! $agentLoginVisible) {
        $selectedAuthMode = 'customer';
    }
@endphp

<div class="storefront-auth-wrapper">
    <div class="storefront-auth-card">

        <div class="storefront-auth-brand">
            @if($logoUrl)
                <img
                    src="{{ $logoUrl }}"
                    alt="{{ $store->name ?? 'Store' }}"
                    class="storefront-auth-logo"
                >
            @else
                <div class="storefront-auth-logo-fallback">
                    {{ strtoupper(substr($store->name ?? 'B2B', 0, 2)) }}
                </div>
            @endif

            <div class="storefront-auth-brand-content">
                <div class="storefront-auth-eyebrow">
                    Recupero accesso
                </div>

                <h1 class="storefront-auth-title">
                    Password dimenticata?
                </h1>

                <p class="storefront-auth-subtitle">
                    Recupera l’accesso come cliente.
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
            action="{{ route('storefront.password.email') }}"
            class="storefront-auth-form"
        >
            @csrf

            @if($agentLoginVisible)
                <div class="storefront-auth-tabs nav nav-pills nav-fill mb-4" role="tablist" aria-label="Tipo accesso">
                    <button
                        type="button"
                        class="nav-link {{ $selectedAuthMode !== 'agent' ? 'active' : '' }}"
                        data-auth-mode-tab
                        data-auth-mode="customer"
                        aria-pressed="{{ $selectedAuthMode !== 'agent' ? 'true' : 'false' }}"
                    >
                        Cliente
                    </button>

                    <button
                        type="button"
                        class="nav-link {{ $selectedAuthMode === 'agent' ? 'active' : '' }}"
                        data-auth-mode-tab
                        data-auth-mode="agent"
                        aria-pressed="{{ $selectedAuthMode === 'agent' ? 'true' : 'false' }}"
                    >
                        Agente
                    </button>
                </div>
            @endif

            <input type="hidden" name="auth_mode" value="{{ $selectedAuthMode }}" data-auth-mode-input>

            <div class="mb-4">
                <label for="forgot_password_email" class="form-label" data-login-label>
                    {{ $selectedAuthMode === 'agent' ? 'Email agente' : 'Email cliente' }}
                </label>

                <input
                    type="email"
                    name="email"
                    id="forgot_password_email"
                    value="{{ old('email', $email ?? '') }}"
                    class="form-control storefront-auth-input @error('email') is-invalid @enderror"
                    placeholder="{{ $selectedAuthMode === 'agent' ? 'email agente' : 'email cliente' }}"
                    required
                    autofocus
                    autocomplete="email"
                    data-login-input
                >

                <div class="form-text mt-2" data-login-help>
                    {{ $selectedAuthMode === 'agent' ? 'Riceverai un link per reimpostare la password del tuo accesso agente.' : 'Riceverai un link per reimpostare la password del tuo account cliente.' }}
                </div>

                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button
                type="submit"
                class="btn storefront-auth-submit-btn w-100"
            >
                <i class="fa-solid fa-paper-plane me-2"></i>
                Invia link reset password
            </button>
        </form>

        <div class="storefront-auth-divider">
            <span>oppure</span>
        </div>

        <div class="text-center">
            <a
                href="{{ route('storefront.login', ['auth_mode' => $selectedAuthMode]) }}"
                class="storefront-auth-link"
            >
                <i class="fa-solid fa-arrow-left me-2"></i>
                Torna al login
            </a>
        </div>

    </div>
</div>
@endsection
