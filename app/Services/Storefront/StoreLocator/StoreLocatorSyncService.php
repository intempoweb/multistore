<?php

namespace App\Services\Storefront\StoreLocator;

use App\Models\Customer;
use App\Models\CustomerShippingAddress;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreLocatorLocation;
use App\Models\StoreVisibleGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StoreLocatorSyncService
{
    public function sync(?int $storeId = null, bool $geocode = false, ?int $limit = null): array
    {
        $stats = [
            'stores_processed' => 0,
            'stores_skipped_without_groups' => 0,
            'customers_read' => 0,
            'locations_upserted' => 0,
            'locations_skipped_without_address' => 0,
            'locations_deactivated' => 0,
            'geocoded' => 0,
            'geocode_failed' => 0,
        ];

        $stores = Store::query()
            ->active()
            ->where('is_b2b', false)
            ->when($storeId !== null, fn (Builder $query) => $query->where('id', $storeId))
            ->orderBy('id')
            ->get();

        foreach ($stores as $store) {
            $this->syncStore($store, $geocode, $limit, $stats);
        }

        return $stats;
    }

    private function syncStore(Store $store, bool $geocode, ?int $limit, array &$stats): void
    {
        $storeGroupCodes = $this->storeGroupCodes($store);

        if ($storeGroupCodes->isEmpty()) {
            $stats['stores_skipped_without_groups']++;
            return;
        }

        $stats['stores_processed']++;
        $processedCustomers = 0;
        $processedSourceKeys = [];

        $customersQuery = Customer::query()
            ->active()
            ->where('account_origin', 'erp')
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->whereNotNull('tipocf_cg44')
            ->whereNotNull('clifor_cg44')
            ->orderBy('id');

        $this->applyExcludedCustomers($customersQuery);
        $this->applyCustomerGroupVisibility($customersQuery, $storeGroupCodes);

        $customersQuery->chunkById(200, function ($customers) use ($store, $geocode, $limit, &$processedCustomers, &$processedSourceKeys, &$stats) {
            foreach ($customers as $customer) {
                if ($limit !== null && $processedCustomers >= $limit) {
                    return false;
                }

                $processedCustomers++;
                $stats['customers_read']++;

                $mainSourceKey = 'customer:' . $customer->id;
                $processedSourceKeys[$mainSourceKey] = $mainSourceKey;

                $this->upsertMainLocation($store, $customer, $geocode, $stats);

                $shippingAddresses = CustomerShippingAddress::query()
                    ->forCustomer((int) $customer->ditta_cg18, (int) $customer->tipocf_cg44, (int) $customer->clifor_cg44)
                    ->active()
                    ->orderBy('coddestin_mg22')
                    ->get();

                foreach ($shippingAddresses as $shippingAddress) {
                    $shippingSourceKey = 'shipping:' . $shippingAddress->id;
                    $processedSourceKeys[$shippingSourceKey] = $shippingSourceKey;

                    $this->upsertShippingLocation($store, $customer, $shippingAddress, $geocode, $stats);
                }
            }

            return true;
        });

        if ($limit === null) {
            $processedSourceKeys = array_values($processedSourceKeys);

            $deactivateQuery = StoreLocatorLocation::query()
                ->forStore($store)
                ->where('is_active', true);

            if (!empty($processedSourceKeys)) {
                $deactivateQuery->whereNotIn('source_key', $processedSourceKeys);
            }

            $stats['locations_deactivated'] += $deactivateQuery->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
        }
    }

    private function upsertMainLocation(Store $store, Customer $customer, bool $geocode, array &$stats): void
    {
        $parts = $this->mainAddressParts($customer);
        $this->upsertLocation(
            store: $store,
            customer: $customer,
            shippingAddress: null,
            sourceType: 'main',
            sourceKey: 'customer:' . $customer->id,
            addressParts: $parts,
            geocode: $geocode,
            stats: $stats
        );
    }

    private function upsertShippingLocation(Store $store, Customer $customer, CustomerShippingAddress $shippingAddress, bool $geocode, array &$stats): void
    {
        $parts = [
            'address' => $shippingAddress->destind_mg22,
            'postcode' => $shippingAddress->destcap_mg22,
            'city' => $shippingAddress->destcitta_mg22,
            'province' => $shippingAddress->destprov_mg22,
            'country' => null,
        ];

        if ($this->addressFingerprint($parts) === $this->addressFingerprint($this->mainAddressParts($customer))) {
            return;
        }

        if (
            blank($parts['address'])
            || (blank($parts['postcode']) && blank($parts['city']) && blank($parts['province']))
        ) {
            $stats['locations_skipped_without_address']++;
            return;
        }

        $this->upsertLocation(
            store: $store,
            customer: $customer,
            shippingAddress: $shippingAddress,
            sourceType: 'shipping',
            sourceKey: 'shipping:' . $shippingAddress->id,
            addressParts: $parts,
            geocode: $geocode,
            stats: $stats
        );
    }

    private function upsertLocation(
        Store $store,
        Customer $customer,
        ?CustomerShippingAddress $shippingAddress,
        string $sourceType,
        string $sourceKey,
        array $addressParts,
        bool $geocode,
        array &$stats
    ): void {
        $addressLine = $this->addressLine($addressParts);

        if ($addressLine === '') {
            $stats['locations_skipped_without_address']++;
            return;
        }

        $fingerprint = $this->addressFingerprint($addressParts);

        $location = StoreLocatorLocation::query()->firstOrNew([
            'store_id' => (int) $store->id,
            'source_key' => $sourceKey,
        ]);

        $fingerprintChanged = !$location->exists || $location->address_fingerprint !== $fingerprint;

        $location->fill([
            'customer_id' => (int) $customer->id,
            'customer_shipping_address_id' => $shippingAddress?->id,
            'source_type' => $sourceType,
            'address_fingerprint' => $fingerprint,
            'is_active' => true,
        ]);

        if ($fingerprintChanged) {
            $location->latitude = null;
            $location->longitude = null;
            $location->geocoded_at = null;
            $location->geocode_status = 'pending';
            $location->geocode_error = null;
        }

        $location->save();
        $stats['locations_upserted']++;

        if ($geocode && ($fingerprintChanged || !$location->latitude || !$location->longitude)) {
            $this->geocodeLocation($location, $addressLine, $stats);
        }
    }

    private function geocodeLocation(StoreLocatorLocation $location, string $addressLine, array &$stats): void
    {
        $result = app(GoogleMapsGeocodingService::class)->geocode($addressLine);

        $location->forceFill([
            'latitude' => $result['latitude'] ?? null,
            'longitude' => $result['longitude'] ?? null,
            'geocoded_at' => ($result['ok'] ?? false) ? now() : null,
            'geocode_status' => (string) ($result['status'] ?? 'unknown'),
            'geocode_error' => $result['error'] ?? null,
        ])->save();

        if ($result['ok'] ?? false) {
            $stats['geocoded']++;
        } else {
            $stats['geocode_failed']++;
        }
    }

    private function storeGroupCodes(Store $store): Collection
    {
        $storeVisibleGroups = StoreVisibleGroup::query()
            ->forContext((int) $store->ditta_cg18, (int) $store->erp_site_code)
            ->pluck('codice_xx32')
            ->map(fn ($value) => trim((string) $value))
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
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
    }

    private function applyCustomerGroupVisibility(Builder $query, Collection $storeGroupCodes): void
    {
        $query->where(function (Builder $query) use ($storeGroupCodes) {
            $query
                ->whereExists(function ($sub) use ($storeGroupCodes) {
                    $sub->selectRaw('1')
                        ->from('customer_visible_groups as cvg')
                        ->whereColumn('cvg.ditta_cg18', 'customers.ditta_cg18')
                        ->whereColumn('cvg.tipocf_cg44', 'customers.tipocf_cg44')
                        ->whereColumn('cvg.clifor_cg44', 'customers.clifor_cg44')
                        ->where('cvg.is_active', 1)
                        ->whereIn('cvg.codice_xx32', $storeGroupCodes->all());
                })
                ->orWhereNotExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('customer_visible_groups as cvg_any')
                        ->whereColumn('cvg_any.ditta_cg18', 'customers.ditta_cg18')
                        ->whereColumn('cvg_any.tipocf_cg44', 'customers.tipocf_cg44')
                        ->whereColumn('cvg_any.clifor_cg44', 'customers.clifor_cg44')
                        ->where('cvg_any.is_active', 1);
                });
        });
    }

    private function applyExcludedCustomers(Builder $query, string $table = 'customers'): void
    {
        $excludedCustomers = $this->excludedCustomers();

        if ($excludedCustomers->isEmpty()) {
            return;
        }

        $query->where(function (Builder $query) use ($excludedCustomers, $table) {
            foreach ($excludedCustomers as $customer) {
                $query->where(function (Builder $query) use ($customer, $table) {
                    $query
                        ->where($table . '.ditta_cg18', '!=', $customer['ditta_cg18'])
                        ->orWhere($table . '.tipocf_cg44', '!=', $customer['tipocf_cg44'])
                        ->orWhere($table . '.clifor_cg44', '!=', $customer['clifor_cg44']);
                });
            }
        });
    }

    private function excludedCustomers(): Collection
    {
        return collect(config('storefront.store_locator.excluded_customers', []))
            ->map(fn (array $customer) => [
                'ditta_cg18' => (int) ($customer['ditta_cg18'] ?? 0),
                'tipocf_cg44' => (int) ($customer['tipocf_cg44'] ?? 0),
                'clifor_cg44' => (int) ($customer['clifor_cg44'] ?? 0),
            ])
            ->filter(fn (array $customer) => $customer['ditta_cg18'] > 0 && $customer['clifor_cg44'] > 0)
            ->values();
    }

    private function mainAddressParts(Customer $customer): array
    {
        $hasCorrespondenceAddress = filled($customer->indircor_cg16)
            || filled($customer->capcor_cg16)
            || filled($customer->cittacor_cg16)
            || filled($customer->provcor_cg16);

        return [
            'address' => $hasCorrespondenceAddress ? $customer->indircor_cg16 : $customer->indirizzo_cg16,
            'postcode' => $hasCorrespondenceAddress ? $customer->capcor_cg16 : $customer->cap_cg16,
            'city' => $hasCorrespondenceAddress ? $customer->cittacor_cg16 : $customer->citta_cg16,
            'province' => $hasCorrespondenceAddress ? $customer->provcor_cg16 : $customer->prov_cg16,
            'country' => null,
        ];
    }

    private function addressLine(array $parts): string
    {
        return trim(collect([
            $parts['address'] ?? null,
            collect([$parts['postcode'] ?? null, $parts['city'] ?? null, $parts['province'] ?? null])
                ->filter(fn ($value) => filled($value))
                ->implode(' '),
        ])->filter(fn ($value) => filled($value))->implode(', '));
    }

    private function addressFingerprint(array $parts): string
    {
        return hash('sha256', collect([
            $parts['address'] ?? '',
            $parts['postcode'] ?? '',
            $parts['city'] ?? '',
            $parts['province'] ?? '',
            $parts['country'] ?? '',
        ])->map(fn ($value) => mb_strtolower(trim((string) $value)))->implode('|'));
    }
}
