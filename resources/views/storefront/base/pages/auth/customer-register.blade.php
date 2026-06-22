@extends($storefrontLayout)

@section('title', __('Registrati'))

@section('content')
<div class="container py-5" style="max-width: 680px;">
    <h1 class="h2 mb-2">{{ __('Crea il tuo account') }}</h1>
    <p class="text-muted mb-4">{{ __('Registrati per salvare i preferiti e gestire più facilmente i tuoi acquisti.') }}</p>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('storefront.register.submit') }}" class="card card-body shadow-sm border-0 p-4">
        @csrf
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="first_name">{{ __('Nome') }}</label><input class="form-control" id="first_name" name="first_name" value="{{ old('first_name') }}" required></div>
            <div class="col-md-6"><label class="form-label" for="last_name">{{ __('Cognome') }}</label><input class="form-control" id="last_name" name="last_name" value="{{ old('last_name') }}" required></div>
            <div class="col-12"><label class="form-label" for="email">{{ __('Email') }}</label><input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" required></div>
            <div class="col-md-6"><label class="form-label" for="password">{{ __('Password') }}</label><input type="password" class="form-control" id="password" name="password" required></div>
            <div class="col-md-6"><label class="form-label" for="password_confirmation">{{ __('Conferma password') }}</label><input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required></div>
            <div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="privacy" value="1" required> <span class="form-check-label">{{ __('Accetto l’informativa privacy e le condizioni di utilizzo.') }}</span></label></div>
            <div class="col-12"><button class="btn btn-dark w-100" type="submit">{{ __('Crea account') }}</button></div>
        </div>
    </form>
</div>
@endsection
