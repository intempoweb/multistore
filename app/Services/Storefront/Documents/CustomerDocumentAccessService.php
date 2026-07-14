<?php

namespace App\Services\Storefront\Documents;

use App\Models\Customer;
use App\Models\Erp\DocumentHeader;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerDocumentAccessService
{
    public function resolveCustomer(Request $request, Store $store): ?Customer
    {
        $contextId = (string) $request->query('agent_context', '');

        if ($contextId !== '' && $this->isAgentMode($request)) {
            $context = $request->session()->get("agent_contexts.$contextId");

            if (is_array($context) && !empty($context['customer_id'])) {
                $contextCustomer = Customer::query()
                    ->active()
                    ->webEnabled()
                    ->where('id', (int) $context['customer_id'])
                    ->where('ditta_cg18', (int) $store->ditta_cg18)
                    ->first();

                if ($contextCustomer instanceof Customer) {
                    return $contextCustomer;
                }
            }
        }

        $authCustomer = auth('customer')->user();

        return $authCustomer instanceof Customer ? $authCustomer : null;
    }

    public function resolveDocument(Request $request, Store $store, string $document): DocumentHeader
    {
        abort_if($store->isB2C(), 404);

        $customer = $this->resolveCustomer($request, $store);

        abort_unless($customer instanceof Customer, 403);

        if ($this->isAgentMode($request) && !$request->filled('agent_context')) {
            abort(403);
        }

        $this->initErpSession();

        return DocumentHeader::query()
            ->forCustomer(
                (int) $customer->ditta_cg18,
                (int) $customer->clifor_cg44
            )
            ->visibleDocumentTypes()
            ->where('DOCTESTATABASE_DO11.DITTA_CG18', (int) $store->ditta_cg18)
            ->where('DOCTESTATABASE_DO11.NUMREG_CO99', $document)
            ->with('rows')
            ->firstOrFail();
    }

    public function isAgentMode(Request $request): bool
    {
        return (bool) $request->session()->get('agent_mode', false);
    }

    private function initErpSession(): void
    {
        DB::connection('erp')
            ->unprepared('SET ANSI_NULLS ON; SET ANSI_WARNINGS ON;');
    }
}
