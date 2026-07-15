@extends($storefrontLayout)
@section('title', __('themes_b2c.ciak.register'))
@section('content')
<div class="ciak-auth-page">
    <p class="ciak-eyebrow">{{ $store->name }}</p>
    <h1>{{ __('themes_b2c.ciak.create_your_account') }}</h1>
    <p>{{ __('themes_b2c.ciak.register_intro') }}</p>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('storefront.register.submit') }}" class="ciak-auth-form">
        @csrf
        @include('storefront.base.partials.recaptcha', ['action' => 'register'])
        <div><label for="first_name">{{ __('themes_b2c.form.name') }}</label><input class="form-control" id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" required></div>
        <div><label for="last_name">{{ __('themes_b2c.form.surname') }}</label><input class="form-control" id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" required></div>
        <div><label for="email">{{ __('themes_b2c.form.email') }}</label><input class="form-control" type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required></div>
        <div><label for="password">{{ __('themes_b2c.checkout.password') }}</label><input class="form-control" type="password" id="password" name="password" autocomplete="new-password" required></div>
        <div><label for="password_confirmation">{{ __('themes_b2c.ciak.confirm_password') }}</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="privacy" value="1" required><span class="form-check-label">{{ __('themes_b2c.ciak.privacy_acceptance') }}</span></label>
        <button type="submit" class="btn btn-dark">{{ __('themes_b2c.ciak.create_account') }}</button>
    </form>
    <div class="ciak-auth-meta"><span></span><a href="{{ route('storefront.login') }}">{{ __('themes_b2c.ciak.already_registered_login') }}</a></div>
</div>
@endsection
