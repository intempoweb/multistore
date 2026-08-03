@extends('layouts.admin')

@section('title', 'Nuovo popup')
@section('breadcrumb', 'Marketing / Popup storefront / Nuovo')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Nuovo popup</h1>
            <p class="text-muted mb-0">{{ $store->name }} · {{ $store->channelLabel() }}</p>
        </div>

        <a href="{{ route('admin.storefront-popups.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Torna alla lista
        </a>
    </div>

    <form method="POST" action="{{ route('admin.storefront-popups.store') }}">
        @csrf
        @include('admin.storefront-popups._form', ['submitLabel' => 'Crea popup'])
    </form>
</div>
@endsection
