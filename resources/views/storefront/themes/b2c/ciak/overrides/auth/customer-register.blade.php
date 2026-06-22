@extends($storefrontLayout)
@section('title', __('Registrati'))
@section('content')
<div class="ciak-auth-page">
    <p class="ciak-eyebrow">{{ $store->name }}</p>
    <h1>{{ __('Crea il tuo account') }}</h1>
    <p>{{ __('Registrati per salvare i preferiti e gestire i tuoi acquisti.') }}</p>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('storefront.register.submit') }}" class="ciak-auth-form">
        @csrf
        <div><label for="first_name">{{ __('Nome') }}</label><input class="form-control" id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" required></div>
        <div><label for="last_name">{{ __('Cognome') }}</label><input class="form-control" id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" required></div>
        <div><label for="email">{{ __('Email') }}</label><input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required></div>
        <div><label for="password">{{ __('Password') }}</label><input class="form-control" type="password" id="password" name="password" autocomplete="new-password" required></div>
        <div><label for="password_confirmation">{{ __('Conferma password') }}</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="privacy" value="1" required><span class="form-check-label">{{ __('Accetto l’informativa privacy e le condizioni di utilizzo.') }}</span></label>
        <button type="submit" class="btn btn-dark">{{ __('Crea account') }}</button>
    </form>
    <div class="ciak-auth-meta"><span></span><a href="{{ route('storefront.login') }}">{{ __('Hai già un account? Accedi') }}</a></div>
</div>
@endsection
