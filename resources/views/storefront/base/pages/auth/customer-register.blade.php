@extends($storefrontLayout)

@section('title', __('themes_b2c.ciak.register'))

@section('content')
<div class="storefront-auth-container-narrow container py-5">
    <h1 class="h2 mb-2">{{ __('themes_b2c.ciak.create_your_account') }}</h1>
    <p class="text-muted mb-4">{{ __('themes_b2c.ciak.register_intro') }}</p>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('storefront.register.submit') }}" class="card card-body shadow-sm border-0 p-4">
        @csrf
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="first_name">{{ __('themes_b2c.form.name') }}</label><input class="form-control" id="first_name" name="first_name" value="{{ old('first_name') }}" required></div>
            <div class="col-md-6"><label class="form-label" for="last_name">{{ __('themes_b2c.form.surname') }}</label><input class="form-control" id="last_name" name="last_name" value="{{ old('last_name') }}" required></div>
            <div class="col-12"><label class="form-label" for="email">{{ __('Email') }}</label><input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required></div>
            <div class="col-md-6"><label class="form-label" for="password">{{ __('Password') }}</label><input type="password" class="form-control" id="password" name="password" required></div>
            <div class="col-md-6"><label class="form-label" for="password_confirmation">{{ __('themes_b2c.ciak.confirm_password') }}</label><input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required></div>
            <div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="privacy" value="1" required> <span class="form-check-label">{{ __('themes_b2c.ciak.privacy_acceptance') }}</span></label></div>
            <div class="col-12"><button class="btn btn-dark w-100" type="submit">{{ __('themes_b2c.ciak.create_account') }}</button></div>
        </div>
    </form>
</div>
@endsection
