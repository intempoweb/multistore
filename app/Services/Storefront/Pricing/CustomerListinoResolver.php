<?php

namespace App\Services\Storefront\Pricing;

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerListinoResolver
{
    /**
     * Listino base LISTARTIC_TOT per ditta e sito ERP.
     *
     * Usato:
     * - quando il cliente non ha un listino commerciale;
     * - come fallback prodotto quando lo SKU non esiste nel listino cliente.
     *
     * Chiave: ditta_cg18:erp_site_code
     */
    private const DEFAULT_LISTINO_BY_DITTA_SITE = [
        '1:1' => 31,
        '3:1' => 1,
    ];

    /**
     * Risolve il listino commerciale del cliente.
     *
     * Priorità:
     * 1. assegnazione esplicita locale;
     * 2. listino ERP presente su customers.codlistinoded;
     * 3. listino base configurato per ditta e sito.
     */
    public function resolveForCustomer(
        Store $store,
        Customer $customer
    ): ?int {
        $ditta = (int) (
            $store->ditta_cg18
            ?? $customer->ditta_cg18
            ?? 0
        );

        $clifor = (int) (
            $customer->clifor_cg44
            ?? 0
        );

        if ($ditta <= 0 || $clifor <= 0) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | 1. Assegnazione esplicita
        |--------------------------------------------------------------------------
        */
        $assignmentListinoId = DB::table('customer_listino_assignments')
            ->where('ditta_cg18', $ditta)
            ->where('clifor_cg44', $clifor)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->value('listino_id');

        $assignmentListinoId = (int) ($assignmentListinoId ?? 0);

        if ($assignmentListinoId > 0) {
            return $assignmentListinoId;
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Listino ERP del cliente
        |--------------------------------------------------------------------------
        */
        $customerListinoId = (int) (
            $customer->codlistinoded
            ?? 0
        );

        if ($customerListinoId > 0) {
            return $customerListinoId;
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Listino base LISTARTIC_TOT dello store
        |--------------------------------------------------------------------------
        */
        return $this->defaultListinoForStore($store);
    }

    /**
     * Restituisce il listino base LISTARTIC_TOT dello store.
     */
    public function defaultListinoForStore(
        ?Store $store
    ): ?int {
        if (
            !$store instanceof Store
            || $store->isB2C()
        ) {
            return null;
        }

        $ditta = (int) (
            $store->ditta_cg18
            ?? 0
        );

        $siteCode = (int) (
            $store->erp_site_code
            ?? 0
        );

        if ($ditta <= 0 || $siteCode <= 0) {
            return null;
        }

        $key = $this->dittaSiteKey(
            $ditta,
            $siteCode
        );

        $listinoId = (int) (
            self::DEFAULT_LISTINO_BY_DITTA_SITE[$key]
            ?? 0
        );

        return $listinoId > 0
            ? $listinoId
            : null;
    }

    /**
     * Cerca il listino base per una ditta all'interno di una collezione di store.
     */
    public function defaultListinoForStores(
        Collection $stores,
        int $ditta
    ): ?int {
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

            $listinoId = $this->defaultListinoForStore(
                $store
            );

            if ($listinoId !== null) {
                return $listinoId;
            }
        }

        return null;
    }

    /**
     * Cerca lo store B2B del sito ERP 1 per la ditta indicata
     * e ne restituisce il listino base LISTARTIC_TOT.
     */
    public function defaultListinoForDitta(
        int $ditta
    ): ?int {
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

    private function dittaSiteKey(
        int $ditta,
        int $siteCode
    ): string {
        return $ditta . ':' . $siteCode;
    }
}