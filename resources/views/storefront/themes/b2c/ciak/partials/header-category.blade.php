@php
    $categoryLabel = trim((string) ($category['label'] ?? ''));
    $categorySlug = trim((string) ($category['slug'] ?? ''));
    $categoryChildren = collect($category['children'] ?? []);
    $mobile = (bool) ($mobile ?? false);
    $contextParams = $contextParams ?? [];
    $categoryUrl = $categorySlug !== ''
        ? route('storefront.category.show', array_merge(['slug' => $categorySlug], $contextParams))
        : null;
    $formatIconFor = static function (array $item): ?string {
        $text = mb_strtolower(trim((string) (($item['label'] ?? '').' '.($item['slug'] ?? ''))));

        return match (true) {
            str_contains($text, 'giornal') => asset('images/themes/b2c/ciak/formats/agenda-giornaliera.png'),
            str_contains($text, 'settiman') => asset('images/themes/b2c/ciak/formats/agenda-settimanale.png'),
            str_contains($text, 'puntin') => asset('images/themes/b2c/ciak/formats/taccuino-puntini.png'),
            str_contains($text, 'righe') => asset('images/themes/b2c/ciak/formats/taccuino-righe.png'),
            str_contains($text, 'bianch'), str_contains($text, 'vuote') => asset('images/themes/b2c/ciak/formats/taccuino-pagine-bianche.png'),
            str_contains($text, 'agenda') => asset('images/themes/b2c/ciak/formats/agenda-settimanale.png'),
            str_contains($text, 'taccuin'), str_contains($text, 'quadern') => asset('images/themes/b2c/ciak/formats/taccuino-pagine-bianche.png'),
            default => null,
        };
    };
    $categoryIcon = $formatIconFor($category);
@endphp
@if($categoryLabel !== '' && $categoryUrl)
    @if($mobile)
        <div class="ciak-mobile-category">
            <div>
                <a href="{{ $categoryUrl }}">
                    @if($categoryIcon)<span class="ciak-menu-format-icon"><img src="{{ $categoryIcon }}" alt="" loading="lazy"></span>@endif
                    <span>{{ $categoryLabel }}</span>
                </a>
                @if($categoryChildren->isNotEmpty())<button type="button" data-bs-toggle="collapse" data-bs-target="#ciak-mobile-category-{{ md5($categorySlug) }}" aria-label="{{ __('Apri sottocategorie') }}" aria-expanded="false"><i data-lucide="chevron-down"></i></button>@endif
            </div>
            @if($categoryChildren->isNotEmpty())
                <div class="collapse ciak-mobile-children" id="ciak-mobile-category-{{ md5($categorySlug) }}">
                    @foreach($categoryChildren as $child)
                        @if(!empty($child['slug']))
                            @php($childIcon = $formatIconFor($child))
                            <a class="{{ $childIcon ? 'has-format-icon' : '' }}" href="{{ route('storefront.category.show', array_merge(['slug' => $child['slug']], $contextParams)) }}">
                                @if($childIcon)<span class="ciak-menu-format-icon"><img src="{{ $childIcon }}" alt="" loading="lazy"></span>@endif
                                <span>{{ $child['label'] ?? $child['code'] }}</span>
                                <i data-lucide="arrow-up-right"></i>
                            </a>
                            @foreach(collect($child['children'] ?? []) as $grandchild)
                                @if(!empty($grandchild['slug']))<a class="is-third" href="{{ route('storefront.category.show', array_merge(['slug' => $grandchild['slug']], $contextParams)) }}">{{ $grandchild['label'] ?? $grandchild['code'] }}</a>@endif
                            @endforeach
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <div class="ciak-nav-category dropdown">
            <a
                class="ciak-nav-link {{ $categoryChildren->isNotEmpty() ? 'dropdown-toggle' : '' }}"
                href="{{ $categoryUrl }}"
                @if($categoryChildren->isNotEmpty()) data-bs-toggle="dropdown" aria-expanded="false" @endif
            >{{ $categoryLabel }}</a>

            @if($categoryChildren->isNotEmpty())
                <div class="dropdown-menu ciak-simple-dropdown">
                    <a class="ciak-simple-dropdown-main" href="{{ $categoryUrl }}">{{ __('Tutti') }} {{ $categoryLabel }}</a>
                    @foreach($categoryChildren as $child)
                        @if(!empty($child['slug']))
                            @php($childIcon = $formatIconFor($child))
                            <a class="ciak-simple-dropdown-link {{ $childIcon ? 'has-format-icon' : '' }}" href="{{ route('storefront.category.show', array_merge(['slug' => $child['slug']], $contextParams)) }}">
                                @if($childIcon)<span class="ciak-menu-format-icon"><img src="{{ $childIcon }}" alt="" loading="lazy"></span>@endif
                                <span>{{ $child['label'] ?? $child['code'] }}</span>
                                <i data-lucide="arrow-up-right"></i>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    @endif
@endif
