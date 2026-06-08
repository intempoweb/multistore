@extends($storefrontLayout)

@section('title', 'Reimposta password')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8 col-xl-6">

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="text-center mb-4">
                        <h1 class="h4 mb-1">Reimposta password</h1>
                        <div class="text-muted small">Scegli una nuova password per accedere alla tua area clienti.</div>
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

                        <div class="mb-3">
                            <label for="reset_email" class="form-label">Email</label>
                            <input
                                type="email"
                                name="email"
                                id="reset_email"
                                class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email', $email ?? '') }}"
                                autocomplete="username"
                                required
                                autofocus
                            >

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

@push('scripts')
    <script>
        (function () {
            const initPasswordToggles = function () {
                const buttons = Array.from(document.querySelectorAll('[data-password-toggle]'));

                buttons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        const targetId = button.getAttribute('data-password-target');
                        const input = targetId ? document.getElementById(targetId) : null;
                        const icon = button.querySelector('[data-password-toggle-icon]');

                        if (!input) {
                            return;
                        }

                        const isPassword = input.getAttribute('type') === 'password';

                        input.setAttribute('type', isPassword ? 'text' : 'password');
                        button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                        button.setAttribute('aria-label', isPassword ? 'Nascondi password' : 'Mostra password');

                        if (icon) {
                            icon.classList.toggle('fa-eye', !isPassword);
                            icon.classList.toggle('fa-eye-slash', isPassword);
                        }
                    });
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initPasswordToggles);
            } else {
                initPasswordToggles();
            }
        })();
    </script>
@endpush