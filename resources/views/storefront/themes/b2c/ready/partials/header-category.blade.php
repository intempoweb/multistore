@php
    $categoryLabel = trim((string) ($category['label'] ?? ''));
    $categoryUrl = trim((string) ($category['url'] ?? ''));
    $categoryChildren = collect($category['children'] ?? [])
        ->filter(fn ($child) => filled($child['label'] ?? null) && filled($child['url'] ?? null))
        ->values();
    $megaId = 'ready-mega-' . md5((string) (($category['slug'] ?? '') ?: $categoryUrl));
@endphp

@if($categoryLabel !== '' && $categoryUrl !== '')
    <div class="ready-nav-category">
        <a
            class="ready-nav-link {{ $categoryChildren->isNotEmpty() ? 'has-mega' : '' }}"
            href="{{ $categoryUrl }}"
            @if($categoryChildren->isNotEmpty()) aria-haspopup="true" aria-expanded="false" aria-controls="{{ $megaId }}" @endif
        >
            {{ $categoryLabel }}
            @if($categoryChildren->isNotEmpty())<i data-lucide="chevron-down" aria-hidden="true"></i>@endif
        </a>

        @if($categoryChildren->isNotEmpty())
            <div class="ready-mega-panel" id="{{ $megaId }}">
                <div class="ready-mega-inner">
                    <a href="{{ $categoryUrl }}" class="ready-mega-feature">
                        <span>Collezione</span>
                        <strong>{{ $categoryLabel }}</strong>
                        <small>Scopri tutta la selezione</small>
                    </a>

                    <div class="ready-mega-links">
                        @foreach($categoryChildren as $child)
                            @php
                                $grandchildren = collect($child['children'] ?? [])
                                    ->filter(fn ($grandchild) => filled($grandchild['label'] ?? null) && filled($grandchild['url'] ?? null))
                                    ->values();
                            @endphp
                            <div class="ready-mega-link-group">
                                <a href="{{ $child['url'] }}">
                                    <span>{{ $child['label'] }}</span>
                                    <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                                </a>
                                @if($grandchildren->isNotEmpty())
                                    <div class="ready-mega-sublinks">
                                        @foreach($grandchildren->take(5) as $grandchild)
                                            <a href="{{ $grandchild['url'] }}">{{ $grandchild['label'] }}</a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif
