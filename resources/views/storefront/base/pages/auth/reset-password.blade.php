@extends($storefrontLayout)

@section('title', 'Reimposta password')

@section('content')
@php
    $selectedAuthMode = old('auth_mode', $authMode ?? request('auth_mode', 'customer'));
    $selectedAuthMode = $selectedAuthMode === 'agent' ? 'agent' : 'customer';
@endphp

<div class="container py-4 customer-auth-page">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8 col-xl-6">

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="text-center mb-4">
                        <h1 class="h4 mb-1">Reimposta password</h1>
                        <div class="text-muted small">
                            Scegli una nuova password per il tuo accesso cliente o agente.
                        </div>
                    </div>

                    @if(session('status'))
                        <div class="alert alert-light border">
                            {{ session('status') }}
                        </div>
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

                    <form method="POST" action="{{ route('storefront.password.update') }}">
                        @csrf

                        <input type="hidden" name="token" value="{{ $token }}">

                        <div class="nav nav-pills nav-fill mb-4" role="tablist" aria-label="Tipo accesso">
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

                        <input type="hidden" name="auth_mode" value="{{ $selectedAuthMode }}" data-auth-mode-input>

                        <div class="mb-3">
                            <label for="reset_email" class="form-label" data-login-label>
                                {{ $selectedAuthMode === 'agent' ? 'Email agente' : 'Email cliente' }}
                            </label>
                            <input
                                type="email"
                                name="email"
                                id="reset_email"
                                class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email', $email ?? '') }}"
                                placeholder="{{ $selectedAuthMode === 'agent' ? 'email agente' : 'email cliente' }}"
                                autocomplete="username"
                                required
                                autofocus
                                data-login-input
                            >

                            <div class="form-text" data-login-help>
                                {{ $selectedAuthMode === 'agent' ? 'Inserisci l’email agente per reimpostare la password agente.' : 'Inserisci l’email cliente per reimpostare la password cliente.' }}
                            </div>

                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="reset_password" class="form-label">Nuova password</label>
                            <div class="input-group">
                                <input
                                    type="password"
                                    name="password"
                                    id="reset_password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    autocomplete="new-password"
                                    required
                                    data-password-toggle-input
                                >
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
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

                        <div class="mb-3">
                            <label for="reset_password_confirmation" class="form-label">Conferma nuova password</label>
                            <div class="input-group">
                                <input
                                    type="password"
                                    name="password_confirmation"
                                    id="reset_password_confirmation"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                    data-password-toggle-input
                                >
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    data-password-toggle
                                    data-password-target="reset_password_confirmation"
                                    aria-label="Mostra password"
                                    aria-pressed="false"
                                >
                                    <i class="fa-solid fa-eye" data-password-toggle-icon></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            Reimposta password
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="{{ route('storefront.login') }}" class="small text-decoration-none">
                            Torna al login
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection