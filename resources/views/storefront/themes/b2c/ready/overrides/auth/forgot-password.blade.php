@extends($storefrontLayout)

@section('title', 'Recupera password')

@section('content')
@php
    $contextParams = $contextParams ?? [];
@endphp

<section class="ready-auth-page">
    <div class="ready-auth-shell">
        <aside class="ready-auth-copy">
            <p class="ready-auth-eyebrow">Recupero accesso</p>
            <h1>Password dimenticata?</h1>
            <p>Inserisci l'email del tuo account: se esiste ed e abilitato riceverai un link per reimpostare la password.</p>
            <a href="{{ route('storefront.login', $contextParams) }}">Torna al login <i data-lucide="arrow-right"></i></a>
        </aside>

        <div class="ready-auth-card">
            <div class="ready-auth-card-head">
                <p>{{ $store->name ?? 'READY' }}</p>
                <h2>Recupera password</h2>
            </div>

            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('storefront.password.email', $contextParams) }}" class="ready-auth-form">
                @csrf
                @include('storefront.base.partials.recaptcha', ['action' => 'forgot_password'])
                <input type="hidden" name="auth_mode" value="customer">

                <div class="ready-auth-field">
                    <label for="forgot_password_email">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="forgot_password_email"
                        value="{{ old('email', $email ?? '') }}"
                        autocomplete="email"
                        required
                        autofocus
                    >
                </div>

                <button type="submit" class="ready-auth-submit">Invia link reset password</button>
            </form>

            <div class="ready-auth-footer-link">
                <span>Ti e tornata in mente?</span>
                <a href="{{ route('storefront.login', $contextParams) }}">Accedi</a>
            </div>
        </div>
    </div>
</section>
@endsection
