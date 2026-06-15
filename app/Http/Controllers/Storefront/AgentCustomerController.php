<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Store;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AgentCustomerController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
    ) {
    }

    public function index(Request $request): View
    {
        $store = app('currentStore');
        $agent = Auth::guard('customer')->user();

        abort_unless($agent instanceof Customer, 403);
        abort_unless($this->isAgent($agent), 403);

        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->active()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('agente_mg17', $agent->agente_mg17)
            ->where('id', '<>', $agent->id)
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

                $query->where(function ($customerQuery) use ($like) {
                    $customerQuery
                        ->where('ragsoanag_cg16', 'like', $like)
                        ->orWhere('indemail_cg16', 'like', $like)
                        ->orWhere('clifor_cg44', 'like', $like)
                        ->orWhere('codice_cg16', 'like', $like);
                });
            })
            ->orderBy('ragsoanag_cg16')
            ->paginate(24)
            ->withQueryString();

        return view($this->themeResolver->view('agent.customers', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'agent' => $agent,
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function impersonate(Request $request, Customer $customer): RedirectResponse
    {
        $store = app('currentStore');
        $agent = Auth::guard('customer')->user();

        abort_unless($agent instanceof Customer, 403);
        abort_unless($this->isAgent($agent), 403);

        abort_unless((int) $customer->ditta_cg18 === (int) $store->ditta_cg18, 403);
        abort_unless((string) $customer->agente_mg17 === (string) $agent->agente_mg17, 403);

        $request->session()->put('agent_customer_id', $agent->id);
        $request->session()->put('agent_customer_name', $agent->ragsoanag_cg16 ?: $agent->indemail_cg16);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('storefront.home')
            ->with('status', 'Stai operando per conto del cliente ' . ($customer->ragsoanag_cg16 ?: $customer->clifor_cg44) . '.');
    }

    public function stop(Request $request): RedirectResponse
    {
        $agentId = (int) $request->session()->get('agent_customer_id');

        abort_unless($agentId > 0, 403);

        $agent = Customer::query()->findOrFail($agentId);

        Auth::guard('customer')->login($agent);
        $request->session()->forget(['agent_customer_id', 'agent_customer_name']);
        $request->session()->regenerate();

        return redirect()->route('storefront.agent.customers')
            ->with('status', 'Sei tornato al profilo agente.');
    }

    private function isAgent(Customer $customer): bool
    {
        return trim((string) $customer->agente_mg17) !== ''
            && trim((string) $customer->indeemail_vwebdcg44) !== '';
    }
}