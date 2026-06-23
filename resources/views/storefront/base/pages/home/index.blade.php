@extends($storefrontLayout)

@section('title', $store->name)

@section('content')
<div class="bg-white p-4 rounded shadow-sm">
    @if($isAgentContext)
        <div class="alert alert-warning border-0 mb-4 small">
            <i class="fa-solid fa-user-tie me-1"></i>
            Stai operando come agente per questo cliente.
        </div>
    @endif

    <h1 class="h3 fw-bold">
        {{ $store->name }}
    </h1>

    <p class="text-muted">
        Homepage B2B default.
    </p>

    <div class="d-flex flex-wrap gap-2 mt-4">
        @if(Route::has('storefront.catalog.index'))
            <a href="{{ route('storefront.catalog.index', $contextParams) }}" class="btn btn-primary">
                Vai al catalogo
            </a>
        @endif

        @if(Route::has('storefront.cart.index'))
            <a href="{{ route('storefront.cart.index', $contextParams) }}" class="btn btn-outline-secondary">
                Vai al carrello
            </a>
        @endif
    </div>
</div>

@endsection
