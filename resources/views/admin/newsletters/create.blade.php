@extends('layouts.admin')

@section('title', 'Nuova newsletter')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Nuova newsletter</h1>
            <p class="text-muted mb-0">Genera contenuti prodotto dal catalogo ERP sincronizzato.</p>
        </div>
        <a href="{{ route('admin.newsletters.index') }}" class="btn btn-outline-secondary">Torna all'elenco</a>
    </div>

    <form method="POST" action="{{ route('admin.newsletters.store') }}">
        @csrf
        @include('admin.newsletters._form')
        <div class="mt-4 d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">Crea newsletter</button>
        </div>
    </form>
</div>
@endsection
