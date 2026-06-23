<aside class="storefront-sidebar d-flex flex-column gap-4" data-storefront-sidebar data-sidebar-context="{{ $sidebar['context'] }}">
    @if($sidebar['children']->isNotEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0"><h2 class="h6 mb-0">Sottocategorie</h2></div>
            <div class="card-body">
                <div class="d-flex flex-column gap-2">
                    @foreach($sidebar['children'] as $child)
                        <a href="{{ $child['url'] }}" class="btn btn-sm btn-outline-secondary text-start">{{ $child['label'] }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if($sidebar['facets']->isNotEmpty() || $sidebar['has_active_filters'] || !$sidebar['hide_empty_panel'])
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <h2 class="h6 mb-0">{{ $sidebar['title'] }}</h2>
                    @if($sidebar['has_active_filters'])
                        <a href="{{ $sidebar['reset_url'] }}" class="small text-decoration-none">Reset</a>
                    @endif
                </div>

                @if($sidebar['has_active_filters'])
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($sidebar['active_pills'] as $pill)
                            <button
                                type="button"
                                class="btn btn-sm btn-light border rounded-pill px-2 py-1 d-inline-flex align-items-center gap-1"
                                data-storefront-filter-pill
                                data-attribute-slug="{{ $pill['attribute_slug'] }}"
                                data-value-slug="{{ $pill['value_slug'] }}"
                            >
                                <span>{{ $pill['label'] }}</span><i class="fa-solid fa-xmark small"></i>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card-body">
                @if($sidebar['facets']->isEmpty())
                    <div class="text-muted small">{{ $sidebar['empty_message'] }}</div>
                @else
                    <form
                        method="GET"
                        action="{{ $sidebar['action_url'] }}"
                        class="accordion storefront-filter-accordion"
                        data-storefront-filters-form
                        data-storefront-filters-target="{{ $sidebar['ajax_target'] }}"
                        data-storefront-sidebar-target="{{ $sidebar['wrapper_target'] }}"
                    >
                        <div class="d-none small text-primary" data-storefront-filter-loading>
                            <i class="fa-solid fa-spinner fa-spin me-1"></i> Aggiornamento filtri...
                        </div>

                        @if($sidebar['agent_context_id'] !== '')
                            <input type="hidden" name="agent_context" value="{{ $sidebar['agent_context_id'] }}">
                        @endif

                        @foreach($sidebar['facets'] as $facet)
                            @if($facet['code'] !== '' && $facet['values']->isNotEmpty())
                                @php
                                    $facetSlug = (string) $facet['slug'];
                                    $isColorFacet = str_contains(strtolower($facetSlug), 'color') || str_contains(strtolower($facetSlug), 'colore');
                                    $accordionId = 'filter-' . md5($facetSlug);
                                    $hasCheckedValues = $facet['values']->contains(fn ($value) => !empty($value['checked']));
                                    $shouldOpen = $hasCheckedValues || $loop->first;
                                @endphp

                                <div class="storefront-sidebar-filter accordion-item border-0 border-bottom">
                                    <h3 class="accordion-header">
                                        <button
                                            class="accordion-button px-0 py-3 {{ $shouldOpen ? '' : 'collapsed' }}"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#{{ $accordionId }}"
                                            aria-expanded="{{ $shouldOpen ? 'true' : 'false' }}"
                                            aria-controls="{{ $accordionId }}"
                                        >
                                            <span class="fw-semibold small">{{ $facet['label'] }}</span>
                                        </button>
                                    </h3>

                                    <div
                                        id="{{ $accordionId }}"
                                        class="accordion-collapse collapse {{ $shouldOpen ? 'show' : '' }}"
                                    >
                                        <div class="accordion-body px-0 pt-0 pb-3">
                                            <div class="d-flex flex-column gap-2">
                                                @foreach($facet['values'] as $value)
                                                    <div class="form-check d-flex align-items-center gap-2">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            name="{{ $facet['slug'] }}[]"
                                                            value="{{ $value['slug'] }}"
                                                            id="{{ $value['input_id'] }}"
                                                            data-storefront-filter-input
                                                            data-attribute-slug="{{ $facet['slug'] }}"
                                                            data-value-slug="{{ $value['slug'] }}"
                                                            data-filter-mode="{{ $isColorFacet ? 'single' : 'multiple' }}"
                                                            @checked($value['checked'])
                                                        >
                                                        <label class="form-check-label small flex-grow-1" for="{{ $value['input_id'] }}">
                                                            @if($value['swatch_url'])
                                                                <span class="d-inline-flex align-middle border rounded-circle overflow-hidden me-1" style="width:16px;height:16px;">
                                                                    <img src="{{ $value['swatch_url'] }}" alt="{{ $value['label'] }}" style="width:100%;height:100%;object-fit:cover;">
                                                                </span>
                                                            @endif
                                                            {{ $value['label'] }}
                                                        </label>
                                                        <span class="badge text-bg-light border">{{ $value['count'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </form>
                @endif
            </div>
        </div>
    @endif
</aside>
