<?php

namespace App\Services\Visibility;

use App\Models\CustomerVisibleGroup;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductVisibilityService
{
    /**
     * Query base: prodotti visibili per un cliente (B2B) in uno store.
     *
     * Regola:
     * - prodotto ∈ gruppi store  (store_visible_groups)
     * - prodotto ∈ gruppi cliente (customer_visible_groups attivi)
     * - eccezione: se il cliente non ha alcun gruppo attivo, vale solo la
     *   visibilità store per non nascondere cataloghi appena abilitati.
     *
     * NOTE:
     * - Join su products.codgrupfis_mg61 (codice gruppo fisico)
     * - Filtra anche per contesto products.site_type = $siteType (per coerenza catalogo)
     */
    public function visibleProductsQuery(
        int $ditta,
        int $siteType,
        int $tipoCf,
        int $clifor
    ): Builder {
        $query = Product::query()
            ->from('products as p')
            ->select('p.*')
            ->where('p.ditta_cg18', $ditta)
            ->where('p.site_type', $siteType)
            ->whereNotNull('p.codgrupfis_mg61')
            ->join('store_visible_groups as svg', function ($join) use ($siteType) {
                $join->on('svg.ditta_cg18', '=', 'p.ditta_cg18')
                    ->on('svg.codice_xx32', '=', 'p.codgrupfis_mg61')
                    ->where('svg.site_type', '=', $siteType);
            });

        if ($this->customerHasActiveGroups($ditta, $tipoCf, $clifor)) {
            $query->join('customer_visible_groups as cvg', function ($join) use ($tipoCf, $clifor) {
                $join->on('cvg.ditta_cg18', '=', 'p.ditta_cg18')
                    ->on('cvg.codice_xx32', '=', 'p.codgrupfis_mg61')
                    ->where('cvg.tipocf_cg44', '=', $tipoCf)
                    ->where('cvg.clifor_cg44', '=', $clifor)
                    ->where('cvg.is_active', '=', 1);
            });
        }

        return $query->distinct('p.id');
    }

    /**
     * Shortcut: ritorna solo gli ID dei prodotti visibili (utile per join ulteriori).
     *
     * @return array<int>
     */
    public function visibleProductIds(
        int $ditta,
        int $siteType,
        int $tipoCf,
        int $clifor
    ): array {
        return $this->visibleProductsQuery($ditta, $siteType, $tipoCf, $clifor)
            ->pluck('p.id')
            ->all();
    }

    /**
     * Shortcut: paginazione standard.
     */
    public function paginateVisibleProducts(
        int $ditta,
        int $siteType,
        int $tipoCf,
        int $clifor,
        int $perPage = 24
    ) {
        return $this->visibleProductsQuery($ditta, $siteType, $tipoCf, $clifor)
            ->orderBy('p.sku')
            ->paginate($perPage);
    }

    public function customerHasActiveGroups(int $ditta, int $tipoCf, int $clifor): bool
    {
        return CustomerVisibleGroup::query()
            ->where('ditta_cg18', $ditta)
            ->where('tipocf_cg44', $tipoCf)
            ->where('clifor_cg44', $clifor)
            ->where('is_active', true)
            ->exists();
    }
}
