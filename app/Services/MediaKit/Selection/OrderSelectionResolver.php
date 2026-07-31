<?php

namespace App\Services\MediaKit\Selection;

use App\Models\MediaAsset;
use App\Models\MediaKitRequest;
use App\Models\Order;
use App\Models\Product;
use App\Services\MediaKit\MediaKitContext;
use App\Services\MediaKit\MediaKitSelection;
use RuntimeException;

final class OrderSelectionResolver implements SelectionResolver
{
    public function supports(string $sourceType): bool
    {
        return $sourceType === MediaKitRequest::SOURCE_ORDER;
    }

    public function resolve(MediaKitRequest $request, MediaKitContext $context): MediaKitSelection
    {
        if (!$request->source_reference) {
            throw new RuntimeException('ID ordine mancante.');
        }

        $order = Order::query()
            ->whereKey((int) $request->source_reference)
            ->where('store_id', $context->store->getKey())
            ->first();

        if (!$order) {
            throw new RuntimeException('Ordine non trovato nello store selezionato.');
        }

        if ($request->customer_id && (int) $order->customer_id !== (int) $request->customer_id) {
            throw new RuntimeException('Ordine non appartenente al cliente selezionato.');
        }

        $order->loadMissing('items');

        $skus = $order->items
            ->pluck('sku')
            ->map(fn ($sku) => trim((string) $sku))
            ->filter(fn (string $sku) => $sku !== '' && !str_starts_with(mb_strtoupper($sku), 'MTBUONO'))
            ->unique()
            ->values();

        $products = Product::query()
            ->with(['mediaAssets' => function ($query): void {
                $query
                    ->whereIn('role', config('mediakit.roles', [
                        MediaAsset::ROLE_MAIN,
                        MediaAsset::ROLE_GALLERY,
                    ]))
                    ->orderBy('sort_order')
                    ->orderBy('id');
            }])
            ->where('ditta_cg18', (int) $order->ditta_cg18)
            ->where('site_type', (int) $order->site_type)
            ->whereIn('sku', $skus->all())
            ->get();

        return new MediaKitSelection(
            $products,
            $request->source_type,
            $request->source_reference,
            $products->isEmpty() ? ['Nessun prodotto locale associato all’ordine.'] : [],
        );
    }
}
