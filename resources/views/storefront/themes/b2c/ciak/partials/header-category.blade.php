@php
    $categoryLabel = trim((string) ($category['label'] ?? ''));
    $categorySlug = trim((string) ($category['slug'] ?? ''));
    $categoryChildren = collect($category['children'] ?? []);
    $mobile = (bool) ($mobile ?? false);
    $contextParams = $contextParams ?? [];
    $categoryUrl = $categorySlug !== ''
        ? route('storefront.category.show', array_merge(['slug' => $categorySlug], $contextParams))
        : null;
@endphp
@if($categoryLabel !== '' && $categoryUrl)
    @if($mobile)
        <div class="ciak-mobile-category">
            <div><a href="{{ $categoryUrl }}">{{ $categoryLabel }}</a>@if($categoryChildren->isNotEmpty())<button type="button" data-bs-toggle="collapse" data-bs-target="#ciak-mobile-category-{{ md5($categorySlug) }}" aria-label="{{ __('Apri sottocategorie') }}"><i data-lucide="chevron-down"></i></button>@endif</div>
            @if($categoryChildren->isNotEmpty())
                <div class="collapse ciak-mobile-children" id="ciak-mobile-category-{{ md5($categorySlug) }}">
                    @foreach($categoryChildren as $child)
                        @if(!empty($child['slug']))
                            <a href="{{ route('storefront.category.show', array_merge(['slug' => $child['slug']], $contextParams)) }}">{{ $child['label'] ?? $child['code'] }}</a>
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
            <a class="ciak-nav-link {{ $categoryChildren->isNotEmpty() ? 'dropdown-toggle' : '' }}" href="{{ $categoryUrl }}" @if($categoryChildren->isNotEmpty()) data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" @endif>{{ $categoryLabel }}</a>
            @if($categoryChildren->isNotEmpty())
                <div class="dropdown-menu ciak-megamenu">
                    <div class="ciak-megamenu-heading"><a href="{{ $categoryUrl }}">{{ $categoryLabel }}</a></div>
                    <div class="ciak-megamenu-grid">
                        @foreach($categoryChildren as $child)
                            <section>
                                @if(!empty($child['slug']))<a class="ciak-megamenu-second" href="{{ route('storefront.category.show', array_merge(['slug' => $child['slug']], $contextParams)) }}">{{ $child['label'] ?? $child['code'] }}</a>@endif
                                @foreach(collect($child['children'] ?? []) as $grandchild)
                                    @if(!empty($grandchild['slug']))<a class="ciak-megamenu-third" href="{{ route('storefront.category.show', array_merge(['slug' => $grandchild['slug']], $contextParams)) }}">{{ $grandchild['label'] ?? $grandchild['code'] }}</a>@endif
                                @endforeach
                            </section>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
@endif
