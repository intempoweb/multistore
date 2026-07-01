@php
    $categoryLabel = trim((string) ($category['label'] ?? $category['code'] ?? ''));
    $categorySlug = trim((string) ($category['slug'] ?? ''));
    $categoryChildren = collect($category['children'] ?? [])
        ->filter(fn ($child) => filled($child['label'] ?? $child['code'] ?? null) && filled($child['slug'] ?? null))
        ->values();
    $categoryUrl = $categorySlug !== ''
        ? route('storefront.category.show', array_merge(['slug' => $categorySlug], $contextParams ?? []))
        : null;
    $megaId = 'intempo-b2b-mega-' . md5($categorySlug);
@endphp

@if($categoryLabel !== '' && $categoryUrl)
    <div class="intempo-b2b-mega-item" data-intempo-b2b-mega-item>
        <a
            class="intempo-b2b-nav-link {{ $categoryChildren->isNotEmpty() ? 'has-mega' : '' }}"
            href="{{ $categoryUrl }}"
            @if($categoryChildren->isNotEmpty()) aria-haspopup="true" aria-expanded="false" aria-controls="{{ $megaId }}" data-intempo-b2b-mega-trigger @endif
        >
            {{ $categoryLabel }}
            @if($categoryChildren->isNotEmpty())<i class="fa-solid fa-chevron-down" aria-hidden="true"></i>@endif
        </a>

        @if($categoryChildren->isNotEmpty())
            <div class="intempo-b2b-mega-panel" id="{{ $megaId }}" data-intempo-b2b-mega-panel>
                <div class="intempo-b2b-mega-inner">
                    <a href="{{ $categoryUrl }}" class="intempo-b2b-mega-feature">
                        <small>Categoria</small>
                        <strong>{{ $categoryLabel }}</strong>
                        <span>Consulta listini, disponibilità e prodotti visibili per il tuo account.</span>
                    </a>

                    <div class="intempo-b2b-mega-links">
                        @foreach($categoryChildren->take(14) as $child)
                            @php
                                $childLabel = $child['label'] ?? $child['code'] ?? 'Categoria';
                                $childSlug = $child['slug'] ?? null;
                                $grandchildren = collect($child['children'] ?? [])->filter(fn ($grandchild) => filled($grandchild['slug'] ?? null))->values();
                            @endphp

                            @if($childSlug)
                                <div class="intempo-b2b-mega-column">
                                    <a class="intempo-b2b-mega-second" href="{{ route('storefront.category.show', array_merge(['slug' => $childSlug], $contextParams ?? [])) }}">
                                        <span>{{ $childLabel }}</span>
                                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                    </a>

                                    @if($grandchildren->isNotEmpty())
                                        <div class="intempo-b2b-mega-third-list">
                                            @foreach($grandchildren->take(5) as $grandchild)
                                                <a href="{{ route('storefront.category.show', array_merge(['slug' => $grandchild['slug']], $contextParams ?? [])) }}">
                                                    {{ $grandchild['label'] ?? $grandchild['code'] ?? 'Categoria' }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <div class="intempo-b2b-mega-action">
                        <p>Hai già i codici articolo?</p>
                        @if(Route::has('storefront.cart.import'))
                            <button type="button" data-bs-toggle="offcanvas" data-bs-target="#storefrontCartImport" aria-controls="storefrontCartImport">
                                Acquisto rapido
                                <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                            </button>
                        @else
                            <a href="{{ $categoryUrl }}">Vedi categoria <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif
