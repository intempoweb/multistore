<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerImpersonationToken;
use App\Models\CustomerListinoAssignment;
use App\Models\CustomerShippingAddress;
use App\Models\Store;
use App\Services\Storefront\Pricing\CustomerListinoResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        /** @var Store $store */
        $store = $this->currentStore();

        $q = Customer::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->orderBy('ragsoanag_cg16')
            ->orderBy('clifor_cg44');

        if ($request->filled('tipocf')) {
            $q->where('tipocf_cg44', (int) $request->input('tipocf'));
        }

        if ($request->filled('clifor')) {
            $q->where('clifor_cg44', (int) $request->input('clifor'));
        }

        if ($request->filled('codice')) {
            $q->where('codice_cg16', (int) $request->input('codice'));
        }

        if ($request->filled('listino')) {
            $listinoId = (int) $request->input('listino');
            $fallbackListinoId = $this->listinoResolver()->defaultListinoForStore($store);

            $q->where(function ($sub) use ($listinoId, $fallbackListinoId) {
                $sub->whereExists(function ($assignmentSub) use ($listinoId) {
                    $assignmentSub->selectRaw('1')
                        ->from('customer_listino_assignments as cla')
                        ->whereColumn('cla.ditta_cg18', 'customers.ditta_cg18')
                        ->whereColumn('cla.clifor_cg44', 'customers.clifor_cg44')
                        ->where('cla.listino_id', $listinoId)
                        ->where('cla.is_active', true);
                })
                ->orWhere(function ($defaultSub) use ($listinoId) {
                    $defaultSub->where('customers.codlistinoded', $listinoId)
                        ->whereNotExists(function ($assignmentSub) {
                            $assignmentSub->selectRaw('1')
                                ->from('customer_listino_assignments as cla')
                                ->whereColumn('cla.ditta_cg18', 'customers.ditta_cg18')
                                ->whereColumn('cla.clifor_cg44', 'customers.clifor_cg44')
                                ->where('cla.is_active', true);
                        });
                });

                if ($fallbackListinoId !== null && $fallbackListinoId === $listinoId) {
                    $sub->orWhere(function ($fallbackSub) {
                        $fallbackSub->whereNotExists(function ($assignmentSub) {
                            $assignmentSub->selectRaw('1')
                                ->from('customer_listino_assignments as cla')
                                ->whereColumn('cla.ditta_cg18', 'customers.ditta_cg18')
                                ->whereColumn('cla.clifor_cg44', 'customers.clifor_cg44')
                                ->where('cla.is_active', true);
                        });
                    });
                }
            });
        }

        if ($request->filled('email')) {
            $email = trim((string) $request->input('email'));

            $q->where(function ($sub) use ($email) {
                $sub->where('indemail_cg16', 'like', "%{$email}%")
                    ->orWhere('email_pec_cg16', 'like', "%{$email}%")
                    ->orWhere('indeemail_vwebdcg44', 'like', "%{$email}%");
            });
        }

        if ($request->filled('vat')) {
            $vat = trim((string) $request->input('vat'));

            $q->where(function ($sub) use ($vat) {
                $sub->where('partiva_cg16', 'like', "%{$vat}%")
                    ->orWhere('codfiscale_cg16', 'like', "%{$vat}%");
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $q->where(function ($sub) use ($search) {
                $sub->where('ragsoanag_cg16', 'like', "%{$search}%")
                    ->orWhere('ragsocor_cg16', 'like', "%{$search}%")
                    ->orWhere('clifor_cg44', 'like', "%{$search}%")
                    ->orWhere('codice_cg16', 'like', "%{$search}%")
                    ->orWhere('partiva_cg16', 'like', "%{$search}%")
                    ->orWhere('codfiscale_cg16', 'like', "%{$search}%")
                    ->orWhere('indemail_cg16', 'like', "%{$search}%");
            });
        }

        if ($request->filled('web_enabled')) {
            $webEnabled = strtoupper(trim((string) $request->input('web_enabled')));

            if ($webEnabled === 'PT') {
                $q->where('codrifalf_mg19', 'PT');
            } elseif ($webEnabled === 'NO') {
                $q->where(function ($sub) {
                    $sub->whereNull('codrifalf_mg19')
                        ->orWhere('codrifalf_mg19', '<>', 'PT');
                });
            }
        }

        if ($request->filled('active')) {
            $active = filter_var(
                $request->input('active'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($active !== null) {
                $q->where('is_active', $active);
            }
        }

        $customers = $q->paginate(50)->withQueryString();

        $this->enrichCustomers(collect($customers->items()), $store);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $customers->items(),
                'meta' => [
                    'current_page' => $customers->currentPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'last_page' => $customers->lastPage(),
                ],
            ]);
        }

        return view('admin.customers.index', [
            'store' => $store,
            'customers' => $customers,
            'filters' => [
                'tipocf' => (string) $request->input('tipocf', ''),
                'clifor' => (string) $request->input('clifor', ''),
                'codice' => (string) $request->input('codice', ''),
                'listino' => (string) $request->input('listino', ''),
                'email' => (string) $request->input('email', ''),
                'vat' => (string) $request->input('vat', ''),
                'search' => (string) $request->input('search', ''),
                'web_enabled' => (string) $request->input('web_enabled', ''),
                'active' => (string) $request->input('active', ''),
            ],
        ]);
    }

    public function show(Request $request, Customer $customer): View|JsonResponse
    {
        /** @var Store $store */
        $store = $this->currentStore();

        $this->guardStoreContext($customer, $store);

        $this->enrichCustomer($customer, $store);

        $visibleGroups = DB::table('customer_visible_groups')
            ->where('ditta_cg18', (int) $customer->ditta_cg18)
            ->where('tipocf_cg44', (int) $customer->tipocf_cg44)
            ->where('clifor_cg44', (int) $customer->clifor_cg44)
            ->orderBy('codice_xx32')
            ->get()
            ->map(function ($row) {
                $flag = strtoupper(trim((string) ($row->flg_b2b_b2c_webt81 ?? '')));
                $row->site_flag_label = match ($flag) {
                    '1', 'B' => 'B2B',
                    '2', 'C' => 'B2C',
                    default => $flag !== '' ? $flag : '-',
                };

                return $row;
            });

        $storesForDitta = Store::query()
            ->where('ditta_cg18', (int) $customer->ditta_cg18)
            ->orderBy('name')
            ->orderBy('erp_site_code')
            ->get([
                'id',
                'name',
                'domain',
                'erp_site_code',
                'ditta_cg18',
                'is_active',
                'is_b2b',
            ])
            ->map(function (Store $storeRow) {
                $storeRow->setAttribute('store_type_label', $storeRow->channelLabel());
                $storeRow->setAttribute('store_status_label', (bool) $storeRow->is_active ? 'Attivo' : 'Disattivo');

                return $storeRow;
            });

        $listinoAssignments = $this->customerListinoAssignmentsForCustomer($customer);
        $listinoIds = $listinoAssignments
            ->pluck('listino_id')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        $fallbackListinoIds = $listinoIds;

        if (empty($fallbackListinoIds)) {
            $defaultListinoId = (int) ($this->listinoResolver()->defaultListinoForStore($store) ?? 0);

            if ($defaultListinoId <= 0) {
                $defaultListinoId = (int) ($customer->codlistinoded ?? 0);
            }

            if ($defaultListinoId > 0) {
                $fallbackListinoIds = [$defaultListinoId];
            }
        }

        $listinoSummaries = $this->listinoSummariesByListini((int) $customer->ditta_cg18, $fallbackListinoIds);
        $listinoCustomers = collect();

        if (!empty($fallbackListinoIds)) {
            $defaultListinoIdForStore = $this->listinoResolver()->defaultListinoForStore($store);

            $listinoCustomers = Customer::query()
                ->where('ditta_cg18', (int) $customer->ditta_cg18)
                ->where(function ($query) use ($fallbackListinoIds, $defaultListinoIdForStore) {
                    $query->whereExists(function ($sub) use ($fallbackListinoIds) {
                        $sub->selectRaw('1')
                            ->from('customer_listino_assignments as cla')
                            ->whereColumn('cla.ditta_cg18', 'customers.ditta_cg18')
                            ->whereColumn('cla.clifor_cg44', 'customers.clifor_cg44')
                            ->whereIn('cla.listino_id', $fallbackListinoIds)
                            ->where('cla.is_active', true);
                    })
                    ->orWhere(function ($sub) use ($fallbackListinoIds) {
                        $sub->whereIn('customers.codlistinoded', $fallbackListinoIds)
                            ->whereNotExists(function ($assignmentSub) {
                                $assignmentSub->selectRaw('1')
                                    ->from('customer_listino_assignments as cla')
                                    ->whereColumn('cla.ditta_cg18', 'customers.ditta_cg18')
                                    ->whereColumn('cla.clifor_cg44', 'customers.clifor_cg44')
                                    ->where('cla.is_active', true);
                            });
                    });

                    if ($defaultListinoIdForStore !== null && in_array($defaultListinoIdForStore, $fallbackListinoIds, true)) {
                        $query->orWhere(function ($sub) {
                            $sub->whereNotExists(function ($assignmentSub) {
                                $assignmentSub->selectRaw('1')
                                    ->from('customer_listino_assignments as cla')
                                    ->whereColumn('cla.ditta_cg18', 'customers.ditta_cg18')
                                    ->whereColumn('cla.clifor_cg44', 'customers.clifor_cg44')
                                    ->where('cla.is_active', true);
                            });
                        });
                    }
                })
                ->orderBy('ragsoanag_cg16')
                ->orderBy('clifor_cg44')
                ->get();

            $this->enrichCustomers($listinoCustomers, $store);
        }

        $shippingAddresses = CustomerShippingAddress::query()
            ->forCustomer(
                (int) $customer->ditta_cg18,
                (int) $customer->tipocf_cg44,
                (int) $customer->clifor_cg44
            )
            ->active()
            ->orderBy('coddestin_mg22')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'customer' => $customer,
                    'stores' => $storesForDitta,
                    'visible_groups' => $visibleGroups,
                    'customer_listino_assignments' => $listinoAssignments,
                    'listino_summaries' => $listinoSummaries,
                    'listino_customers' => $listinoCustomers,
                    'shipping_addresses' => $shippingAddresses,
                ],
            ]);
        }

        return view('admin.customers.show', [
            'store' => $store,
            'customer' => $customer,
            'storesForDitta' => $storesForDitta,
            'visibleGroups' => $visibleGroups,
            'customerListinoAssignments' => $listinoAssignments,
            'listinoSummaries' => $listinoSummaries,
            'listinoCustomers' => $listinoCustomers,
            'shippingAddresses' => $shippingAddresses,
        ]);
    }

    public function loginAsCustomer(Request $request, Customer $customer): \Illuminate\Http\RedirectResponse
    {
        /** @var Store $store */
        $store = $this->currentStore();

        if ($redirect = $this->redirectIfCannotImpersonate($request, $customer, $store)) {
            return $redirect;
        }

        if (!$customer->canReceiveMagicLink()) {
            return back()->with('error', 'Cliente non abilitato al login web.');
        }

        $targetStore = $store;

        if ($targetStore->isB2C() || blank($targetStore->domain)) {
            return back()->with('error', 'Nessuno store B2B attivo con dominio trovato per questo cliente.');
        }

        $plainToken = Str::random(64);

        CustomerImpersonationToken::query()
            ->where('customer_id', $customer->id)
            ->whereNull('used_at')
            ->delete();

        CustomerImpersonationToken::create([
            'customer_id' => $customer->id,
            'admin_user_id' => (int) Auth::id(),
            'store_id' => $targetStore->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(5),
        ]);

        $targetUrl = rtrim((string) $targetStore->domain, '/');

        if (!preg_match('#^https?://#i', $targetUrl)) {
            $targetUrl = $request->getScheme() . '://' . $targetUrl;
        }

        $targetUrl .= '/impersonate/' . $plainToken;

        return redirect()->away($targetUrl);
    }

    private function currentStore(): Store
    {
        /** @var Store $store */
        $store = admin_store();

        return $store;
    }

    private function guardStoreContext(Customer $customer, Store $store): void
    {
        if ((int) $customer->ditta_cg18 !== (int) $store->ditta_cg18) {
            abort(404);
        }
    }

    private function redirectIfCannotImpersonate(Request $request, Customer $customer, Store $store): ?\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        $allowed = $user
            && method_exists($user, 'canAccessAdminSection')
            && $user->canAccessAdminSection('b2b_impersonation')
            && method_exists($user, 'canAccessAdminStore')
            && $user->canAccessAdminStore($store)
            && $store->isB2B()
            && (int) $customer->ditta_cg18 === (int) $store->ditta_cg18
            && $customer->account_origin !== 'storefront'
            && (int) ($customer->clifor_cg44 ?? 0) > 0;

        if ($allowed) {
            return null;
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('warning', 'Non hai i permessi per accedere come questo cliente.');
    }

    private function enrichCustomers(Collection $customers, Store $currentStore): void
    {
        if ($customers->isEmpty()) {
            return;
        }

        $storesByDitta = $this->storesByDitta($customers);
        $assignmentsByCustomerKey = $this->listinoAssignmentsByCustomers($customers);
        $listinoSummaryByDittaAndListino = $this->listinoSummaryByCustomers($customers, $assignmentsByCustomerKey, $currentStore);
        $shippingAddressesByCustomerKey = $this->shippingAddressesByCustomers($customers);

        foreach ($customers as $customer) {
            if ($customer instanceof Customer) {
                $this->applyCustomerComputedAttributes(
                    $customer,
                    $currentStore,
                    $storesByDitta,
                    $assignmentsByCustomerKey,
                    $listinoSummaryByDittaAndListino,
                    $shippingAddressesByCustomerKey
                );
            }
        }
    }

    private function enrichCustomer(Customer $customer, Store $currentStore): void
    {
        $storesByDitta = $this->storesByDitta(collect([$customer]));
        $assignmentsByCustomerKey = $this->listinoAssignmentsByCustomers(collect([$customer]));
        $listinoSummaryByDittaAndListino = $this->listinoSummaryByCustomers(collect([$customer]), $assignmentsByCustomerKey, $currentStore);
        $shippingAddressesByCustomerKey = $this->shippingAddressesByCustomers(collect([$customer]));

        $this->applyCustomerComputedAttributes(
            $customer,
            $currentStore,
            $storesByDitta,
            $assignmentsByCustomerKey,
            $listinoSummaryByDittaAndListino,
            $shippingAddressesByCustomerKey
        );
    }

    private function applyCustomerComputedAttributes(
        Customer $customer,
        Store $currentStore,
        Collection $storesByDitta,
        Collection $assignmentsByCustomerKey,
        Collection $listinoSummaryByDittaAndListino,
        Collection $shippingAddressesByCustomerKey
    ): void {
        $ditta = (int) $customer->ditta_cg18;
        $tipoCf = (int) $customer->tipocf_cg44;
        $clifor = (int) $customer->clifor_cg44;

        $stores = $storesByDitta->get($ditta, collect());
        $customerKey = $this->customerKey($ditta, $tipoCf, $clifor);
        $customerListinoKey = $this->customerListinoKey($ditta, $clifor);

        $assignments = $assignmentsByCustomerKey->get($customerListinoKey, collect())->values();
        $shippingAddresses = $shippingAddressesByCustomerKey->get($customerKey, collect())->values();

        $assignmentListinoIds = $assignments
            ->pluck('listino_id')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values();

        $defaultListinoId = null;

        if ($assignments->isEmpty()) {
            $defaultListinoId = null;

            if ((int) ($currentStore->ditta_cg18 ?? 0) === $ditta) {
                $defaultListinoId = $this->listinoResolver()->defaultListinoForStore($currentStore);
            }

            if ($defaultListinoId === null) {
                $customerDefaultListinoId = (int) ($customer->codlistinoded ?? 0);
                $defaultListinoId = $customerDefaultListinoId > 0 ? $customerDefaultListinoId : null;
            }
        }

        $effectiveListinoIds = $assignmentListinoIds;

        if ($effectiveListinoIds->isEmpty() && $defaultListinoId) {
            $effectiveListinoIds = collect([$defaultListinoId]);
        }

        $listinoSummaries = $effectiveListinoIds
            ->map(function (int $listinoId) use ($ditta, $listinoSummaryByDittaAndListino) {
                return $listinoSummaryByDittaAndListino->get($this->dittaListinoKey($ditta, $listinoId));
            })
            ->filter()
            ->values();

        $primaryListinoId = $effectiveListinoIds->first();
        $primaryListinoSummary = $primaryListinoId
            ? $listinoSummaryByDittaAndListino->get($this->dittaListinoKey($ditta, $primaryListinoId))
            : null;

        $defaultListinoSummary = $defaultListinoId
            ? $listinoSummaryByDittaAndListino->get($this->dittaListinoKey($ditta, $defaultListinoId))
            : null;

        $customer->setAttribute('store_names', $stores->pluck('name')->values()->all());
        $customer->setAttribute(
            'store_labels',
            $stores->map(fn ($row) => $row->name . ' (' . $row->erp_site_code . ')')->values()->all()
        );
        $customer->setAttribute('store_names_text', $stores->pluck('name')->implode(', '));
        $customer->setAttribute('store_domains', $stores->pluck('domain')->filter()->values()->all());
        $customer->setAttribute(
            'stores_payload',
            $stores->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'domain' => $row->domain,
                    'erp_site_code' => (int) $row->erp_site_code,
                    'is_active' => (bool) $row->is_active,
                    'is_b2b' => (bool) $row->is_b2b,
                    'store_type_label' => $row->channelLabel(),
                    'store_status_label' => (bool) $row->is_active ? 'Attivo' : 'Disattivo',
                ];
            })->values()->all()
        );

        $customer->setAttribute('shipping_addresses', $shippingAddresses->all());
        $customer->setAttribute('shipping_addresses_count', $shippingAddresses->count());
        $customer->setAttribute(
            'shipping_addresses_payload',
            $shippingAddresses->map(function (CustomerShippingAddress $address) {
                return [
                    'id' => (int) $address->id,
                    'coddestin_mg22' => (int) $address->coddestin_mg22,
                    'destragsoc_mg22' => $address->destragsoc_mg22,
                    'destind_mg22' => $address->destind_mg22,
                    'destcap_mg22' => $address->destcap_mg22,
                    'destcitta_mg22' => $address->destcitta_mg22,
                    'destprov_mg22' => $address->destprov_mg22,
                    'desttel_mg22' => $address->desttel_mg22,
                    'destcell_mg22' => $address->destcell_mg22,
                    'destemail_mg22' => $address->destemail_mg22,
                    'destfax_mg22' => $address->destfax_mg22,
                    'destnote_mg22' => $address->destnote_mg22,
                    'aliqrid_cg28' => $address->aliqrid_cg28,
                    'statoest_cg07' => $address->statoest_cg07,
                    'vett1_mg14' => $address->vett1_mg14,
                    'erp_lastchange' => optional($address->erp_lastchange)?->toDateString(),
                ];
            })->values()->all()
        );

        $customer->setAttribute('customer_listino_assignments', $assignments->all());
        $customer->setAttribute('customer_assignment_listino_ids', $assignmentListinoIds->all());
        $customer->setAttribute('customer_assignment_listino_ids_text', $assignmentListinoIds->implode(', '));
        $customer->setAttribute('customer_default_listino_id', $defaultListinoId);
        $customer->setAttribute('customer_effective_listino_ids', $effectiveListinoIds->all());
        $customer->setAttribute('customer_effective_listino_ids_text', $effectiveListinoIds->implode(', '));
        $customer->setAttribute('customer_listini_count', $effectiveListinoIds->count());
        $customer->setAttribute('customer_listino_summaries', $listinoSummaries->all());
        $customer->setAttribute('primary_listino_id', $primaryListinoId);
        $customer->setAttribute('primary_listino_summary', $primaryListinoSummary);

        $customer->setAttribute('listino_summary', $primaryListinoSummary);
        $customer->setAttribute('default_listino_summary', $defaultListinoSummary);
        $customer->setAttribute('listino_products_count', $primaryListinoSummary->products_count ?? 0);
        $customer->setAttribute('listino_rows_count', $primaryListinoSummary->rows_count ?? 0);
        $customer->setAttribute('listino_min_price', $primaryListinoSummary->min_price ?? null);
        $customer->setAttribute('listino_max_price', $primaryListinoSummary->max_price ?? null);
    }

    private function storesByDitta(Collection $customers): Collection
    {
        $ditte = $customers
            ->pluck('ditta_cg18')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        if (empty($ditte)) {
            return collect();
        }

        return Store::query()
            ->whereIn('ditta_cg18', $ditte)
            ->orderBy('name')
            ->orderBy('erp_site_code')
            ->get([
                'id',
                'name',
                'domain',
                'erp_site_code',
                'ditta_cg18',
                'is_active',
                'is_b2b',
            ])
            ->groupBy(fn ($row) => (int) $row->ditta_cg18);
    }

    private function shippingAddressesByCustomers(Collection $customers): Collection
    {
        $pairs = $customers
            ->filter(fn ($customer) => $customer instanceof Customer)
            ->map(fn (Customer $customer) => [
                'ditta_cg18' => (int) $customer->ditta_cg18,
                'tipocf_cg44' => (int) $customer->tipocf_cg44,
                'clifor_cg44' => (int) $customer->clifor_cg44,
            ])
            ->filter(fn (array $pair) => $pair['ditta_cg18'] > 0 && $pair['clifor_cg44'] > 0)
            ->unique(fn (array $pair) => $pair['ditta_cg18'] . ':' . $pair['tipocf_cg44'] . ':' . $pair['clifor_cg44'])
            ->values();

        if ($pairs->isEmpty()) {
            return collect();
        }

        $addresses = CustomerShippingAddress::query()
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($sub) use ($pair) {
                        $sub->where('ditta_cg18', $pair['ditta_cg18'])
                            ->where('tipocf_cg44', $pair['tipocf_cg44'])
                            ->where('clifor_cg44', $pair['clifor_cg44']);
                    });
                }
            })
            ->where('is_active', true)
            ->orderBy('ditta_cg18')
            ->orderBy('tipocf_cg44')
            ->orderBy('clifor_cg44')
            ->orderBy('coddestin_mg22')
            ->get();

        return $addresses->groupBy(fn (CustomerShippingAddress $address) => $this->customerFullKey(
            (int) $address->ditta_cg18,
            (int) $address->tipocf_cg44,
            (int) $address->clifor_cg44
        ));
    }

    private function listinoAssignmentsByCustomers(Collection $customers): Collection
    {
        $pairs = $customers
            ->filter(fn ($customer) => $customer instanceof Customer)
            ->map(fn (Customer $customer) => [
                'ditta_cg18' => (int) $customer->ditta_cg18,
                'clifor_cg44' => (int) $customer->clifor_cg44,
            ])
            ->filter(fn (array $pair) => $pair['ditta_cg18'] > 0 && $pair['clifor_cg44'] > 0)
            ->unique(fn (array $pair) => $pair['ditta_cg18'] . ':' . $pair['clifor_cg44'])
            ->values();

        if ($pairs->isEmpty()) {
            return collect();
        }

        return CustomerListinoAssignment::query()
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($sub) use ($pair) {
                        $sub->where('ditta_cg18', $pair['ditta_cg18'])
                            ->where('clifor_cg44', $pair['clifor_cg44']);
                    });
                }
            })
            ->where('is_active', true)
            ->orderBy('ditta_cg18')
            ->orderBy('clifor_cg44')
            ->orderBy('listino_id')
            ->get()
            ->groupBy(fn (CustomerListinoAssignment $assignment) => $this->customerListinoKey(
                (int) $assignment->ditta_cg18,
                (int) $assignment->clifor_cg44
            ));
    }

    private function customerListinoAssignmentsForCustomer(Customer $customer): Collection
    {
        return CustomerListinoAssignment::query()
            ->where('ditta_cg18', (int) $customer->ditta_cg18)
            ->where('clifor_cg44', (int) $customer->clifor_cg44)
            ->where('is_active', true)
            ->orderBy('listino_id')
            ->get();
    }

    private function listinoSummaryByCustomers(Collection $customers, Collection $assignmentsByCustomerKey, Store $currentStore): Collection
    {
        $ditte = $customers
            ->pluck('ditta_cg18')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        $pairs = [];

        foreach ($customers as $customer) {
            if (!$customer instanceof Customer) {
                continue;
            }

            $ditta = (int) $customer->ditta_cg18;
            $customerListinoKey = $this->customerListinoKey(
                $ditta,
                (int) $customer->clifor_cg44
            );
            $assignments = $assignmentsByCustomerKey->get($customerListinoKey, collect());

            foreach ($assignments as $assignment) {
                $listinoId = (int) ($assignment->listino_id ?? 0);

                if ($ditta > 0 && $listinoId > 0) {
                    $pairs[$this->dittaListinoKey($ditta, $listinoId)] = [
                        'ditta_cg18' => $ditta,
                        'listino_id' => $listinoId,
                    ];
                }
            }

            if ($assignments->isEmpty()) {
                $defaultListinoId = 0;

                if ((int) ($currentStore->ditta_cg18 ?? 0) === $ditta) {
                    $defaultListinoId = (int) ($this->listinoResolver()->defaultListinoForStore($currentStore) ?? 0);
                }

                if ($defaultListinoId <= 0) {
                    $defaultListinoId = (int) ($customer->codlistinoded ?? 0);
                }

                if ($ditta > 0 && $defaultListinoId > 0) {
                    $pairs[$this->dittaListinoKey($ditta, $defaultListinoId)] = [
                        'ditta_cg18' => $ditta,
                        'listino_id' => $defaultListinoId,
                    ];
                }
            }
        }

        if (empty($ditte) || empty($pairs)) {
            return collect();
        }

        $rows = DB::table('price_tiers')
            ->selectRaw('
                ditta_cg18,
                listino_id,
                COUNT(*) as rows_count,
                COUNT(DISTINCT sku) as products_count,
                MIN(CAST(price_net AS DECIMAL(18,6))) as min_price,
                MAX(CAST(price_net AS DECIMAL(18,6))) as max_price
            ')
            ->whereIn('ditta_cg18', $ditte)
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($sub) use ($pair) {
                        $sub->where('ditta_cg18', $pair['ditta_cg18'])
                            ->where('listino_id', $pair['listino_id']);
                    });
                }
            })
            ->groupBy('ditta_cg18', 'listino_id')
            ->get();

        return $rows->keyBy(fn ($row) => $this->dittaListinoKey(
            (int) $row->ditta_cg18,
            (int) $row->listino_id
        ));
    }

    private function listinoSummariesByListini(int $ditta, array $listinoIds): Collection
    {
        if ($ditta <= 0 || empty($listinoIds)) {
            return collect();
        }

        return DB::table('price_tiers')
            ->selectRaw('
                ditta_cg18,
                listino_id,
                COUNT(*) as rows_count,
                COUNT(DISTINCT sku) as products_count,
                MIN(CAST(price_net AS DECIMAL(18,6))) as min_price,
                MAX(CAST(price_net AS DECIMAL(18,6))) as max_price
            ')
            ->where('ditta_cg18', $ditta)
            ->whereIn('listino_id', $listinoIds)
            ->groupBy('ditta_cg18', 'listino_id')
            ->orderBy('listino_id')
            ->get();
    }

    private function customerKey(int $ditta, int $tipoCf, int $clifor): string
    {
        return $ditta . ':' . $tipoCf . ':' . $clifor;
    }

    private function customerFullKey(int $ditta, int $tipoCf, int $clifor): string
    {
        return $ditta . ':' . $tipoCf . ':' . $clifor;
    }

    private function customerListinoKey(int $ditta, int $clifor): string
    {
        return $ditta . ':' . $clifor;
    }

    private function dittaListinoKey(int $ditta, int $listinoId): string
    {
        return $ditta . ':' . $listinoId;
    }

    private function listinoResolver(): CustomerListinoResolver
    {
        return app(CustomerListinoResolver::class);
    }
}
