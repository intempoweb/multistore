@php
    $categoryLabel = trim((string) ($category['label'] ?? ''));
    $categorySlug = trim((string) ($category['slug'] ?? ''));
    $categoryChildren = collect($category['children'] ?? [])
        ->filter(fn ($child) => filled($child['label'] ?? null) && filled($child['slug'] ?? null))
        ->values();
    $categoryUrl = $categorySlug !== ''
        ? route('storefront.category.show', array_merge(['slug' => $categorySlug], $contextParams ?? []))
        : null;
    $megaId = 'intempo-mega-' . md5($categorySlug);
    $categoryIcon = is_callable($menuIconFor ?? null) ? $menuIconFor($category) : null;
@endphp

@if($categoryLabel !== '' && $categoryUrl)
    <div class="intempo-b2c-mega-item" data-intempo-mega-item>
        <a
            class="intempo-b2c-nav-link {{ $categoryChildren->isNotEmpty() ? 'has-mega' : '' }}"
            href="{{ $categoryUrl }}"
            @if($categoryChildren->isNotEmpty()) aria-haspopup="true" aria-expanded="false" aria-controls="{{ $megaId }}" data-intempo-mega-trigger @endif
        >
            {{ $categoryLabel }}
            @if($categoryChildren->isNotEmpty())<i data-lucide="chevron-down" aria-hidden="true"></i>@endif
        </a>

        @if($categoryChildren->isNotEmpty())
            <div class="intempo-b2c-mega-panel" id="{{ $megaId }}" data-intempo-mega-panel>
                <div class="intempo-b2c-mega-inner {{ $categoryIcon ? '' : 'has-no-feature-icon' }}">
                    <a href="{{ $categoryUrl }}" class="intempo-b2c-mega-feature">
                        @if($categoryIcon)
                            <img src="{{ $categoryIcon }}" alt="" loading="lazy" decoding="async">
                        @endif
                        <span>
                            <small>{{ __('Collezione') }}</small>
                            <strong>{{ $categoryLabel }}</strong>
                            <em>{{ __('Scopri tutta la selezione') }}</em>
                        </span>
                    </a>
                    <div class="intempo-b2c-mega-links">
                        @foreach($categoryChildren->take(12) as $child)
                            <a href="{{ route('storefront.category.show', array_merge(['slug' => $child['slug']], $contextParams ?? [])) }}">
                                <span>{{ $child['label'] ?? $child['code'] }}</span>
                                <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                            </a>
                        @endforeach
                    </div>
                    <div class="intempo-b2c-mega-copy">
                        <p>{{ __('Scegli prodotti pensati per scrivere, lavorare e organizzare il tuo tempo con semplicità.') }}</p>
                        <a href="{{ $categoryUrl }}">{{ __('Vedi categoria') }}<i data-lucide="arrow-right" aria-hidden="true"></i></a>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif
