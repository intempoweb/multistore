@php
    $label = $item['label'] ?? $item['code'] ?? 'Categoria';
    $slug = $item['slug'] ?? null;
    $children = collect($item['children'] ?? []);
    $isActive = $slug && ($activeCategorySlug === $slug || str_starts_with($activeCategorySlug, $slug . '/'));
    $compact = $compact ?? false;
@endphp

@if($slug)
    <div class="ciak-nav-item {{ $compact ? 'is-compact' : '' }}">
        <a
            href="{{ route('storefront.category.show', array_merge(['slug' => $slug], $contextParams)) }}"
            class="ciak-nav-link {{ $isActive ? 'is-active' : '' }}"
        >
            <span>{{ $label }}</span>
            @if(!$compact && $children->isNotEmpty())
                <i data-lucide="chevron-down" aria-hidden="true"></i>
            @endif
        </a>

        @if(!$compact && $children->isNotEmpty())
            <div class="ciak-nav-panel" role="menu">
                <div class="ciak-nav-panel-inner">
                    <div class="ciak-nav-panel-head">
                        <div>
                            <span>{{ __('Collezione') }}</span>
                            <a href="{{ route('storefront.category.show', array_merge(['slug' => $slug], $contextParams)) }}" class="ciak-nav-panel-title">
                                {{ $label }}
                            </a>
                        </div>

                        <a href="{{ route('storefront.category.show', array_merge(['slug' => $slug], $contextParams)) }}" class="ciak-nav-panel-all">
                            {{ __('Vedi tutto') }}
                            <i data-lucide="arrow-up-right" aria-hidden="true"></i>
                        </a>
                    </div>

                    <div class="ciak-nav-panel-grid">
                        @foreach($children->take(8) as $child)
                            @php
                                $childLabel = $child['label'] ?? $child['code'] ?? 'Categoria';
                                $childSlug = $child['slug'] ?? null;
                                $grandChildren = collect($child['children'] ?? []);
                            @endphp

                            @if($childSlug)
                                <div class="ciak-nav-panel-group">
                                    <a href="{{ route('storefront.category.show', array_merge(['slug' => $childSlug], $contextParams)) }}" class="ciak-nav-panel-link">
                                        <span>{{ $childLabel }}</span>
                                        <i data-lucide="arrow-right" aria-hidden="true"></i>
                                    </a>

                                    @if($grandChildren->isNotEmpty())
                                        <div class="ciak-nav-panel-sublinks">
                                            @foreach($grandChildren->take(4) as $grandChild)
                                                @php
                                                    $grandChildLabel = $grandChild['label'] ?? $grandChild['code'] ?? null;
                                                    $grandChildSlug = $grandChild['slug'] ?? null;
                                                @endphp

                                                @if($grandChildSlug && $grandChildLabel)
                                                    <a href="{{ route('storefront.category.show', array_merge(['slug' => $grandChildSlug], $contextParams)) }}">
                                                        {{ $grandChildLabel }}
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif
