<?php

namespace App\Services\Storefront\Catalog;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class CatalogRequestNormalizer
{
    private const ALLOWED_SORTS = [
        'default',
        'sku_asc',
        'sku_desc',
        'name_asc',
        'name_desc',
        'price_asc',
        'price_desc',
        'newest',
    ];

    private const RESERVED_QUERY_KEYS = [
        'page',
        'filters',
        'sort',
        'grid',
    ];

    public function sort(mixed $sort): string
    {
        $sort = (string) $sort;

        return in_array($sort, self::ALLOWED_SORTS, true) ? $sort : 'default';
    }

    public function filters(
        Request $request,
        Collection $filterFacets,
        array $additionalReservedKeys = [],
    ): array {
        $reservedKeys = array_merge(self::RESERVED_QUERY_KEYS, $additionalReservedKeys);

        $query = collect($request->query())
            ->reject(fn ($value, string|int $key) => in_array((string) $key, $reservedKeys, true));

        if ($query->isEmpty() || $filterFacets->isEmpty()) {
            return [];
        }

        $facetsBySlug = $filterFacets
            ->filter(fn ($facet) => ! empty($facet['slug']) && ! empty($facet['code']))
            ->keyBy(fn ($facet) => (string) $facet['slug']);

        $filters = [];

        foreach ($query as $attributeSlug => $values) {
            $facet = $facetsBySlug->get(Str::slug((string) $attributeSlug));

            if (! $facet) {
                continue;
            }

            $attributeCode = (string) ($facet['code'] ?? '');
            $facetValues = collect($facet['values'] ?? [])
                ->filter(fn ($value) => ! empty($value['slug']) && ! empty($value['key']))
                ->keyBy(fn ($value) => (string) $value['slug']);

            foreach (collect(is_array($values) ? $values : [$values])
                ->map(fn ($value) => Str::slug((string) $value))
                ->filter()
                ->unique() as $valueSlug) {
                $value = $facetValues->get($valueSlug);

                if (! $value) {
                    continue;
                }

                $filters[$attributeCode] ??= [];
                $filters[$attributeCode][] = (string) $value['key'];
            }
        }

        return collect($filters)
            ->map(fn ($values) => collect($values)->unique()->values()->all())
            ->filter(fn ($values) => ! empty($values))
            ->all();
    }
}
