@extends($storefrontLayout)

@section('title', __('Registrati') . ' | ' . ($store->name ?? 'CIAK'))

@section('content')
<section class="ciak-auth-section">
    <div class="ciak-auth-intro">
        <span>{{ __('Account CIAK') }}</span>
        <h1>{{ __('Inizia da qui.') }}</h1>
        <p>{{ __('Crea il tuo account per salvare i preferiti e avere i tuoi acquisti sempre a portata di mano.') }}</p>
    </div>

    <div class="ciak-auth-panel">
        <div class="ciak-auth-panel-heading">
            <h2>{{ __('Crea account') }}</h2>
            <p>{{ __('Bastano pochi dati per iniziare.') }}</p>
        </div>

        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('storefront.register.submit') }}" class="ciak-auth-form">
            @csrf
            <div class="ciak-auth-two-columns">
                <div><label for="first_name">{{ __('Nome') }}</label><input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name"></div>
                <div><label for="last_name">{{ __('Cognome') }}</label><input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name"></div>
            </div>
            <div><label for="register_email">{{ __('Email') }}</label><input type="email" id="register_email" name="email" value="{{ old('email') }}" required autocomplete="email"></div>
            <div><label for="register_password">{{ __('Password') }}</label><input type="password" id="register_password" name="password" required autocomplete="new-password"></div>
            <div><label for="password_confirmation">{{ __('Conferma password') }}</label><input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div>
            <label class="ciak-auth-check"><input type="checkbox" name="privacy" value="1" required> <span>{{ __('Accetto l’informativa privacy e le condizioni di utilizzo.') }}</span></label>
            <button type="submit" class="ciak-auth-submit">{{ __('Crea account') }} <i class="fa-solid fa-arrow-right"></i></button>
        </form>

        <div class="ciak-auth-switch"><span>{{ __('Hai già un account?') }}</span><a href="{{ route('storefront.login') }}">{{ __('Accedi') }}</a></div>
    </div>
</section>
@endsection
