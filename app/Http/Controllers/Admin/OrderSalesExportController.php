<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Admin\Orders\OrderSalesExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderSalesExportController extends Controller
{
    public function __invoke(Request $request, OrderSalesExportService $exportService): BinaryFileResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ]);

        $month = Carbon::createFromFormat('Y-m', $data['month'] ?? now('Europe/Rome')->format('Y-m'))
            ->startOfMonth();
        $storeId = isset($data['store_id']) ? (int) $data['store_id'] : null;
        $allowedStoreIds = $this->restrictedStoreIds($request);

        if ($allowedStoreIds !== null && $storeId !== null && ! in_array($storeId, $allowedStoreIds, true)) {
            abort(403);
        }

        $path = $exportService->build(
            month: $month,
            storeId: $storeId,
            allowedStoreIds: $allowedStoreIds,
            channel: $allowedStoreIds !== null ? 'b2c' : null,
        );

        return response()
            ->download(
                $path,
                $exportService->filename($month, $storeId),
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            )
            ->deleteFileAfterSend(true);
    }

    private function restrictedStoreIds(Request $request): ?array
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'isB2cManager') || ! $user->isB2cManager()) {
            return null;
        }

        return Store::query()
            ->where('is_active', true)
            ->get(['id', 'is_b2b'])
            ->filter(fn (Store $store) => method_exists($user, 'canAccessAdminStore') && $user->canAccessAdminStore($store))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
