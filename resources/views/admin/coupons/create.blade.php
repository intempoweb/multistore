@extends('layouts.admin')

@section('title', 'Nuovo coupon')
@section('breadcrumb', 'Nuovo coupon')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">Nuovo coupon</h1>
            <p class="text-muted mb-1">
                Crea un coupon per lo store corrente.
            </p>

            @isset($store)
                <div class="small text-muted">
                    <strong>{{ $store->name }}</strong>
                    <span class="mx-1">•</span>
                    Ditta {{ $store->ditta_cg18 }}
                    <span class="mx-1">•</span>
                    Site {{ $store->erp_site_code }}
                    <span class="mx-1">•</span>
                    {{ $store->channelLabel() }}
                </div>
            @endisset
        </div>

        <a href="{{ route('admin.coupons.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>
            Torna alla lista
        </a>
    </div>

    <form method="POST" action="{{ route('admin.coupons.store') }}">
        @csrf

        @include('admin.coupons._form', [
            'coupon' => $coupon,
            'promotions' => $promotions ?? [],
            'submitLabel' => 'Crea coupon',
        ])
    </form>
</div>
@endsection
