<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Erp\DocumentHeader;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerDocumentsController extends Controller
{
    private function initErpSession(): void
    {
        DB::connection('erp')->unprepared('SET ANSI_NULLS ON; SET ANSI_WARNINGS ON;');
    }

    private function isAgentMode(Request $request): bool
    {
        return (bool) $request->session()->get('agent_mode', false);
    }

    private function isAgentImpersonating(Request $request): bool
    {
        return (bool) $request->session()->get('agent_impersonating', false);
    }

    public function index(Request $request)
    {
        $customer = auth('customer')->user();

        abort_unless($customer, 403);

        if ($this->isAgentMode($request) && !$this->isAgentImpersonating($request)) {
            return redirect()->route('storefront.agent.customers');
        }

        $store = app('currentStore');
        $themeResolver = app(ThemeResolver::class);

        $this->initErpSession();

        $ditta = (int) $customer->ditta_cg18;
        $clifor = (int) $customer->clifor_cg44;

        $filters = [
            'document_number' => trim((string) $request->input('document_number', '')),
            'document_type' => trim((string) $request->input('document_type', '')),
            'date_from' => trim((string) $request->input('date_from', '')),
            'date_to' => trim((string) $request->input('date_to', '')),
        ];

        $documentTypes = DocumentHeader::documentTypesForCustomer($ditta, $clifor);

        $documents = DocumentHeader::query()
            ->forCustomer($ditta, $clifor)
            ->applyDocumentFilters($filters)
            ->orderByDesc('DATADOC_DO11')
            ->simplePaginate(25)
            ->withQueryString();

        return view('storefront.base.pages.account.documents.index', [
            'store' => $store,
            'storefrontLayout' => $themeResolver->layout($store),
            'customer' => $customer,
            'documents' => $documents,
            'filters' => $filters,
            'documentTypes' => $documentTypes,
        ]);
    }

    public function show(Request $request, string $document)
    {
        $customer = auth('customer')->user();

        abort_unless($customer, 403);

        if ($this->isAgentMode($request) && !$this->isAgentImpersonating($request)) {
            return redirect()->route('storefront.agent.customers');
        }

        $store = app('currentStore');
        $themeResolver = app(ThemeResolver::class);

        $this->initErpSession();

        $document = DocumentHeader::query()
            ->forCustomer((int) $customer->ditta_cg18, (int) $customer->clifor_cg44)
            ->with('rows')
            ->where('NUMREG_CO99', $document)
            ->firstOrFail();

        return view('storefront.base.pages.account.documents.show', [
            'store' => $store,
            'storefrontLayout' => $themeResolver->layout($store),
            'customer' => $customer,
            'document' => $document,
        ]);
    }
}