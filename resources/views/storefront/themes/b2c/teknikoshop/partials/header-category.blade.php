@php
    $categoryLabel = trim((string) ($category['label'] ?? ''));
    $categorySlug = trim((string) ($category['slug'] ?? ''));
    $categoryChildren = collect($category['children'] ?? []);
    $mobile = (bool) ($mobile ?? false);
    $contextParams = $contextParams ?? [];
    $categoryUrl = $categorySlug !== ''
        ? route('storefront.category.show', array_merge(['slug' => $categorySlug], $contextParams))
        : null;
    $technicalIconFor = static function (array $item): string {
        $text = mb_strtolower(trim((string) (($item['label'] ?? '').' '.($item['slug'] ?? ''))));

        return match (true) {
            str_contains($text, 'led') => 'scan-line',
            str_contains($text, 'expand') => 'panel-right-open',
            str_contains($text, 'magnum') => 'box',
            str_contains($text, 'big') => 'briefcase-business',
            str_contains($text, 'tab') => 'tablet',
            str_contains($text, 'zain') => 'backpack',
            default => 'component',
        };
    };
    $categoryIcon = $technicalIconFor($category);
    $desktopChildren = $categoryChildren
        ->map(function ($child) use ($technicalIconFor) {
            $child = (array) $child;
            $slug = trim((string) ($child['slug'] ?? ''));

            if ($slug === '') {
                return null;
            }

            $grandchildren = collect($child['children'] ?? [])
                ->filter(fn ($item) => !empty($item['slug']))
                ->values();

            return [
                'slug' => $slug,
                'label' => $child['label'] ?? $child['code'] ?? '',
                'icon' => $technicalIconFor($child),
                'summary' => $grandchildren->take(2)->pluck('label')->filter()->implode(' · '),
            ];
        })
        ->filter()
        ->values();
@endphp
@if($categoryLabel !== '' && $categoryUrl)
    @if($mobile)
        <div class="ciak-mobile-category teknikoshop-mobile-category">
            <div>
                <a href="{{ $categoryUrl }}">
                    <span class="teknikoshop-menu-icon"><i data-lucide="{{ $categoryIcon }}"></i></span>
                    <span>{{ $categoryLabel }}</span>
                </a>
                @if($categoryChildren->isNotEmpty())<button type="button" data-bs-toggle="collapse" data-bs-target="#teknikoshop-mobile-category-{{ md5($categorySlug) }}" aria-label="{{ __('themes_b2c.ciak.open_subcategories') }}" aria-expanded="false"><i data-lucide="chevron-down"></i></button>@endif
            </div>
            @if($categoryChildren->isNotEmpty())
                <div class="collapse ciak-mobile-children" id="teknikoshop-mobile-category-{{ md5($categorySlug) }}">
                    @foreach($categoryChildren as $child)
                        @if(!empty($child['slug']))
                            @php($childIcon = $technicalIconFor($child))
                            <a href="{{ route('storefront.category.show', array_merge(['slug' => $child['slug']], $contextParams)) }}">
                                <span class="teknikoshop-menu-icon"><i data-lucide="{{ $childIcon }}"></i></span>
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
        <div class="ciak-nav-category dropdown teknikoshop-nav-category">
            <a
                class="ciak-nav-link {{ $categoryChildren->isNotEmpty() ? 'dropdown-toggle' : '' }}"
                href="{{ $categoryUrl }}"
                @if($categoryChildren->isNotEmpty()) data-bs-toggle="dropdown" aria-expanded="false" @endif
            >{{ $categoryLabel }}</a>

            @if($categoryChildren->isNotEmpty())
                <div class="dropdown-menu ciak-simple-dropdown ciak-mega-dropdown teknikoshop-mega-dropdown">
                    <div class="ciak-mega-inner teknikoshop-mega-inner">
                        <div class="ciak-mega-head teknikoshop-mega-head">
                            <div>
                                <span>Collezione</span>
                                <strong>{{ $categoryLabel }}</strong>
                            </div>
                            <a href="{{ $categoryUrl }}">{{ __('themes_b2c.ciak.view_all') }} <i data-lucide="arrow-right"></i></a>
                        </div>

                        <div class="ciak-mega-grid teknikoshop-mega-grid">
                            @foreach($desktopChildren as $child)
                                <a class="ciak-mega-card teknikoshop-mega-card" href="{{ route('storefront.category.show', array_merge(['slug' => $child['slug']], $contextParams)) }}">
                                    <span class="ciak-mega-card-media teknikoshop-mega-card-media">
                                        <i data-lucide="{{ $child['icon'] }}"></i>
                                    </span>
                                    <span class="ciak-mega-card-copy mx-3">
                                        <strong>{{ $child['label'] }}</strong>
                                        @if($child['summary'] !== '')
                                            <small>{{ $child['summary'] }}</small>
                                        @else
                                            <small>Scopri la collezione tecnica</small>
                                        @endif
                                    </span>
                                    <i data-lucide="arrow-up-right"></i>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
@endif
