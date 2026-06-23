@extends($storefrontLayout)

@section('title', ($store->name ?? 'Fipell') . ' - Ingrosso cartoleria, scuola e ufficio')

@section('content')
<div class="fipell-home">
    <section class="fipell-home-hero" aria-labelledby="fipell-home-title">
        <div class="fipell-home-hero-copy">
            <span class="fipell-home-eyebrow">Portale B2B</span>
            <h1 id="fipell-home-title">Ingrosso cartoleria, scuola e ufficio</h1>
            <p>
                @if($customerName !== '')
                    {{ $customerName }}, il tuo catalogo B2B e pronto con disponibilita e listini dedicati.
                @else
                    Il tuo catalogo B2B e pronto con disponibilita e listini dedicati.
                @endif
            </p>

            <div class="fipell-home-hero-stats" aria-label="Riepilogo catalogo">
                <span><strong>{{ number_format($productsTotal, 0, ',', '.') }}</strong><small>Prodotti</small></span>
                <span><strong>{{ number_format($rootCategories->count(), 0, ',', '.') }}</strong><small>Categorie</small></span>
                <span><strong>{{ $featuredCards->count() }}</strong><small>In evidenza</small></span>
            </div>

            <a href="{{ $catalogUrl }}" class="btn fipell-home-primary-btn"><span>Vai al catalogo</span></a>
        </div>

        <div class="fipell-home-hero-panel">
            <div class="fipell-home-hero-products" aria-label="Prodotti in catalogo">
                @forelse($heroCards as $row)
                    <a href="{{ $row['url'] }}" class="fipell-home-hero-product fipell-home-hero-product-{{ $loop->iteration }}">
                        @if($row['card']->image)
                            <img src="{{ $row['card']->image }}" alt="{{ $row['card']->name }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                        @else
                            <i class="fa-regular fa-square" aria-hidden="true"></i>
                        @endif
                    </a>
                @empty
                    <span class="fipell-home-hero-product"><i class="fa-regular fa-square" aria-hidden="true"></i></span>
                @endforelse
            </div>
        </div>
    </section>

    <section class="fipell-home-actions" aria-label="Azioni rapide">
        <a href="{{ $catalogUrl }}" class="fipell-home-action">
            <i class="fa-regular fa-rectangle-list" aria-hidden="true"></i>
            <span><strong>Catalogo</strong><small>Prodotti, disponibilita e prezzi cliente</small></span>
        </a>
        <a href="{{ $documentsUrl }}" class="fipell-home-action">
            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
            <span><strong>Documenti</strong><small>Fatture, DDT e statistiche ordini</small></span>
        </a>
        @if($quickOrderEnabled)
            <button type="button" class="fipell-home-action" data-bs-toggle="offcanvas" data-bs-target="#storefrontCartImport" aria-controls="storefrontCartImport">
                <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                <span><strong>Acquisto rapido</strong><small>Importa righe ordine da file</small></span>
            </button>
        @endif
        <a href="{{ $accountUrl }}" class="fipell-home-action">
            <i class="fa-regular fa-user" aria-hidden="true"></i>
            <span><strong>Account</strong><small>Dati cliente e storico attivita</small></span>
        </a>
    </section>

    <section class="fipell-home-section" aria-labelledby="fipell-categories-title">
        <div class="fipell-home-section-head">
            <h2 id="fipell-categories-title">Categorie principali</h2>
            <a href="{{ $catalogUrl }}" class="fipell-home-link">{{ $rootCategories->count() > 6 ? 'Vedi tutte le categorie' : 'Vai al catalogo' }}</a>
        </div>
        @if($categoryCards->isEmpty())
            <div class="fipell-home-empty">Nessuna categoria disponibile per questo account.</div>
        @else
            <div class="fipell-home-category-grid">
                @foreach($categoryCards as $category)
                    <a href="{{ $category['url'] }}" class="fipell-home-category-card">
                        <i class="{{ $category['icon'] }}" aria-hidden="true"></i><span>{{ $category['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="fipell-home-section" aria-labelledby="fipell-products-title">
        <div class="fipell-home-section-head">
            <h2 id="fipell-products-title">Prodotti in evidenza</h2>
            <a href="{{ $catalogUrl }}" class="fipell-home-link">{{ $productsTotal > 0 ? 'Vedi tutti i prodotti' : 'Vai al catalogo' }}</a>
        </div>
        @if($featuredCards->isEmpty())
            <div class="fipell-home-empty">Nessun prodotto disponibile per questo account.</div>
        @else
            <div class="fipell-home-product-grid">
                @foreach($featuredCards as $row)
                    <a href="{{ $row['url'] }}" class="fipell-home-product-card">
                        <span class="fipell-home-product-image">
                            @if($row['card']->image)
                                <img src="{{ $row['card']->image }}" alt="{{ $row['card']->name }}" loading="lazy">
                            @else
                                <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                            @endif
                        </span>
                        <span class="fipell-home-product-copy">
                            <strong>{{ $row['card']->name }}</strong>
                            <small>SKU {{ $row['card']->targetSku }}</small>
                            <b>{{ $row['price_label'] }}</b>
                            <em class="{{ $row['availability']['class'] }}">{{ $row['availability']['label'] }}</em>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="fipell-home-cta" aria-labelledby="fipell-listini-title">
        <div>
            <h2 id="fipell-listini-title">Continua a lavorare sul tuo catalogo riservato</h2>
            <p>
                {{ number_format($productsTotal, 0, ',', '.') }} prodotti disponibili
                @if($rootCategories->isNotEmpty())
                    in {{ number_format($rootCategories->count(), 0, ',', '.') }} categorie
                @endif
                con i tuoi prezzi cliente.
            </p>
        </div>
        <div class="fipell-home-cta-actions">
            @if($quickOrderEnabled)
                <button type="button" class="btn fipell-home-secondary-btn" data-bs-toggle="offcanvas" data-bs-target="#storefrontCartImport" aria-controls="storefrontCartImport">
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i><span>Acquisto rapido</span>
                </button>
            @endif
            <a href="{{ $catalogUrl }}" class="btn fipell-home-primary-btn">
                <i class="fa-regular fa-rectangle-list" aria-hidden="true"></i><span>Vai al catalogo</span>
            </a>
        </div>
    </section>
</div>
@endsection
