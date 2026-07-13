<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Erp\DocumentHeader;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerDocumentsController extends Controller
{
    private function initErpSession(): void
    {
        DB::connection('erp')
            ->unprepared('SET ANSI_NULLS ON; SET ANSI_WARNINGS ON;');
    }

    private function isAgentMode(Request $request): bool
    {
        return (bool) $request->session()->get('agent_mode', false);
    }

    private function resolveCustomer(Request $request): ?Customer
    {
        $store = current_store();
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

        return $authCustomer instanceof Customer
            ? $authCustomer
            : null;
    }

    public function index(Request $request)
    {
        $store = current_store();
        $customer = $this->resolveCustomer($request);

        abort_unless($customer instanceof Customer, 403);

        if ($this->isAgentMode($request) && !$request->filled('agent_context')) {
            return redirect()->route('storefront.agent.customers');
        }

        $this->initErpSession();

        $themeResolver = app(ThemeResolver::class);

        $ditta = (int) $customer->ditta_cg18;
        $clifor = (int) $customer->clifor_cg44;

        $filters = [
            'document_number' => trim((string) $request->input('document_number', '')),
            'document_type' => trim((string) $request->input('document_type', '')),
            'date_from' => trim((string) $request->input('date_from', '')),
            'date_to' => trim((string) $request->input('date_to', '')),
        ];

        $documentTypes = DocumentHeader::defaultDocumentTypes(
            $filters['document_type']
        );

        $sort = $request->input('sort') === 'number'
            ? 'number'
            : 'date';

        $direction = $request->input('dir') === 'asc'
            ? 'asc'
            : 'desc';

        $documents = DocumentHeader::query()
            ->select(DocumentHeader::INDEX_COLUMNS)
            ->forCustomer($ditta, $clifor)
            ->visibleDocumentTypes($filters['document_type'])
            ->applyDocumentFilters($filters)
            ->when(
                $sort === 'date',
                fn ($query) => $query
                    ->orderByDocumentDate($direction)
                    ->orderBy('NUMREG_CO99', $direction),
                fn ($query) => $query
                    ->orderBy('NUMREG_CO99', $direction)
            )
            ->simplePaginate(25)
            ->appends($request->query());

        return view('storefront.base.pages.account.documents.index', [
            'store' => $store,
            'storefrontLayout' => $themeResolver->layout($store),
            'customer' => $customer,
            'documents' => $documents,
            'filters' => $filters,
            'documentTypes' => $documentTypes,
            'sort' => $sort,
            'direction' => $direction,
            'agentContext' => (string) $request->query('agent_context', ''),
        ]);
    }

    public function show(Request $request, string $document)
    {
        $store = current_store();
        $customer = $this->resolveCustomer($request);

        abort_unless($customer instanceof Customer, 403);

        if ($this->isAgentMode($request) && !$request->filled('agent_context')) {
            return redirect()->route('storefront.agent.customers');
        }

        $this->initErpSession();

        $themeResolver = app(ThemeResolver::class);

        $documentHeader = DocumentHeader::query()
            ->forCustomer(
                (int) $customer->ditta_cg18,
                (int) $customer->clifor_cg44
            )
            ->visibleDocumentTypes()
            ->with('rows')
            ->where('NUMREG_CO99', $document)
            ->firstOrFail();

        return view('storefront.base.pages.account.documents.show', [
            'store' => $store,
            'storefrontLayout' => $themeResolver->layout($store),
            'customer' => $customer,
            'document' => $documentHeader,
            'agentContext' => (string) $request->query('agent_context', ''),
        ]);
    }
}