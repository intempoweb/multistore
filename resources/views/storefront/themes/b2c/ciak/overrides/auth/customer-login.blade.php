@extends($storefrontLayout)
@section('title', __('Accedi'))
@section('content')
<div class="ciak-auth-page">
    <p class="ciak-eyebrow">{{ $store->name }}</p>
    <h1>{{ __('Accedi') }}</h1>
    <p>{{ __('Entra nel tuo account per gestire ordini e preferiti.') }}</p>
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('storefront.login.submit') }}" class="ciak-auth-form">
        @csrf
        <input type="hidden" name="auth_mode" value="customer">
        <div><label for="customer_login">{{ __('Email') }}</label><input class="form-control" type="text" id="customer_login" name="login" value="{{ old('login', $login ?? '') }}" autocomplete="username" required autofocus></div>
        <div><label for="customer_password">{{ __('Password') }}</label><input class="form-control" type="password" id="customer_password" name="password" autocomplete="current-password" required></div>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="remember" value="1" @checked(old('remember'))><span class="form-check-label">{{ __('Ricordami') }}</span></label>
        <button type="submit" class="btn btn-dark">{{ __('Accedi') }}</button>
    </form>
    <div class="ciak-auth-meta"><a href="{{ route('storefront.password.request') }}">{{ __('Password dimenticata?') }}</a><a href="{{ route('storefront.register') }}">{{ __('Crea un account') }}</a></div>
</div>
@endsection
