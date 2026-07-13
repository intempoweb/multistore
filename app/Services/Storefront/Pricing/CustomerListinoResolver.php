<?php

namespace App\Services\Storefront\Pricing;

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerListinoResolver
{
    /**
     * Fallback commerciale quando il cliente B2B non ha nessun listino agganciato.
     *
     * Chiave: ditta_cg18:erp_site_code
     */
    private const DEFAULT_LISTINO_BY_DITTA_SITE = [
        '1:1' => 1,
        '3:1' => 1,
    ];

    /**
     * Risolve il listino effettivo cliente senza creare assegnazioni artificiali.
     */
    public function resolveForCustomer(Store $store, Customer $customer): ?int
    {
        $ditta = (int) ($store->ditta_cg18 ?? $customer->ditta_cg18 ?? 0);
        $clifor = (int) ($customer->clifor_cg44 ?? 0);

        if ($ditta <= 0 || $clifor <= 0) {
            return null;
        }

        $assignmentListinoId = DB::table('customer_listino_assignments')
            ->where('ditta_cg18', $ditta)
            ->where('clifor_cg44', $clifor)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->value('listino_id');

        if ($assignmentListinoId !== null && (int) $assignmentListinoId > 0) {
            return (int) $assignmentListinoId;
        }

        $storeDefaultListinoId = $this->defaultListinoForStore($store);

        if ($storeDefaultListinoId !== null) {
            return $storeDefaultListinoId;
        }

        $customerDefaultListinoId = (int) ($customer->codlistinoded ?? 0);

        return $customerDefaultListinoId > 0 ? $customerDefaultListinoId : null;
    }

    public function defaultListinoForStore(?Store $store): ?int
    {
        if (!$store instanceof Store || $store->isB2C()) {
            return null;
        }

        $key = $this->dittaSiteKey(
            (int) ($store->ditta_cg18 ?? 0),
            (int) ($store->erp_site_code ?? 0)
        );

        return self::DEFAULT_LISTINO_BY_DITTA_SITE[$key] ?? null;
    }

    public function defaultListinoForStores(Collection $stores, int $ditta): ?int
    {
        if ($ditta <= 0 || $stores->isEmpty()) {
            return null;
        }

        foreach ($stores as $store) {
            if (!$store instanceof Store) {
                continue;
            }

            if ((int) ($store->ditta_cg18 ?? 0) !== $ditta) {
                continue;
            }

            $listinoId = $this->defaultListinoForStore($store);

            if ($listinoId !== null) {
                return $listinoId;
            }
        }

        return null;
    }

    public function defaultListinoForDitta(int $ditta): ?int
    {
        if ($ditta <= 0) {
            return null;
        }

        $store = Store::query()
            ->where('ditta_cg18', $ditta)
            ->where('erp_site_code', 1)
            ->where('is_b2b', true)
            ->first();

        return $this->defaultListinoForStore($store);
    }

    private function dittaSiteKey(int $ditta, int $siteCode): string
    {
        return $ditta . ':' . $siteCode;
    }
}
