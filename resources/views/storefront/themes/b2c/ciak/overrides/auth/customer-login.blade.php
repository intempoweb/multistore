@extends($storefrontLayout)

@section('title', __('Accedi') . ' | ' . ($store->name ?? 'CIAK'))

@section('content')
<section class="ciak-auth-section">
    <div class="ciak-auth-intro">
        <span>{{ __('Il tuo spazio CIAK') }}</span>
        <h1>{{ __('Bentornato.') }}</h1>
        <p>{{ __('Accedi per ritrovare i prodotti salvati, seguire i tuoi ordini e acquistare più velocemente.') }}</p>
    </div>

    <div class="ciak-auth-panel">
        <div class="ciak-auth-panel-heading">
            <h2>{{ __('Accedi') }}</h2>
            <p>{{ __('Inserisci email e password del tuo account.') }}</p>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('storefront.login.submit') }}" class="ciak-auth-form">
            @csrf
            <input type="hidden" name="auth_mode" value="customer">
            <div>
                <label for="ciak_login">{{ __('Email') }}</label>
                <input type="email" id="ciak_login" name="login" value="{{ old('login', $login ?? '') }}" required autofocus autocomplete="email">
            </div>
            <div>
                <div class="ciak-auth-label-row">
                    <label for="ciak_password">{{ __('Password') }}</label>
                    <a href="{{ route('storefront.password.request') }}">{{ __('Password dimenticata?') }}</a>
                </div>
                <input type="password" id="ciak_password" name="password" required autocomplete="current-password">
            </div>
            <label class="ciak-auth-check"><input type="checkbox" name="remember" value="1"> <span>{{ __('Resta connesso') }}</span></label>
            <button type="submit" class="ciak-auth-submit">{{ __('Accedi') }} <i class="fa-solid fa-arrow-right"></i></button>
        </form>

        <div class="ciak-auth-switch">
            <span>{{ __('Non hai ancora un account?') }}</span>
            <a href="{{ route('storefront.register') }}">{{ __('Registrati') }}</a>
        </div>
    </div>
</section>
@endsection
