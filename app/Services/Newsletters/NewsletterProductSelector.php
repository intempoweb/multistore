<?php

namespace App\Services\Newsletters;

use App\Models\Newsletter;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NewsletterProductSelector
{
    public function select(Newsletter $newsletter, ?array $manualSkus = null): Collection
    {
        $store = $newsletter->store;

        if (! $store instanceof Store) {
            return collect();
        }

        if ($manualSkus !== null && $manualSkus !== []) {
            $positions = collect($manualSkus)
                ->values()
                ->flip();

            return $this->baseQuery($store)
                ->whereIn('sku', $manualSkus)
                ->get()
                ->sortBy(fn (Product $product) => (int) ($positions[(string) $product->sku] ?? 999999))
                ->values();
        }

        if ($newsletter->selection_type === Newsletter::SELECTION_MANUAL) {
            return collect();
        }

        $query = $this->baseQuery($store);
        $types = $this->selectionTypes($newsletter);

        if ($types === []) {
            return collect();
        }

        $query->where(function (Builder $criteria) use ($types) {
            foreach ($types as $type) {
                $criteria->orWhere(function (Builder $q) use ($type) {
                    match ($type) {
                        Newsletter::SELECTION_OFFERS => $q->currentOffer(),
                        Newsletter::SELECTION_PROMOTIONS => $q->currentPromotion(),
                        Newsletter::SELECTION_NEW_PRODUCTS => $q->currentNewProduct(),
                        Newsletter::SELECTION_CAMPAIGNS => $q->currentCampaign(),
                        default => $q->whereRaw('1 = 0'),
                    };
                });
            }
        });

        foreach ((array) ($newsletter->filters ?? []) as $field => $value) {
            $value = Product::normalizeErpCodeValue(is_string($value) ? $value : null);

            if ($value !== null && in_array($field, $this->allowedFilterFields(), true)) {
                $query->where($field, $value);
            }
        }

        return $query->orderBy('sku')->limit(250)->get()->values();
    }

    public function syncProducts(Newsletter $newsletter, Collection $products): void
    {
        $payload = $products
            ->values()
            ->mapWithKeys(fn (Product $product, int $index) => [
                $product->id => ['sort_order' => ($index + 1) * 10],
            ])
            ->all();

        $newsletter->products()->sync($payload);
    }

    public function manualSkusFromText(?string $text): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $text) ?: [])
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function baseQuery(Store $store): Builder
    {
        return Product::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->active()
            ->simple();
    }

    private function selectionTypes(Newsletter $newsletter): array
    {
        if ($newsletter->selection_type !== Newsletter::SELECTION_MIXED) {
            return [$newsletter->selection_type];
        }

        return collect((array) data_get($newsletter->settings, 'selection_types', []))
            ->filter(fn ($type) => in_array($type, [
                Newsletter::SELECTION_OFFERS,
                Newsletter::SELECTION_PROMOTIONS,
                Newsletter::SELECTION_NEW_PRODUCTS,
                Newsletter::SELECTION_CAMPAIGNS,
            ], true))
            ->values()
            ->all();
    }

    private function allowedFilterFields(): array
    {
        return [
            'fam_99',
            'sfam_99',
            'gruppo_99',
            'sgruppo_99',
            'marca_mg64',
            'codlinea_w55',
            'codedizione_w56',
            'codcollezione_w57',
            'codbrand_w58',
        ];
    }
}
