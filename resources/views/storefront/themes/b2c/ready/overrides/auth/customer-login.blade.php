@extends($storefrontLayout)

@section('title', 'Accedi')

@section('content')
@php
    $contextParams = $contextParams ?? [];
@endphp

<section class="ready-auth-page">
    <div class="ready-auth-shell">
        <aside class="ready-auth-copy">
            <p class="ready-auth-eyebrow">Area personale</p>
            <h1>Accedi</h1>
            <p>Gestisci ordini, preferiti e indirizzi con un accesso rapido al tuo account Ready.</p>
            <a href="{{ route('storefront.catalog.index', $contextParams) }}">Continua lo shopping <i data-lucide="arrow-right"></i></a>
        </aside>

        <div class="ready-auth-card">
            <div class="ready-auth-card-head">
                <p>{{ 'READY' }}</p>
                <h2>Bentornato</h2>
            </div>

            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('storefront.login.submit', $contextParams) }}" class="ready-auth-form">
                @csrf
                @include('storefront.base.partials.recaptcha', ['action' => 'login'])
                <input type="hidden" name="auth_mode" value="customer">

                <div class="ready-auth-field">
                    <label for="customer_login">Email</label>
                    <input
                        type="email"
                        id="customer_login"
                        name="login"
                        value="{{ old('login', $login ?? $email ?? '') }}"
                        autocomplete="username"
                        required
                        autofocus
                        class="@error('login') is-invalid @enderror @error('email') is-invalid @enderror"
                    >
                </div>

                <div class="ready-auth-field">
                    <label for="customer_login_password">Password</label>
                    <div class="ready-password-control">
                        <input
                            type="password"
                            id="customer_login_password"
                            name="password"
                            autocomplete="current-password"
                            required
                            data-password-toggle-input
                            class="@error('password') is-invalid @enderror"
                        >
                        <button
                            type="button"
                            data-password-toggle
                            data-password-target="customer_login_password"
                            aria-label="Mostra password"
                            aria-pressed="false"
                        >
                            <i class="fa-solid fa-eye" data-password-toggle-icon></i>
                        </button>
                    </div>
                </div>

                <div class="ready-auth-row">
                    <label class="ready-auth-check">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span>Ricordami</span>
                    </label>
                    <a href="{{ route('storefront.password.request', $contextParams) }}">Password dimenticata?</a>
                </div>

                <button type="submit" class="ready-auth-submit">Accedi</button>
            </form>

            <div class="ready-auth-footer-link">
                <span>Non hai un account?</span>
                <a href="{{ route('storefront.register', $contextParams) }}">Crea il tuo account</a>
            </div>
        </div>
    </div>
</section>
@endsection
