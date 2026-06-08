@extends('layouts.admin')

@section('title', 'Modifica coupon')
@section('breadcrumb', 'Modifica coupon')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">Modifica coupon</h1>
            <p class="text-muted mb-1">
                Aggiorna configurazione, validità e limiti del coupon.
            </p>

            @isset($store)
                <div class="small text-muted">
                    <strong>{{ $store->name }}</strong>
                    <span class="mx-1">•</span>
                    Ditta {{ $store->ditta_cg18 }}
                    <span class="mx-1">•</span>
                    Site {{ $store->erp_site_code }}
                    <span class="mx-1">•</span>
                    {{ ($store->is_b2b ?? false) ? 'B2B' : 'B2C' }}
                </div>
            @endisset
        </div>

        <a href="{{ route('admin.coupons.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Torna alla lista
        </a>
    </div>

    <form method="POST" action="{{ route('admin.coupons.update', $coupon) }}">
        @csrf
        @method('PUT')

        @include('admin.coupons._form', [
            'coupon' => $coupon,
            'promotions' => $promotions ?? [],
            'submitLabel' => 'Aggiorna coupon',
        ])
    </form>
</div>
@endsection