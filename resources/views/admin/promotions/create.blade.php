@extends('layouts.admin')

@section('title', 'Nuova promozione')
@section('breadcrumb', 'Nuova promozione')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">Nuova promozione</h1>
        <p class="text-muted mb-0">Crea una nuova regola promo per lo store corrente.</p>
    </div>

    <form method="POST" action="{{ route('admin.promotions.store') }}">
        @csrf
        @include('admin.promotions._form', [
            'promotion' => $promotion,
            'submitLabel' => 'Crea promozione',
        ])
    </form>
</div>
@endsection