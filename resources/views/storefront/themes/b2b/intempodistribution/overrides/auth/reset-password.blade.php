@extends($storefrontLayout)

@section('title', 'Reimposta password')

@section('content')
@php
    $logoUrl = $store?->logo_url;
    $selectedAuthMode = old('auth_mode', $authMode ?? request('auth_mode', 'customer'));
    $selectedAuthMode = $selectedAuthMode === 'agent' ? 'agent' : 'customer';
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
                    Nuova password
                </div>

                <h1 class="storefront-auth-title">
                    Reimposta password
                </h1>

                <p class="storefront-auth-subtitle">
                    Scegli una nuova password per il tuo accesso {{ $selectedAuthMode === 'agent' ? 'agente' : 'cliente' }}.
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
            action="{{ route('storefront.password.update') }}"
            class="storefront-auth-form"
        >
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="auth_mode" value="{{ $selectedAuthMode }}">

            <div class="alert alert-light border rounded-4 mb-4">
                <strong>Tipo account:</strong>
                {{ $selectedAuthMode === 'agent' ? 'Agente' : 'Cliente' }}
            </div>

            <div class="mb-3">
                <label for="reset_email" class="form-label">
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    id="reset_email"
                    value="{{ old('email', $email ?? '') }}"
                    class="form-control storefront-auth-input @error('email') is-invalid @enderror"
                    placeholder="nome@azienda.it"
                    autocomplete="username"
                    required
                    autofocus
                >

                <div class="form-text mt-2">
                    Conferma l’indirizzo email associato all’accesso {{ $selectedAuthMode === 'agent' ? 'agente' : 'cliente' }}.
                </div>

                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="reset_password" class="form-label">
                    Nuova password
                </label>

                <div class="input-group">
                    <input
                        type="password"
                        name="password"
                        id="reset_password"
                        class="form-control storefront-auth-input @error('password') is-invalid @enderror"
                        placeholder="Inserisci nuova password"
                        autocomplete="new-password"
                        required
                        data-password-toggle-input
                    >

                    <button
                        type="button"
                        class="btn btn-outline-secondary storefront-auth-password-toggle"
                        data-password-toggle
                        data-password-target="reset_password"
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

            <div class="mb-4">
                <label for="reset_password_confirmation" class="form-label">
                    Conferma nuova password
                </label>

                <div class="input-group">
                    <input
                        type="password"
                        name="password_confirmation"
                        id="reset_password_confirmation"
                        class="form-control storefront-auth-input"
                        placeholder="Ripeti nuova password"
                        autocomplete="new-password"
                        required
                        data-password-toggle-input
                    >

                    <button
                        type="button"
                        class="btn btn-outline-secondary storefront-auth-password-toggle"
                        data-password-toggle
                        data-password-target="reset_password_confirmation"
                        aria-label="Mostra password"
                        aria-pressed="false"
                    >
                        <i class="fa-solid fa-eye" data-password-toggle-icon></i>
                    </button>
                </div>
            </div>

            <button
                type="submit"
                class="btn storefront-auth-submit-btn w-100"
            >
                <i class="fa-solid fa-key me-2"></i>
                Reimposta password
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