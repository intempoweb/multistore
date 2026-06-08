<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreVisibleGroup;
use Illuminate\Http\Request;

class StoreVisibleGroupController extends Controller
{
    public function index(Request $request)
    {
        /** @var Store $store */
        $store = app('currentStore');

        $q = StoreVisibleGroup::query()
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('site_type', (int) $store->erp_site_code);

        if ($request->filled('code')) {
            $code = trim((string) $request->input('code'));
            $q->where('codice_xx32', 'like', "%{$code}%");
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $q->where(function ($sub) use ($search) {
                $sub->where('descrizione_xx32', 'like', "%{$search}%")
                    ->orWhere('codice_xx32', 'like', "%{$search}%");
            });
        }

        $groups = $q
            ->orderBy('codice_xx32')
            ->paginate(100)
            ->withQueryString();

        return view('admin.store_visible_groups.index', compact('store', 'groups'));
    }

    public function show(int $id)
    {
        /** @var Store $store */
        $store = app('currentStore');

        $group = StoreVisibleGroup::query()
            ->where('id', $id)
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('site_type', (int) $store->erp_site_code)
            ->firstOrFail();

        return view('admin.store_visible_groups.show', compact('store', 'group'));
    }
}