<?php

namespace App\Services\Storefront\StoreLocator;

use App\Models\Erp\DocumentHeader;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreLocatorLocation;
use App\Models\StoreVisibleGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreLocatorRepository
{
    public function locations(Store $store, ?Product $product = null, ?float $latitude = null, ?float $longitude = null, int $limit = 100): Collection
    {
        if ($store->is_b2b) {
            return collect();
        }

        $query = StoreLocatorLocation::query()
            ->forStore($store)
            ->active()
            ->geocoded()
            ->with(['customer', 'shippingAddress'])
            ->whereHas('customer', function (Builder $query) use ($store) {
                $query->active()
                    ->where('account_origin', 'erp')
                    ->where('ditta_cg18', (int) $store->ditta_cg18);
            });

        if ($product instanceof Product) {
            $buyerCliforIds = $this->buyerCliforIdsForProduct($store, $product);

            if ($buyerCliforIds->isEmpty()) {
                return collect();
            }

            $query->whereHas('customer', function (Builder $query) use ($store, $buyerCliforIds) {
                $query->where('ditta_cg18', (int) $store->ditta_cg18)
                    ->whereIn('clifor_cg44', $buyerCliforIds->all());
            });
        } else {
            $storeGroupCodes = $this->eligibleGroupCodes($store);

            if ($storeGroupCodes->isEmpty()) {
                return collect();
            }

            $query->whereExists(function ($sub) use ($storeGroupCodes) {
                $sub->selectRaw('1')
                    ->from('customer_visible_groups as cvg')
                    ->join('customers as c', 'c.id', '=', 'store_locator_locations.customer_id')
                    ->whereColumn('cvg.ditta_cg18', 'c.ditta_cg18')
                    ->whereColumn('cvg.tipocf_cg44', 'c.tipocf_cg44')
                    ->whereColumn('cvg.clifor_cg44', 'c.clifor_cg44')
                    ->where('cvg.is_active', 1)
                    ->whereIn('cvg.codice_xx32', $storeGroupCodes->all());
            });
        }

        if ($latitude !== null && $longitude !== null) {
            $query
                ->select('store_locator_locations.*')
                ->selectRaw(
                    '(6371 * acos(least(1, greatest(-1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))) as distance_km',
                    [$latitude, $longitude, $latitude]
                )
                ->orderBy('distance_km');
        } else {
            $query->orderBy('customer_id')->orderBy('source_type')->orderBy('id');
        }

        return $query
            ->limit(max(1, min($limit, 200)))
            ->get()
            ->map(fn (StoreLocatorLocation $location) => $this->present($location));
    }

    private function buyerCliforIdsForProduct(Store $store, Product $product): Collection
    {
        $skus = $this->productSkus($product);

        if ($skus->isEmpty()) {
            return collect();
        }

        $erp = DB::connection('erp');
        $erp->statement('SET ANSI_NULLS ON');
        $erp->statement('SET ANSI_WARNINGS ON');

        return $erp
            ->table('DOCCORPOBASE_DO30 as rows')
            ->join('DOCTESTATABASE_DO11 as headers', 'headers.NUMREG_CO99', '=', 'rows.NUMREG_CO99')
            ->where('headers.DITTA_CG18', (int) $store->ditta_cg18)
            ->whereIn('headers.TIPODOCDECOD_MG36', DocumentHeader::STORE_LOCATOR_DOCUMENT_TYPES)
            ->whereIn('rows.CODART_MG66', $skus->all())
            ->whereNotNull('headers.CLIFOR_CG44')
            ->distinct()
            ->pluck('headers.CLIFOR_CG44')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values();
    }

    private function productSkus(Product $product): Collection
    {
        $skus = collect([(string) $product->sku]);

        if ((string) $product->type === 'configurable') {
            $childSkus = Product::query()
                ->forContext((int) $product->ditta_cg18, (int) $product->site_type)
                ->active()
                ->where('type', 'simple')
                ->where('parent_code', (string) $product->sku)
                ->pluck('sku');

            $skus = $skus->merge($childSkus);
        }

        return $skus
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values();
    }

    public function productGroupCodes(Product $product): Collection
    {
        if ((string) $product->type === 'configurable') {
            return Product::query()
                ->forContext((int) $product->ditta_cg18, (int) $product->site_type)
                ->active()
                ->where('type', 'simple')
                ->where('parent_code', (string) $product->sku)
                ->whereNotNull('codgrupfis_mg61')
                ->pluck('codgrupfis_mg61')
                ->merge([$product->codgrupfis_mg61])
                ->map(fn ($code) => trim((string) $code))
                ->filter()
                ->unique()
                ->values();
        }

        return collect([$product->codgrupfis_mg61])
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values();
    }

    public function present(StoreLocatorLocation $location): array
    {
        $parts = $location->sourceAddressParts();
        $distance = $location->getAttribute('distance_km');

        return [
            'id' => $location->id,
            'name' => $location->sourceName(),
            'address' => $parts['address'] ?? null,
            'postcode' => $parts['postcode'] ?? null,
            'city' => $parts['city'] ?? null,
            'province' => $parts['province'] ?? null,
            'address_line' => $location->sourceAddressLine(),
            'phone' => $parts['phone'] ?? null,
            'email' => $parts['email'] ?? null,
            'latitude' => $location->latitude !== null ? (float) $location->latitude : null,
            'longitude' => $location->longitude !== null ? (float) $location->longitude : null,
            'distance_km' => $distance !== null ? round((float) $distance, 1) : null,
        ];
    }

    private function eligibleGroupCodes(Store $store): Collection
    {
        $storeVisibleGroups = StoreVisibleGroup::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->pluck('codice_xx32')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values();

        if ($storeVisibleGroups->isNotEmpty()) {
            return $storeVisibleGroups;
        }

        return Product::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->active()
            ->whereNotNull('codgrupfis_mg61')
            ->distinct()
            ->pluck('codgrupfis_mg61')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values();
    }
}