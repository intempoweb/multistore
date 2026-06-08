@php
    $store = $store ?? (app()->bound('currentStore') ? app('currentStore') : null);
    $items = collect($items ?? []);
    $cart = $cart ?? null;

    $canImportCart = (bool) ($store?->is_b2b ?? false)
        && auth('customer')->check()
        && Route::has('storefront.cart.import');
@endphp

@if($canImportCart)
    @once
        <div
            class="offcanvas offcanvas-end"
            tabindex="-1"
            id="storefrontCartImport"
            aria-labelledby="storefrontCartImportLabel"
        >
            <div class="offcanvas-header border-bottom">
                <div>
                    <h5 class="offcanvas-title" id="storefrontCartImportLabel">
                        <i class="fa-solid fa-file-import me-2"></i>
                        Acquisto rapido
                    </h5>
                    <div class="text-muted small mt-1">
                        Carica un file CSV, XLS o XLSX con colonne <strong>sku</strong> e <strong>qty</strong>.
                    </div>
                </div>

                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Chiudi"></button>
            </div>

            <div class="offcanvas-body">
                <div class="border rounded-3 bg-light-subtle p-3 mb-3">
                    <div class="fw-semibold small mb-1">Formato consigliato</div>
                    <div class="text-muted small">
                        Usa una prima riga con intestazioni <code>sku</code> e <code>qty</code>.
                        Senza intestazioni: prima colonna SKU, seconda quantità.
                    </div>
                </div>

                <div class="d-grid gap-2 mb-3">
                    @if(Route::has('storefront.cart.import.template'))
                        <a href="{{ route('storefront.cart.import.template') }}" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-file-arrow-down me-2"></i>
                            Scarica template
                        </a>
                    @endif

                    @if($cart && $items->isNotEmpty() && Route::has('storefront.cart.export'))
                        <a href="{{ route('storefront.cart.export') }}" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-file-export me-2"></i>
                            Esporta carrello
                        </a>
                    @endif
                </div>

                <form method="POST" action="{{ route('storefront.cart.import') }}" enctype="multipart/form-data" class="d-flex flex-column gap-3">
                    @csrf

                    <div>
                        <label for="cart_import_file" class="form-label fw-semibold">File prodotti</label>
                        <input
                            type="file"
                            name="import_file"
                            id="cart_import_file"
                            class="form-control @error('import_file') is-invalid @enderror"
                            accept=".csv,.txt,.xls,.xlsx"
                            required
                        >

                        @error('import_file')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-success w-100">
                        <i class="fa-solid fa-file-import me-2"></i>
                        Carica prodotti nel carrello
                    </button>
                </form>
            </div>
        </div>
    @endonce
@endif