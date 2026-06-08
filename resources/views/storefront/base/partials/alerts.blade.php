

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i>
        {{ session('success') }}

        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        {{ session('error') }}

        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i>
        {{ session('warning') }}

        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
    </div>
@endif

@if (session('info'))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-info me-2"></i>
        {{ session('info') }}

        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="fw-semibold mb-2">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            Controlla i dati inseriti:
        </div>

        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>

        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
    </div>
@endif

@if (session('cart_import_errors'))
    @php
        $cartImportErrors = collect(session('cart_import_errors', []));
    @endphp

    @if ($cartImportErrors->isNotEmpty())
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <div class="fw-semibold mb-2">
                <i class="fa-solid fa-file-import me-2"></i>
                Alcune righe non sono state importate:
            </div>

            <ul class="mb-0 ps-3">
                @foreach ($cartImportErrors->take(20) as $importError)
                    <li>{{ $importError }}</li>
                @endforeach
            </ul>

            @if ($cartImportErrors->count() > 20)
                <div class="small mt-2">
                    Altri {{ $cartImportErrors->count() - 20 }} errori non mostrati.
                </div>
            @endif

            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
        </div>
    @endif
@endif