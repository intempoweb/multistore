@extends($storefrontLayout)

@section('title', 'Recupera password')

@section('content')
@php
    $selectedAuthMode = old('auth_mode', $authMode ?? request('auth_mode', 'customer')) === 'agent' ? 'agent' : 'customer';
@endphp

<div class="container py-5 customer-auth-page">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-7 col-xl-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="storefront-icon-56 d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-3">
                            <i class="fa-solid fa-key fa-lg"></i>
                        </div>

                        <h1 class="h4 mb-1">Recupera password</h1>
                        <div class="text-muted small">
                            Scegli se recuperare la password cliente o agente.
                        </div>
                    </div>

                    @if(session('status'))
                        <div class="alert alert-success border-0">
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

                    <form method="POST" action="{{ route('storefront.password.email') }}" class="d-flex flex-column gap-3">
                        @csrf
                        @include('storefront.base.partials.recaptcha', ['action' => 'forgot_password'])

                        <div class="nav nav-pills nav-fill mb-2" role="tablist" aria-label="Tipo accesso">
                            <button
                                type="button"
                                class="nav-link {{ $selectedAuthMode === 'customer' ? 'active' : '' }}"
                                data-auth-mode-tab
                                data-auth-mode="customer"
                                aria-pressed="{{ $selectedAuthMode === 'customer' ? 'true' : 'false' }}"
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

                        <div>
                            <label for="forgot_password_email" class="form-label fw-semibold" data-login-label>
                                {{ $selectedAuthMode === 'agent' ? 'Email agente' : 'Email cliente' }}
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="forgot_password_email"
                                class="form-control form-control-lg @error('email') is-invalid @enderror"
                                value="{{ old('email', $email ?? '') }}"
                                placeholder="{{ $selectedAuthMode === 'agent' ? 'email agente' : 'email cliente' }}"
                                autocomplete="email"
                                required
                                autofocus
                                data-login-input
                            >

                            <div class="form-text mt-2" data-login-help>
                                {{ $selectedAuthMode === 'agent'
                                    ? 'Riceverai un link per impostare o reimpostare la password agente.'
                                    : 'Riceverai un link per reimpostare la password del tuo account cliente.' }}
                            </div>

                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fa-solid fa-envelope me-2"></i>
                            Invia link reset password
                        </button>
                    </form>

                    <div class="border-top mt-4 pt-4 text-center">
                        <a href="{{ route('storefront.login') }}" class="text-decoration-none fw-semibold">
                            <i class="fa-solid fa-arrow-left me-2"></i>
                            Torna al login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
