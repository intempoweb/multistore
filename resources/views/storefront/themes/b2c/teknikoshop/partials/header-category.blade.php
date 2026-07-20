@php
    $categoryLabel = trim((string) ($category['label'] ?? ''));
    $categorySlug = trim((string) ($category['slug'] ?? ''));
    $categoryChildren = collect($category['children'] ?? []);
    $mobile = (bool) ($mobile ?? false);
    $contextParams = $contextParams ?? [];
    $categoryUrl = $categorySlug !== ''
        ? route('storefront.category.show', array_merge(['slug' => $categorySlug], $contextParams))
        : null;
    $catalogRepository = app(\App\Repositories\Storefront\CatalogRepository::class);
    $locale = app()->getLocale();
    $store = $store ?? current_store();
    $collectionDefinitions = collect([
        ['key' => 'led', 'label' => 'LED', 'fam' => 'TZ', 'sfam' => 'TZ01'],
        ['key' => 'expand', 'label' => 'EXPAND', 'fam' => 'TZ', 'sfam' => 'TZ02'],
        ['key' => 'magnum', 'label' => 'MAGNUM', 'fam' => 'TZ', 'sfam' => 'TZ03'],
        ['key' => 'big', 'label' => 'BIG', 'fam' => 'TZ', 'sfam' => 'TZ04'],
        ['key' => 'tab', 'label' => 'TAB', 'fam' => 'TZ', 'sfam' => 'TZ05'],
    ]);
    $collectionKeyFor = static function (array $item): ?string {
        $text = mb_strtolower(trim((string) (($item['label'] ?? '').' '.($item['slug'] ?? ''))));

        return match (true) {
            str_contains($text, 'led') => 'led',
            str_contains($text, 'expand') => 'expand',
            str_contains($text, 'magnum') => 'magnum',
            str_contains($text, 'big') => 'big',
            str_contains($text, 'tab') => 'tab',
            default => null,
        };
    };
    $collectionAssetsFor = static function (array $item) use ($collectionKeyFor): array {
        $key = $collectionKeyFor($item);

        if (!$key) {
            return [];
        }

        $definition = collect([
            'led' => ['image' => 'led.jpg', 'outline' => 'led_outline.svg'],
            'expand' => ['image' => 'expand.jpg', 'outline' => 'expand_outline.svg'],
            'magnum' => ['image' => 'magnum_.jpg', 'outline' => 'magnum_outline.svg'],
            'big' => ['image' => 'big.png', 'outline' => 'big_outline.svg'],
            'tab' => ['image' => 'tab.jpg', 'outline' => 'tab_outline.svg'],
        ])->get($key);

        return [
            'key' => $key,
            'image' => b2c_theme_asset_url("teknikoshop/collections/{$definition['image']}"),
            'outline' => b2c_theme_asset_url("teknikoshop/collections/{$definition['outline']}"),
        ];
    };
    $categoryAssets = $collectionAssetsFor($category);
    $desktopChildren = $categoryChildren
        ->map(function ($child) use ($collectionAssetsFor) {
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
                'assets' => $collectionAssetsFor($child),
                'summary' => $grandchildren->take(2)->pluck('label')->filter()->implode(' · '),
            ];
        })
        ->filter()
        ->values();

    $isBackpackRoot = (($category['fam_code'] ?? null) === 'TZ')
        || str_contains(mb_strtolower($categoryLabel . ' ' . $categorySlug), 'zain')
        || str_contains(mb_strtolower($categoryLabel . ' ' . $categorySlug), 'backpack')
        || str_contains(mb_strtolower($categoryLabel . ' ' . $categorySlug), 'mochil');

    if ($isBackpackRoot) {
        $desktopChildren = $collectionDefinitions
            ->map(function (array $definition) use ($desktopChildren, $collectionAssetsFor, $catalogRepository, $locale, $store) {
                $matched = $desktopChildren->first(function ($child) use ($definition) {
                    $text = mb_strtolower(trim((string) (($child['label'] ?? '').' '.($child['slug'] ?? ''))));

                    return str_contains($text, $definition['key']);
                });
                $dynamicSlug = $store
                    ? $catalogRepository->buildCategorySlug($store, $locale, $definition['fam'], $definition['sfam'])
                    : '';

                return [
                    'slug' => $matched['slug'] ?? $dynamicSlug,
                    'label' => $matched['label'] ?? $definition['label'],
                    'assets' => $collectionAssetsFor(['label' => $definition['label'], 'slug' => $definition['key']]),
                    'summary' => $matched['summary'] ?? '',
                ];
            })
            ->filter(fn ($child) => filled($child['slug'] ?? null))
            ->values();
    }
@endphp
@if($categoryLabel !== '' && $categoryUrl)
    @if($mobile)
        <div class="ciak-mobile-category teknikoshop-mobile-category">
            <div>
                <a href="{{ $categoryUrl }}">
                    @if(!empty($categoryAssets['outline']))
                        <span class="teknikoshop-menu-icon"><img src="{{ $categoryAssets['outline'] }}" alt="" loading="lazy" decoding="async"></span>
                    @endif
                    <span>{{ $categoryLabel }}</span>
                </a>
                @if($categoryChildren->isNotEmpty())<button type="button" data-bs-toggle="collapse" data-bs-target="#teknikoshop-mobile-category-{{ md5($categorySlug) }}" aria-label="{{ __('themes_b2c.ciak.open_subcategories') }}" aria-expanded="false"><i data-lucide="chevron-down"></i></button>@endif
            </div>
            @if($categoryChildren->isNotEmpty())
                <div class="collapse ciak-mobile-children" id="teknikoshop-mobile-category-{{ md5($categorySlug) }}">
                    @foreach($categoryChildren as $child)
                        @if(!empty($child['slug']))
                            @php($childAssets = $collectionAssetsFor((array) $child))
                            <a href="{{ route('storefront.category.show', array_merge(['slug' => $child['slug']], $contextParams)) }}">
                                @if(!empty($childAssets['outline']))
                                    <span class="teknikoshop-menu-icon"><img src="{{ $childAssets['outline'] }}" alt="" loading="lazy" decoding="async"></span>
                                @endif
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
                                        @if(!empty($child['assets']['outline']))
                                            <img class="teknikoshop-mega-outline-icon" src="{{ $child['assets']['outline'] }}" alt="" loading="lazy" decoding="async">
                                        @else
                                            <i data-lucide="backpack"></i>
                                        @endif
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
