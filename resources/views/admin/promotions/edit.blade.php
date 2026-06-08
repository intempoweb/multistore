@extends('layouts.admin')

@section('title', 'Modifica promozione')

@section('breadcrumb', 'Modifica promozione')

@section('content')

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-start mb-4">

        <div>

            <h1 class="h3 mb-1">Modifica promozione</h1>

            <p class="text-muted mb-0">{{ $promotion->name }}</p>

        </div>

        <a href="{{ route('admin.promotions.index') }}" class="btn btn-sm btn-outline-secondary">

            Torna alla lista

        </a>

    </div>

    <form method="POST" action="{{ route('admin.promotions.update', $promotion) }}">

        @csrf

        @method('PUT')

        @include('admin.promotions._form', [

            'promotion' => $promotion,

            'submitLabel' => 'Aggiorna promozione',

        ])

    </form>

</div>

@endsection