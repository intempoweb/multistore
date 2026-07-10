@extends('layouts.admin')

@section('title', 'Nuova pagina storefront')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Nuova pagina storefront</h1>
            <div class="text-muted">
                Crea una pagina contenuto collegata ai template Blade dello store corrente.
            </div>
        </div>

        <div>
            <a href="{{ route('admin.storefront-pages.index') }}" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-2"></i>
                Torna alle pagine
            </a>
        </div>
    </div>

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

    <form method="POST" action="{{ route('admin.storefront-pages.store') }}">
        @csrf

        @include('admin.storefront-pages._form', [
            'page' => $page,
            'usesTranslations' => $usesTranslations ?? false,
            'contentLocale' => $contentLocale ?? app()->getLocale(),
            'supportedLocales' => $supportedLocales ?? [app()->getLocale()],
        ])
    </form>

</div>
@endsection
