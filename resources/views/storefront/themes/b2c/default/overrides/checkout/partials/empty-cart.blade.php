<div class="card border-0 shadow-sm">
    <div class="card-body py-5 text-center">
        <div class="mb-3 text-muted">
            <i class="fa-solid fa-cart-shopping fa-2x"></i>
        </div>

        <h5 class="mb-2">Il carrello è vuoto</h5>
        <p class="text-muted mb-4">Aggiungi prodotti dal catalogo per procedere con l'ordine.</p>

        <a href="{{ route('storefront.catalog.index') }}" class="btn btn-primary">
            Vai al catalogo
        </a>
    </div>
</div>