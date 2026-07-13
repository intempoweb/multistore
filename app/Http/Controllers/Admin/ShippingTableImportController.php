<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShippingTableImportRequest;
use App\Models\ShippingRule;
use App\Models\Store;
use App\Services\Shipping\Export\ShippingTableExportService;
use App\Services\Shipping\Import\ShippingTableImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use InvalidArgumentException;

class ShippingTableImportController extends Controller
{
    public function __construct(
        protected ShippingTableImportService $shippingTableImportService,
        protected ShippingTableExportService $shippingTableExportService,
    ) {
    }

    public function store(ShippingTableImportRequest $request): RedirectResponse
    {
        $store = $this->resolveAdminStore();

        $file = $request->file('file');
        abort_unless($file !== null, 422, 'File CSV obbligatorio.');

        $path = $file->getRealPath();
        abort_unless(
            is_string($path) && $path !== '',
            422,
            'Impossibile leggere il file caricato.'
        );

        $replaceExisting = $request->boolean('replace_existing');

        $sharedStoreIds = collect($request->input('shared_store_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $allowedSharedStoreIds = $this->resolveShareableStores($store)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedSharedStoreIds = $sharedStoreIds
            ->filter(fn ($id) => in_array($id, $allowedSharedStoreIds, true))
            ->values()
            ->all();

        try {
            DB::transaction(function () use ($store, $path, $replaceExisting, $selectedSharedStoreIds) {
                if ($replaceExisting) {
                    $existingRules = ShippingRule::query()
                        ->where('store_id', $store->id)
                        ->where('type', 'table')
                        ->get();

                    foreach ($existingRules as $existingRule) {
                        $existingRule->stores()->detach();
                    }

                    ShippingRule::query()
                        ->where('store_id', $store->id)
                        ->where('type', 'table')
                        ->delete();
                }

                $this->shippingTableImportService->importFromCsv(
                    $path,
                    (int) $store->id
                );

                ShippingRule::query()
                    ->where('store_id', $store->id)
                    ->whereNull('ditta_cg18')
                    ->whereNull('erp_site_code')
                    ->update([
                        'ditta_cg18' => (int) $store->ditta_cg18,
                        'erp_site_code' => (int) $store->erp_site_code,
                    ]);

                $importedRules = ShippingRule::query()
                    ->where('store_id', $store->id)
                    ->where('type', 'table')
                    ->get();

                foreach ($importedRules as $rule) {
                    $rule->stores()->sync($selectedSharedStoreIds);
                }
            });
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.shipping-rules.index')
                ->with(
                    'error',
                    $e->getMessage() !== ''
                        ? 'Import CSV fallito: ' . $e->getMessage()
                        : 'Import CSV fallito. Verifica formato e contenuto del file.'
                );
        }

        return redirect()
            ->route('admin.shipping-rules.index')
            ->with('success', 'Tabella spedizioni importata correttamente.');
    }

    public function export(): StreamedResponse
    {
        $store = $this->resolveAdminStore();
        abort_if($store->isB2B(), 404);

        $rules = ShippingRule::query()
            ->forStore($store)
            ->where('type', 'table')
            ->orderBy('country')
            ->orderBy('province')
            ->orderBy('cap')
            ->orderBy('weight_from')
            ->orderBy('id')
            ->get(['country', 'province', 'cap', 'weight_from', 'amount']);

        abort_if($rules->isEmpty(), 404, 'Nessuna tabella spedizioni esportabile per lo store corrente.');

        $filename = sprintf(
            'shipping-table-%s-%s.csv',
            str($store->site_code ?: $store->name)->slug(),
            now()->format('Y-m-d-His')
        );

        return response()->streamDownload(function () use ($rules) {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            $this->shippingTableExportService->writeCsv($rules, $handle);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    protected function resolveAdminStore(): Store
    {
        $store = null;

        if (session()->has('admin_store_id')) {
            $store = Store::query()
                ->where('id', (int) session('admin_store_id'))
                ->where('is_active', true)
                ->first();
        }

        if (!$store instanceof Store) {
            $boundStore = current_store();

            if ($boundStore instanceof Store) {
                $store = $boundStore;
            }
        }

        if (!$store instanceof Store) {
            throw new InvalidArgumentException('Nessuno store admin selezionato.');
        }

        return $store;
    }

    protected function resolveShareableStores(Store $store)
    {
        return Store::query()
            ->where('is_active', true)
            ->where('is_b2b', false)
            ->where('ditta_cg18', (int) $store->ditta_cg18)
            ->where('id', '!=', (int) $store->id)
            ->orderBy('name')
            ->get();
    }
}
