@php
    use Illuminate\Support\Str;

    $slug = $slug ?? request()->route('slug');
    $filterFacets = collect($filterFacets ?? []);
    $activeFilters = collect($activeFilters ?? []);
    $childrenCategories = collect($childrenCategories ?? []);

    $agentContextId = $agentContextId ?? (string) request('agent_context', '');
    $contextParams = $contextParams ?? ($agentContextId !== '' ? ['agent_context' => $agentContextId] : []);

    $hasActiveFilters = $activeFilters
        ->flatMap(fn ($values) => is_array($values) ? $values : [$values])
        ->filter(fn ($value) => trim((string) $value) !== '')
        ->isNotEmpty();

    $sidebarTitle = $sidebarTitle ?? 'Filtri';
    $sidebarContext = $sidebarContext ?? 'category';
    $sidebarResetUrl = $sidebarResetUrl ?? ($slug ? route('storefront.category.show', array_merge(['slug' => $slug], $contextParams)) : url()->current());
    $sidebarActionUrl = $sidebarActionUrl ?? ($slug ? route('storefront.category.show', array_merge(['slug' => $slug], $contextParams)) : url()->current());
    $sidebarAjaxTarget = $sidebarAjaxTarget ?? '.storefront-product-results';
    $sidebarWrapperTarget = $sidebarWrapperTarget ?? '.storefront-sidebar-wrapper';

    $emptyFiltersMessage = $emptyFiltersMessage
        ?? 'Nessun attributo filtrabile disponibile sui prodotti semplici o sulle varianti di questa categoria.';
@endphp

<aside class="storefront-sidebar d-flex flex-column gap-4" data-storefront-sidebar data-sidebar-context="{{ $sidebarContext }}">
    @if($childrenCategories->isNotEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h2 class="h6 mb-0">Sottocategorie</h2>
            </div>

            <div class="card-body">
                <div class="d-flex flex-column gap-2">
                    @foreach($childrenCategories as $childCategory)
                        @php
                            $childSlug = $childCategory['slug'] ?? null;
                            $childLabel = $childCategory['label'] ?? $childCategory['code'] ?? 'Categoria';
                        @endphp

                        @if($childSlug)
                            <a href="{{ route('storefront.category.show', array_merge(['slug' => $childSlug], $contextParams)) }}" class="btn btn-sm btn-outline-secondary text-start">
                                {{ $childLabel }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <h2 class="h6 mb-0">{{ $sidebarTitle }}</h2>

                @if($hasActiveFilters)
                    <a href="{{ $sidebarResetUrl }}" class="small text-decoration-none">
                        Reset
                    </a>
                @endif
            </div>

    @if($hasActiveFilters)
        <div class="d-flex flex-wrap gap-2">
            @foreach($filterFacets as $facet)
                @php
                    $facetCode = (string) ($facet['code'] ?? '');
                    $facetLabel = (string) ($facet['label'] ?? $facetCode);
                    $facetSlug = (string) ($facet['slug'] ?? Str::slug($facetLabel));
                    $selectedValues = collect($facet['active_values'] ?? ($activeFilters[$facetCode] ?? []))
                        ->map(fn ($value) => trim((string) $value))
                        ->filter()
                        ->values();

                    $valuesByKey = collect($facet['values'] ?? [])->keyBy(fn ($value) => (string) ($value['key'] ?? ''));
                @endphp

                @foreach($selectedValues as $selectedValue)
                    @php
                        $value = $valuesByKey->get($selectedValue);
                        $valueLabel = (string) ($value['label'] ?? $selectedValue);
                        $valueSlug = (string) ($value['slug'] ?? Str::slug($valueLabel));
                    @endphp

                    <button
                        type="button"
                        class="btn btn-sm btn-light border rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1"
                        data-storefront-filter-pill
                        data-attribute-slug="{{ $facetSlug }}"
                        data-value-slug="{{ $valueSlug }}"
                    >
                        <span>{{ $facetLabel }}: {{ $valueLabel }}</span>
                        <i class="fa-solid fa-xmark small"></i>
                    </button>
                @endforeach
            @endforeach
        </div>
    @endif
</div>

        <div class="card-body">
            @if($filterFacets->isEmpty())
                <div class="text-muted small">
                    {{ $emptyFiltersMessage }}
                </div>
            @else
                <form
                    method="GET"
                    action="{{ $sidebarActionUrl }}"
                    class="d-flex flex-column gap-4"
                    data-storefront-filters-form
                    data-storefront-filters-target="{{ $sidebarAjaxTarget }}"
                    data-storefront-sidebar-target="{{ $sidebarWrapperTarget }}"
                >
                    <div class="d-none small text-primary" data-storefront-filter-loading>
                        <i class="fa-solid fa-spinner fa-spin me-1"></i>
                        Aggiornamento filtri...
                    </div>

                    @if($agentContextId !== '')
                        <input type="hidden" name="agent_context" value="{{ $agentContextId }}">
                    @endif

                    @foreach($filterFacets as $facet)
                        @php
                            $facetCode = (string) ($facet['code'] ?? '');
                            $facetLabel = (string) ($facet['label'] ?? $facetCode);
                            $facetSlug = (string) ($facet['slug'] ?? Str::slug($facetLabel));
                            $facetValues = collect($facet['values'] ?? []);
                            $selectedValues = collect($facet['active_values'] ?? ($activeFilters[$facetCode] ?? []))
                                ->map(fn ($value) => trim((string) $value))
                                ->filter()
                                ->values();
                        @endphp

                        @if($facetCode !== '' && $facetValues->isNotEmpty())
                            <div class="storefront-sidebar-filter">
                                <div class="fw-semibold small mb-2">
                                    {{ $facetLabel }}
                                </div>

                                <div class="d-flex flex-column gap-2">
                                    @foreach($facetValues as $value)
                                        @php
                                            $valueKey = (string) ($value['key'] ?? '');
                                            $valueLabel = (string) ($value['label'] ?? $valueKey);
                                            $valueSlug = (string) ($value['slug'] ?? Str::slug($valueLabel));
                                            $valueCount = (int) ($value['count'] ?? 0);
                                            $inputId = 'filter_' . md5($facetCode . '_' . $valueKey);
                                            $isChecked = $selectedValues->contains($valueKey);
                                        @endphp

                                        @if($valueKey !== '')
                                            <div class="form-check d-flex align-items-center gap-2">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="{{ $facetSlug }}[]"
                                                    value="{{ $valueSlug }}"
                                                    id="{{ $inputId }}"
                                                    data-storefront-filter-input
                                                    data-attribute-slug="{{ $facetSlug }}"
                                                    data-value-slug="{{ $valueSlug }}"
                                                    @checked($isChecked)
                                                >

                                                <label class="form-check-label small flex-grow-1" for="{{ $inputId }}">
                                                    @if(!empty($value['swatch_url']))
                                                        <span class="d-inline-flex align-middle border rounded-circle overflow-hidden me-1" style="width:16px;height:16px;">
                                                            <img src="{{ $value['swatch_url'] }}" alt="{{ $valueLabel }}" style="width:100%;height:100%;object-fit:cover;">
                                                        </span>
                                                    @endif

                                                    {{ $valueLabel }}
                                                </label>

                                                <span class="badge text-bg-light border">
                                                    {{ $valueCount }}
                                                </span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </form>
            @endif
        </div>
    </div>
</aside>