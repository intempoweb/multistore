<?php

namespace App\Services\Storefront\ViewData;

use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class StorefrontSidebarDataBuilder
{
    public function __construct(
        private Request $request,
    ) {}

    public function build(array $data): array
    {
        $slug = $data['slug'] ?? $this->request->route('slug');
        $facets = collect($data['filterFacets'] ?? []);
        $activeFilters = collect($data['activeFilters'] ?? []);
        $children = collect($data['childrenCategories'] ?? []);

        $facetSortOrders = Attribute::query()
            ->whereIn('code', $facets->pluck('code')->filter()->unique()->values())
            ->pluck('sort_order', 'code');

        $agentContextId = (string) ($data['agentContextId'] ?? $this->request->input('agent_context', ''));
        $contextParams = $data['contextParams'] ?? ($agentContextId !== '' ? ['agent_context' => $agentContextId] : []);

        $defaultUrl = $slug
            ? route('storefront.category.show', array_merge(['slug' => $slug], $contextParams))
            : $this->request->url();

        $preparedFacets = $facets
            ->map(fn ($facet) => $this->facet($facet, $activeFilters, $facetSortOrders))
            ->sortBy([
                fn ($facet) => (int) ($facet['sort_order'] ?? 999),
                fn ($facet) => (string) ($facet['label'] ?? ''),
            ])
            ->values();

        $activePills = $preparedFacets->flatMap(fn ($facet) => $facet['pills'])->values();

        return [
            'title' => $data['sidebarTitle'] ?? 'Filtri',
            'context' => $data['sidebarContext'] ?? 'category',
            'reset_url' => $data['sidebarResetUrl'] ?? $defaultUrl,
            'action_url' => $data['sidebarActionUrl'] ?? $defaultUrl,
            'ajax_target' => $data['sidebarAjaxTarget'] ?? '.storefront-product-results',
            'wrapper_target' => $data['sidebarWrapperTarget'] ?? '.storefront-sidebar-wrapper',
            'empty_message' => $data['emptyFiltersMessage']
                ?? 'Nessun attributo filtrabile disponibile sui prodotti semplici o sulle varianti di questa categoria.',
            'hide_empty_panel' => (bool) ($data['hideEmptyFilterPanel'] ?? false),
            'agent_context_id' => $agentContextId,
            'has_active_filters' => $activePills->isNotEmpty(),
            'children' => $children->map(function ($child) use ($contextParams) {
                $childSlug = $child['slug'] ?? null;

                return [
                    'label' => $child['label'] ?? $child['code'] ?? 'Categoria',
                    'url' => $childSlug
                        ? route('storefront.category.show', array_merge(['slug' => $childSlug], $contextParams))
                        : null,
                ];
            })->filter(fn ($child) => filled($child['url']))->values(),
            'facets' => $preparedFacets,
            'active_pills' => $activePills,
        ];
    }

    private function facet(array $facet, Collection $activeFilters, Collection $facetSortOrders): array
    {
        $code = (string) ($facet['code'] ?? '');
        $label = (string) ($facet['label'] ?? $code);
        $slug = (string) ($facet['slug'] ?? Str::slug($label));

        $selected = collect($facet['active_values'] ?? $activeFilters->get($code, []))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        $values = collect($facet['values'] ?? [])->map(function ($value) use ($code, $selected) {
            $key = (string) ($value['key'] ?? '');
            $label = (string) ($value['label'] ?? $key);

            return [
                'key' => $key,
                'label' => $label,
                'slug' => (string) ($value['slug'] ?? Str::slug($label)),
                'count' => (int) ($value['count'] ?? 0),
                'swatch_url' => $value['swatch_url'] ?? null,
                'input_id' => 'filter_'.md5($code.'_'.$key),
                'checked' => $selected->contains($key),
            ];
        })->filter(fn ($value) => $value['key'] !== '')->values();

        $valuesByKey = $values->keyBy('key');

        return [
            'code' => $code,
            'label' => $label,
            'slug' => $slug,
            'sort_order' => (int) ($facetSortOrders->get($code, $facet['sort_order'] ?? 999)),
            'values' => $values,
            'pills' => $selected->map(function ($key) use ($label, $slug, $valuesByKey) {
                $value = $valuesByKey->get($key);

                return [
                    'label' => $label.': '.($value['label'] ?? $key),
                    'attribute_slug' => $slug,
                    'value_slug' => $value['slug'] ?? Str::slug($key),
                ];
            })->values(),
        ];
    }
}