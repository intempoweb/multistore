@extends($storefrontLayout)

@section('title', 'Reimposta password')

@section('content')
@php
    $contextParams = $contextParams ?? [];
@endphp

<section class="ready-auth-page">
    <div class="ready-auth-shell">
        <aside class="ready-auth-copy">
            <p class="ready-auth-eyebrow">Nuova password</p>
            <h1>Reimposta password</h1>
            <p>Scegli una nuova password sicura per rientrare nella tua area personale Ready.</p>
            <a href="{{ route('storefront.login', $contextParams) }}">Torna al login <i data-lucide="arrow-right"></i></a>
        </aside>

        <div class="ready-auth-card">
            <div class="ready-auth-card-head">
                <p>{{ 'READY' }}</p>
                <h2>Aggiorna accesso</h2>
            </div>

            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('storefront.password.update', $contextParams) }}" class="ready-auth-form">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="auth_mode" value="customer">

                <div class="ready-auth-field">
                    <label for="reset_email">Email</label>
                    <input
                        type="email"
                        name="email"
                        id="reset_email"
                        value="{{ old('email', $email ?? '') }}"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <div class="ready-auth-field">
                    <label for="reset_password">Nuova password</label>
                    <div class="ready-password-control">
                        <input type="password" name="password" id="reset_password" autocomplete="new-password" required data-password-toggle-input>
                        <button type="button" data-password-toggle data-password-target="reset_password" aria-label="Mostra password" aria-pressed="false">
                            <i class="fa-solid fa-eye" data-password-toggle-icon></i>
                        </button>
                    </div>
                </div>

                <div class="ready-auth-field">
                    <label for="reset_password_confirmation">Conferma nuova password</label>
                    <div class="ready-password-control">
                        <input type="password" name="password_confirmation" id="reset_password_confirmation" autocomplete="new-password" required data-password-toggle-input>
                        <button type="button" data-password-toggle data-password-target="reset_password_confirmation" aria-label="Mostra password" aria-pressed="false">
                            <i class="fa-solid fa-eye" data-password-toggle-icon></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="ready-auth-submit">Reimposta password</button>
            </form>
        </div>
    </div>
</section>
@endsection
