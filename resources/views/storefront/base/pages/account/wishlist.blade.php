{{-- resources/views/storefront/base/pages/account/wishlist.blade.php --}}

@extends($storefrontLayout)

@section('title', 'I miei preferiti')

@section('content')

<div class="row g-4">

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <div class="text-muted small mb-1">
                        Area cliente
                    </div>

                    <h1 class="h3 fw-bold mb-1">
                        I miei preferiti
                    </h1>

                    <div class="text-muted small">
                        {{ $wishlistCount === 1 ? '1 prodotto salvato' : $wishlistCount . ' prodotti salvati' }}
                    </div>
                </div>

                <a href="{{ route('storefront.catalog.index') }}" class="btn btn-outline-dark">
                    Continua gli acquisti
                </a>
            </div>
        </div>
    </div>

    @forelse($wishlistItems as $item)
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3 p-lg-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-auto">
                            <a
                                href="{{ route('storefront.product.show', $item->sku) }}"
                                class="d-flex align-items-center justify-content-center bg-white border rounded text-decoration-none overflow-hidden"
                                style="width: 112px; height: 112px;"
                            >
                                @if($item->primaryImage()?->url)
                                    <img
                                        src="{{ $item->primaryImage()->url }}"
                                        alt="{{ $item->product?->display_name ?? $item->product?->sku ?? $item->sku }}"
                                        style="width: 100%; height: 100%; object-fit: contain;"
                                        loading="lazy"
                                    >
                                @else
                                    <span class="small text-muted text-center px-2">
                                        Nessuna immagine
                                    </span>
                                @endif
                            </a>
                        </div>

                        <div class="col">
                            <div class="small text-muted mb-1">
                                SKU {{ $item->sku }}
                            </div>

                            <h2 class="h6 fw-bold mb-2">
                                <a href="{{ route('storefront.product.show', $item->sku) }}" class="text-body text-decoration-none">
                                    {{ $item->product?->display_name ?? $item->product?->sku ?? $item->sku }}
                                </a>
                            </h2>

                            @if($item->product?->public_price !== null)
                                <div class="fw-semibold">
                                    € {{ number_format((float) $item->product->public_price, 3, ',', '.') }}
                                </div>
                            @endif
                        </div>

                        <div class="col-12 col-lg-auto">
                            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-lg-end">
                                <a href="{{ route('storefront.product.show', $item->sku) }}" class="btn btn-outline-secondary btn-sm">
                                    Vedi prodotto
                                </a>

                                <form method="POST" action="{{ route('storefront.wishlist.move-to-cart', $item) }}">
                                    @csrf

                                    <button type="submit" class="btn btn-dark btn-sm w-100">
                                        <i class="fa-solid fa-cart-shopping me-1"></i>
                                        Sposta nel carrello
                                    </button>
                                </form>

                                <form
                                    method="POST"
                                    action="{{ route('storefront.wishlist.remove', $item) }}"
                                    onsubmit="return confirm('Rimuovere questo prodotto dai preferiti?')"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn btn-link text-danger btn-sm w-100 text-decoration-none">
                                        Rimuovi
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="mb-3">
                        <i class="fa-regular fa-heart fa-2x text-muted"></i>
                    </div>

                    <h2 class="h5 mb-2">
                        La tua wishlist è vuota
                    </h2>

                    <p class="text-muted mb-4">
                        Salva i prodotti che ti interessano per ritrovarli rapidamente.
                    </p>

                    <a href="{{ route('storefront.catalog.index') }}" class="btn btn-dark">
                        Vai al catalogo
                    </a>
                </div>
            </div>
        </div>
    @endforelse

</div>
@endsection