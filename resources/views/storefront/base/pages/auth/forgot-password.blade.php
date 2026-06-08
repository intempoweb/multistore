@extends($storefrontLayout)

@section('title', 'Recupera password')

@section('content')
<div class="container py-5 customer-auth-page">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-7 col-xl-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-3" style="width: 56px; height: 56px;">
                            <i class="fa-solid fa-key fa-lg"></i>
                        </div>

                        <h1 class="h4 mb-1">Recupera password</h1>
                        <div class="text-muted small">
                            Inserisci la tua email cliente: ti invieremo un link per reimpostare la password.
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

                        <div>
                            <label for="forgot_password_email" class="form-label fw-semibold">Email</label>
                            <input
                                type="email"
                                name="email"
                                id="forgot_password_email"
                                class="form-control form-control-lg @error('email') is-invalid @enderror"
                                value="{{ old('email', $email ?? '') }}"
                                placeholder="nome@azienda.it"
                                autocomplete="email"
                                required
                                autofocus
                            >

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