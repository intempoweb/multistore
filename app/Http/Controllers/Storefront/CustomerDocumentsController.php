<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerSupportTicket;
use App\Models\Erp\DocumentHeader;
use App\Services\Storefront\Documents\DocumentFulfillmentResolver;
use App\Services\Storefront\Documents\DocumentGoodsDestinationResolver;
use App\Services\Storefront\Documents\DocumentProductResolver;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerDocumentsController extends Controller
{
    private function initErpSession(): void
    {
        DB::connection('erp')
            ->unprepared(
                'SET ANSI_NULLS ON; SET ANSI_WARNINGS ON;'
            );
    }

    private function isAgentMode(Request $request): bool
    {
        return (bool) $request->session()->get(
            'agent_mode',
            false
        );
    }

    private function resolveCustomer(Request $request): ?Customer
    {
        $store = current_store();

        $contextId = (string) $request->query(
            'agent_context',
            ''
        );

        if (
            $contextId !== ''
            && $this->isAgentMode($request)
        ) {
            $context = $request->session()->get(
                "agent_contexts.$contextId"
            );

            if (
                is_array($context)
                && !empty($context['customer_id'])
            ) {
                $contextCustomer = Customer::query()
                    ->active()
                    ->webEnabled()
                    ->where(
                        'id',
                        (int) $context['customer_id']
                    )
                    ->where(
                        'ditta_cg18',
                        (int) $store->ditta_cg18
                    )
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

    /**
     * Valorizza sul documento gli attributi CUSTOMER_* utilizzati
     * dalla view e dai metodi *ForDisplay().
     *
     * I dati vengono letti dal Customer locale, già sincronizzato
     * dall'ERP, evitando la query alla lenta vista ANAGRCLI_TOT.
     */
    private function attachLocalCustomerDetails(
        DocumentHeader $document,
        Customer $customer
    ): void {
        $document->setAttribute(
            'CUSTOMER_RAGSOANAG',
            $customer->ragsoanag_cg16
        );

        $document->setAttribute(
            'CUSTOMER_INDIRIZZO',
            $customer->indirizzo_cg16
        );

        $document->setAttribute(
            'CUSTOMER_CAP',
            $customer->cap_cg16
        );

        $document->setAttribute(
            'CUSTOMER_CITTA',
            $customer->citta_cg16
        );

        $document->setAttribute(
            'CUSTOMER_PROV',
            $customer->prov_cg16
        );

        $document->setAttribute(
            'CUSTOMER_PARTIVA',
            $customer->partiva_cg16
        );

        $document->setAttribute(
            'CUSTOMER_CODFISCALE',
            $customer->codfiscale_cg16
        );

        $document->setAttribute(
            'CUSTOMER_EMAIL',
            $customer->indemail_cg16
        );

        $document->setAttribute(
            'CUSTOMER_TELEFONO',
            $customer->tel1num_cg16
        );
    }

    public function index(Request $request)
    {
        $store = current_store();
        $customer = $this->resolveCustomer($request);

        abort_unless(
            $customer instanceof Customer,
            403
        );

        if (
            $this->isAgentMode($request)
            && !$request->filled('agent_context')
        ) {
            return redirect()->route(
                'storefront.agent.customers'
            );
        }

        $this->initErpSession();

        $themeResolver = app(ThemeResolver::class);

        $ditta = (int) $customer->ditta_cg18;
        $clifor = (int) $customer->clifor_cg44;

        $filters = [
            'document_number' => trim(
                (string) $request->input(
                    'document_number',
                    ''
                )
            ),
            'document_type' => trim(
                (string) $request->input(
                    'document_type',
                    ''
                )
            ),
            'date_from' => trim(
                (string) $request->input(
                    'date_from',
                    ''
                )
            ),
            'date_to' => trim(
                (string) $request->input(
                    'date_to',
                    ''
                )
            ),
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
            ->withOrderProvenance()
            ->select(DocumentHeader::INDEX_COLUMNS)
            ->forCustomer($ditta, $clifor)
            ->visibleDocumentTypes(
                $filters['document_type']
            )
            ->applyDocumentFilters($filters)
            ->when(
                $sort === 'date',
                fn ($query) => $query
                    ->orderByDocumentDate($direction)
                    ->orderByDocumentNumber($direction),
                fn ($query) => $query
                    ->orderByDocumentNumber($direction)
            )
            ->simplePaginate(25)
            ->appends($request->query());

        DocumentHeader::preloadDdtOrderFallbackDetails(
            $documents->getCollection()
        );

        return view(
            'storefront.base.pages.account.documents.index',
            [
                'store' => $store,
                'storefrontLayout' => $themeResolver->layout(
                    $store
                ),
                'customer' => $customer,
                'documents' => $documents,
                'filters' => $filters,
                'documentTypes' => $documentTypes,
                'sort' => $sort,
                'direction' => $direction,
                'agentContext' => (string) $request->query(
                    'agent_context',
                    ''
                ),
            ]
        );
    }

    public function show(
        Request $request,
        string $document
    ) {
        $store = current_store();
        $customer = $this->resolveCustomer($request);

        abort_unless(
            $customer instanceof Customer,
            403
        );

        if (
            $this->isAgentMode($request)
            && !$request->filled('agent_context')
        ) {
            return redirect()->route(
                'storefront.agent.customers'
            );
        }

        $this->initErpSession();

        $themeResolver = app(ThemeResolver::class);

        $documentHeader = DocumentHeader::query()
            ->withDocumentDetails()
            ->forCustomer(
                (int) $customer->ditta_cg18,
                (int) $customer->clifor_cg44
            )
            ->visibleDocumentTypes()
            ->where(
                'DOCTESTATABASE_DO11.NUMREG_CO99',
                $document
            )
            ->with('rows')
            ->firstOrFail();

        $this->attachLocalCustomerDetails(
            $documentHeader,
            $customer
        );

        app(DocumentGoodsDestinationResolver::class)->attach(
            $documentHeader
        );

        app(DocumentProductResolver::class)->attachProducts(
            $documentHeader,
            $store
        );

        app(DocumentFulfillmentResolver::class)->attach(
            $documentHeader
        );

        $documentReturns = CustomerReturn::query()
            ->where('customer_id', (int) $customer->id)
            ->where('store_id', (int) $store->id)
            ->where('ditta_cg18', (int) $customer->ditta_cg18)
            ->where('clifor_cg44', (int) $customer->clifor_cg44)
            ->where(
                'numreg_co99',
                (string) $documentHeader->NUMREG_CO99
            )
            ->withCount([
                'items',
                'attachments',
            ])
            ->latest()
            ->get();

        $supportTickets = CustomerSupportTicket::query()
            ->where('customer_id', (int) $customer->id)
            ->where('store_id', (int) $store->id)
            ->where('ditta_cg18', (int) $customer->ditta_cg18)
            ->where('clifor_cg44', (int) $customer->clifor_cg44)
            ->where(
                'numreg_co99',
                (string) $documentHeader->NUMREG_CO99
            )
            ->withCount([
                'items',
                'attachments',
            ])
            ->latest()
            ->get();

        return view(
            'storefront.base.pages.account.documents.show',
            [
                'store' => $store,
                'storefrontLayout' => $themeResolver->layout(
                    $store
                ),
                'customer' => $customer,
                'document' => $documentHeader,
                'documentReturns' => $documentReturns,
                'supportTickets' => $supportTickets,
                'agentContext' => (string) $request->query(
                    'agent_context',
                    ''
                ),
            ]
        );
    }
}