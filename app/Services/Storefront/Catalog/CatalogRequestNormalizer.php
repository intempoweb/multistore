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

        return in_array($sort, self::ALLOWED_SORTS, true)
            ? $sort
            : 'default';
    }

    public function filters(
        Request $request,
        Collection $filterFacets,
        array $additionalReservedKeys = [],
    ): array {
        $reservedKeys = array_merge(
            self::RESERVED_QUERY_KEYS,
            $additionalReservedKeys
        );

        $query = collect($request->query())
            ->reject(
                fn ($value, string|int $key) =>
                    in_array((string) $key, $reservedKeys, true)
            );

        if ($query->isEmpty() || $filterFacets->isEmpty()) {
            return [];
        }

        $facetsBySlug = $filterFacets
            ->filter(
                fn ($facet) =>
                    ! empty($facet['slug'])
                    && ! empty($facet['code'])
            )
            ->keyBy(
                fn ($facet) =>
                    Str::slug((string) $facet['slug'])
            );

        $filters = [];

        foreach ($query as $attributeSlug => $values) {
            $facet = $facetsBySlug->get(
                Str::slug((string) $attributeSlug)
            );

            if (! $facet) {
                continue;
            }

            $attributeCode = (string) ($facet['code'] ?? '');

            if ($attributeCode === '') {
                continue;
            }

            $facetValues = collect($facet['values'] ?? [])
                ->filter(
                    fn ($value) =>
                        ! empty($value['slug'])
                        && ! empty($value['key'])
                )
                ->keyBy(
                    fn ($value) =>
                        Str::slug((string) $value['slug'])
                );

            foreach ($this->queryValues($values) as $valueSlug) {
                $value = $facetValues->get($valueSlug);

                if (! $value) {
                    continue;
                }

                $filters[$attributeCode] ??= [];

                $filters[$attributeCode][] =
                    (string) $value['key'];
            }
        }

        return collect($filters)
            ->map(
                fn ($values) =>
                    collect($values)
                        ->unique()
                        ->values()
                        ->all()
            )
            ->filter(
                fn ($values) =>
                    ! empty($values)
            )
            ->all();
    }

    public function publicQuery(
        Request $request,
        Collection $filterFacets
    ): array {
        $query = $request->query();

        $facetSlugs = $filterFacets
            ->pluck('slug')
            ->map(fn ($slug) => trim((string) $slug))
            ->filter()
            ->unique()
            ->values();

        foreach ($facetSlugs as $facetSlug) {
            if (! array_key_exists($facetSlug, $query)) {
                continue;
            }

            $values = $this->rawQueryValues(
                $query[$facetSlug]
            );

            if ($values->isEmpty()) {
                unset($query[$facetSlug]);

                continue;
            }

            $query[$facetSlug] = $values->implode(',');
        }

        return $query;
    }

    private function queryValues(mixed $values): Collection
    {
        return $this->rawQueryValues($values)
            ->map(fn ($value) => Str::slug($value))
            ->filter()
            ->unique()
            ->values();
    }

    private function rawQueryValues(mixed $values): Collection
    {
        $normalized = [];

        $rawValues = is_array($values)
            ? $values
            : [$values];

        foreach ($rawValues as $rawValue) {
            if (is_array($rawValue)) {
                foreach ($rawValue as $nestedValue) {
                    $this->appendRawQueryValue(
                        $normalized,
                        $nestedValue
                    );
                }

                continue;
            }

            foreach (explode(',', (string) $rawValue) as $value) {
                $this->appendRawQueryValue(
                    $normalized,
                    $value
                );
            }
        }

        return collect($normalized)
            ->filter()
            ->unique()
            ->values();
    }

    private function appendRawQueryValue(
        array &$normalized,
        mixed $value
    ): void {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        $normalized[] = $value;
    }
}