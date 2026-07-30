@extends($storefrontLayout)

@section('title', 'Crea account')

@section('content')
@php
    $contextParams = $contextParams ?? [];
@endphp

<section class="ready-auth-page">
    <div class="ready-auth-shell">
        <aside class="ready-auth-copy">
            <p class="ready-auth-eyebrow">Nuovo account</p>
            <h1>Crea il tuo account</h1>
            <p>Salva preferiti, completa gli acquisti piu velocemente e ritrova i tuoi ordini Ready.</p>
            <a href="{{ route('storefront.catalog.index', $contextParams) }}">Scopri il catalogo <i data-lucide="arrow-right"></i></a>
        </aside>

        <div class="ready-auth-card">
            <div class="ready-auth-card-head">
                <p>{{ $store->name ?? 'READY' }}</p>
                <h2>Registrati</h2>
            </div>

            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('storefront.register.submit', $contextParams) }}" class="ready-auth-form">
                @csrf
                @include('storefront.base.partials.recaptcha', ['action' => 'register'])

                <div class="ready-auth-grid">
                    <div class="ready-auth-field">
                        <label for="first_name">Nome</label>
                        <input id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" required>
                    </div>

                    <div class="ready-auth-field">
                        <label for="last_name">Cognome</label>
                        <input id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" required>
                    </div>
                </div>

                <div class="ready-auth-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                </div>

                <div class="ready-auth-field">
                    <label for="password">Password</label>
                    <div class="ready-password-control">
                        <input type="password" id="password" name="password" autocomplete="new-password" required data-password-toggle-input>
                        <button type="button" data-password-toggle data-password-target="password" aria-label="Mostra password" aria-pressed="false">
                            <i class="fa-solid fa-eye" data-password-toggle-icon></i>
                        </button>
                    </div>
                </div>

                <div class="ready-auth-field">
                    <label for="password_confirmation">Conferma password</label>
                    <div class="ready-password-control">
                        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required data-password-toggle-input>
                        <button type="button" data-password-toggle data-password-target="password_confirmation" aria-label="Mostra password" aria-pressed="false">
                            <i class="fa-solid fa-eye" data-password-toggle-icon></i>
                        </button>
                    </div>
                </div>

                <label class="ready-auth-check">
                    <input type="checkbox" name="privacy" value="1" required>
                    <span>Accetto l'informativa privacy e le condizioni di utilizzo.</span>
                </label>

                <button type="submit" class="ready-auth-submit">Crea account</button>
            </form>

            <div class="ready-auth-footer-link">
                <span>Hai gia un account?</span>
                <a href="{{ route('storefront.login', $contextParams) }}">Accedi</a>
            </div>
        </div>
    </div>
</section>
@endsection
