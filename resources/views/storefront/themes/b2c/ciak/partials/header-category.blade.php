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
            {{ $label }}
        </a>

        @if(!$compact && $children->isNotEmpty())
            <div class="ciak-nav-panel">
                <a href="{{ route('storefront.category.show', array_merge(['slug' => $slug], $contextParams)) }}" class="ciak-nav-panel-title">
                    Tutto {{ $label }}
                </a>

                <div class="ciak-nav-panel-grid">
                    @foreach($children as $child)
                        @php
                            $childLabel = $child['label'] ?? $child['code'] ?? 'Categoria';
                            $childSlug = $child['slug'] ?? null;
                            $grandChildren = collect($child['children'] ?? []);
                        @endphp

                        @if($childSlug)
                            <div>
                                <a href="{{ route('storefront.category.show', array_merge(['slug' => $childSlug], $contextParams)) }}" class="ciak-nav-panel-link">
                                    {{ $childLabel }}
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
        @endif
    </div>
@endif
