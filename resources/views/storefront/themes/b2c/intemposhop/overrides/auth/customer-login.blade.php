@extends($storefrontLayout)

@section('title', 'Accedi')

@section('content')
@php
    $contextParams = $contextParams ?? [];
@endphp

<section class="intempo-b2c-auth-page" aria-labelledby="intempo-b2c-login-title">
    <div class="intempo-b2c-auth-shell">
        <aside class="intempo-b2c-auth-intro">
            <p class="intempo-b2c-eyebrow">Area personale</p>
            <h1 id="intempo-b2c-login-title">Accedi</h1>
            <p>Entra nel tuo account Intempo per gestire ordini, preferiti e dati personali.</p>
            <a href="{{ route('storefront.catalog.index', $contextParams) }}">
                Continua lo shopping
                <i data-lucide="arrow-right" aria-hidden="true"></i>
            </a>
        </aside>

        <div class="intempo-b2c-auth-card">
            <div class="intempo-b2c-auth-card-head">
                <p>INTEMPO</p>
                <h2>Bentornato</h2>
            </div>

            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('storefront.login.submit', $contextParams) }}" class="intempo-b2c-auth-form">
                @csrf
                @include('storefront.base.partials.recaptcha', ['action' => 'login'])
                <input type="hidden" name="auth_mode" value="customer">

                <div class="intempo-b2c-auth-field">
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

                <div class="intempo-b2c-auth-field">
                    <label for="customer_login_password">Password</label>
                    <input
                        type="password"
                        id="customer_login_password"
                        name="password"
                        autocomplete="current-password"
                        required
                        class="@error('password') is-invalid @enderror"
                    >
                </div>

                <div class="intempo-b2c-auth-row">
                    <label class="intempo-b2c-auth-check">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span>Ricordami</span>
                    </label>
                    <a href="{{ route('storefront.password.request', $contextParams) }}">Password dimenticata?</a>
                </div>

                <button type="submit" class="intempo-b2c-auth-submit">
                    Accedi
                    <i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i>
                </button>
            </form>

            <div class="intempo-b2c-auth-footer-link">
                <span>Non hai un account?</span>
                <a href="{{ route('storefront.register', $contextParams) }}">Crea il tuo account</a>
            </div>
        </div>
    </div>
</section>
@endsection
