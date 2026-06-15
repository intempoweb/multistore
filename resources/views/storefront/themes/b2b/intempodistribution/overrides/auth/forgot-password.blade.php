@extends($storefrontLayout)

@section('title', 'Recupera password')

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
                    alt="{{ $store->name }}"
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
                    Scegli se recuperare l’accesso come cliente o come agente.
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

            <div class="storefront-auth-tabs nav nav-pills nav-fill mb-4" role="tablist" aria-label="Tipo accesso">
                <button
                    type="button"
                    class="nav-link {{ old('auth_mode', 'customer') !== 'agent' ? 'active' : '' }}"
                    data-auth-mode-tab
                    data-auth-mode="customer"
                    aria-pressed="{{ old('auth_mode', 'customer') !== 'agent' ? 'true' : 'false' }}"
                >
                    Cliente
                </button>

                <button
                    type="button"
                    class="nav-link {{ old('auth_mode', 'customer') === 'agent' ? 'active' : '' }}"
                    data-auth-mode-tab
                    data-auth-mode="agent"
                    aria-pressed="{{ old('auth_mode', 'customer') === 'agent' ? 'true' : 'false' }}"
                >
                    Agente
                </button>
            </div>

            <input type="hidden" name="auth_mode" value="{{ old('auth_mode', 'customer') }}" data-auth-mode-input>

            <div class="mb-4">
                <label class="form-label" data-login-label>
                    Email cliente
                </label>

                <input
                    type="email"
                    name="email"
                    value="{{ old('email', $email ?? '') }}"
                    class="form-control storefront-auth-input"
                    placeholder="email cliente"
                    required
                    autofocus
                    autocomplete="email"
                    data-login-input
                >

                <div class="form-text mt-2" data-login-help>
                    Riceverai un link per reimpostare la password del tuo account cliente.
                </div>
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
                href="{{ route('storefront.login') }}"
                class="storefront-auth-link"
            >
                <i class="fa-solid fa-arrow-left me-2"></i>
                Torna al login
            </a>
        </div>

    </div>
</div>
@endsection