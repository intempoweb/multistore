<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AgentCustomerController extends Controller
{
    public function __construct(
        private ThemeResolver $themeResolver,
    ) {
    }

    public function index(Request $request): RedirectResponse|View
    {
        $store = app('currentStore');
        $agent = Auth::guard('customer')->user();
        abort_unless($agent instanceof Customer, 403);
        abort_unless($this->isAgentMode($request), 403);

        $agentEmail = $this->agentEmail($request, $agent);
        $agentCode = $this->agentCode($request, $agent);
        $agentName = $this->agentName($request, $agent);

        abort_unless($this->isAgentContext($agentEmail, $agentCode), 403);

        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where(function ($query) use ($agentEmail, $agentCode) {
                if ($agentEmail !== '') {
                    $query->whereRaw('LOWER(indeemail_vwebdcg44) = ?', [$agentEmail]);

                    return;
                }

                if ($agentCode !== '') {
                    $query->where('agente_mg17', $agentCode);
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

                $query->where(function ($customerQuery) use ($like) {
                    $customerQuery
                        ->where('ragsoanag_cg16', 'like', $like)
                        ->orWhere('indemail_cg16', 'like', $like)
                        ->orWhere('clifor_cg44', 'like', $like)
                        ->orWhere('codice_cg16', 'like', $like)
                        ->orWhere('partiva_cg16', 'like', $like)
                        ->orWhere('codfiscale_cg16', 'like', $like);
                });
            })
            ->orderByDesc('is_active')
            ->orderByRaw("CASE WHEN codrifalf_mg19 = 'PT' THEN 0 ELSE 1 END")
            ->orderBy('ragsoanag_cg16')
            ->paginate(24)
            ->withQueryString();

        return view($this->themeResolver->view('agent.customers', $store), [
            'store' => $store,
            'storefrontLayout' => $this->themeResolver->layout($store),
            'agent' => $agent,
            'agentEmail' => $agentEmail,
            'agentCode' => $agentCode,
            'agentName' => $agentName,
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function openCustomer(Request $request, Customer $customer): RedirectResponse
    {
        $store = app('currentStore');
        $agent = Auth::guard('customer')->user();

        abort_unless($agent instanceof Customer, 403);
        abort_unless($this->isAgentMode($request), 403);

        $agentEmail = $this->agentEmail($request, $agent);
        $agentCode = $this->agentCode($request, $agent);
        $agentName = $this->agentName($request, $agent);

        abort_unless($this->isAgentContext($agentEmail, $agentCode), 403);
        abort_unless((int) $customer->ditta_cg18 === (int) $store->ditta_cg18, 403);
        abort_unless($this->customerBelongsToAgent($customer, $agentEmail, $agentCode), 403);
        abort_unless((bool) $customer->is_active, 403);
        abort_unless(strtoupper(trim((string) $customer->codrifalf_mg19)) === 'PT', 403);

        $contextId = (string) Str::uuid();

        $request->session()->put("agent_contexts.$contextId", [
            'customer_id' => $customer->id,
            'customer_name' => $customer->ragsoanag_cg16 ?: $customer->indemail_cg16 ?: $customer->clifor_cg44,
            'agent_email' => $agentEmail,
            'agent_code' => $agentCode,
            'agent_name' => $agentName,
            'created_at' => now()->toDateTimeString(),
        ]);

        return redirect()
            ->route('storefront.home', ['agent_context' => $contextId])
            ->with('status', 'Stai operando per conto del cliente ' . ($customer->ragsoanag_cg16 ?: $customer->clifor_cg44) . '.');
    }

    public function clearContext(Request $request): RedirectResponse
    {
        $contextId = (string) $request->query('agent_context', '');

        if ($contextId !== '') {
            $request->session()->forget("agent_contexts.$contextId");
        }

        return redirect()
            ->route('storefront.agent.customers')
            ->with('status', 'Sei tornato al profilo agente.');
    }

    private function agentEmail(Request $request, Customer $agent): string
    {
        $sessionEmail = Str::lower(trim((string) $request->session()->get('agent_login_email', '')));

        if ($sessionEmail !== '') {
            return $sessionEmail;
        }

        return Str::lower(trim((string) $agent->indeemail_vwebdcg44));
    }

    private function agentCode(Request $request, Customer $agent): string
    {
        $sessionCode = trim((string) $request->session()->get('agent_code', ''));

        if ($sessionCode !== '') {
            return $sessionCode;
        }

        return trim((string) $agent->agente_mg17);
    }

    private function agentName(Request $request, Customer $agent): string
    {
        $sessionName = trim((string) $request->session()->get('agent_name', ''));

        if ($sessionName !== '') {
            return $sessionName;
        }

        return trim((string) $agent->ragsoanag_vwebdcg44);
    }

    private function isAgentMode(Request $request): bool
    {
        return (bool) $request->session()->get('agent_mode', false);
    }

    private function isAgentContext(string $agentEmail, string $agentCode): bool
    {
        return $agentEmail !== '' || $agentCode !== '';
    }

    private function customerBelongsToAgent(Customer $customer, string $agentEmail, string $agentCode): bool
    {
        $customerAgentEmail = Str::lower(trim((string) $customer->indeemail_vwebdcg44));
        $customerAgentCode = trim((string) $customer->agente_mg17);

        if ($agentEmail !== '') {
            return $customerAgentEmail === $agentEmail;
        }

        return $agentCode !== '' && $customerAgentCode === $agentCode;
    }
}