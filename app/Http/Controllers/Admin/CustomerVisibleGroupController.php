<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerVisibleGroup;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerVisibleGroupController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Store $store */
        $store = $this->currentStore();

        $q = CustomerVisibleGroup::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18);

        if ($request->filled('tipocf')) {
            $q->where('tipocf_cg44', (int) $request->input('tipocf'));
        }

        if ($request->filled('clifor')) {
            $q->where('clifor_cg44', (int) $request->input('clifor'));
        }

        if ($request->filled('site_flag')) {
            $siteFlag = $this->normalizeSiteFlag($request->input('site_flag'));

            if ($siteFlag !== null) {
                $q->where('flg_b2b_b2c_webt81', $siteFlag);
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

        if ($request->filled('group')) {
            $g = trim((string) $request->input('group'));

            $q->where(function ($sub) use ($g) {
                $sub->where('codice_xx32', 'like', "%{$g}%")
                    ->orWhere('descrizione_xx32', 'like', "%{$g}%");
            });
        }

        $rows = $q
            ->orderBy('tipocf_cg44')
            ->orderBy('clifor_cg44')
            ->orderBy('codice_xx32')
            ->paginate(50)
            ->withQueryString();

        return view('admin.customer-visible-groups.index', [
            'store' => $store,
            'rows' => $rows,
            'filters' => [
                'tipocf' => (string) $request->input('tipocf', ''),
                'clifor' => (string) $request->input('clifor', ''),
                'site_flag' => (string) $request->input('site_flag', ''),
                'active' => (string) $request->input('active', ''),
                'group' => (string) $request->input('group', ''),
            ],
        ]);
    }

    private function currentStore(): Store
    {
        /** @var Store $store */
        $store = app()->bound('adminStore')
            ? app('adminStore')
            : app('currentStore');

        return $store;
    }

    private function normalizeSiteFlag(mixed $value): ?string
    {
        $raw = strtoupper(trim((string) $value));

        if ($raw === '') {
            return null;
        }

        // supporta valori testuali
        if (in_array($raw, ['B', 'B2B', '1'], true)) {
            return '1';
        }

        if (in_array($raw, ['C', 'B2C', '0'], true)) {
            return '0';
        }

        return $raw;
    }
}