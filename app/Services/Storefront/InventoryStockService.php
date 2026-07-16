<?php

namespace App\Services\Storefront;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryStockService
{
    public function confirmOrderStock(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $order->loadMissing(['items']);
            $items = collect($order->items)
                ->filter(fn ($item) => $item instanceof OrderItem)
                ->filter(fn ($item) => trim((string) $item->sku) !== '' && (float) ($item->quantity ?? 0) > 0)
                ->values();

            $stats = [
                'checked' => 0,
                'movements_created' => 0,
                'already_confirmed' => 0,
                'products_updated' => 0,
            ];

            if ($items->isEmpty()) {
                return $stats;
            }

            $existingMovementItemIds = StockMovement::query()
                ->where('type', 'order_confirmed')
                ->whereIn('order_item_id', $items->pluck('id')->all())
                ->pluck('order_item_id')
                ->map(fn ($id) => (int) $id)
                ->flip();

            $productsBySku = Product::query()
                ->whereIn('sku', $items->pluck('sku')->map(fn ($sku) => trim((string) $sku))->unique()->values()->all())
                ->where('type', 'simple')
                ->lockForUpdate()
                ->get()
                ->groupBy(fn (Product $product) => trim((string) $product->sku));

            $stockByProductId = $productsBySku
                ->flatten(1)
                ->mapWithKeys(fn (Product $product) => [(int) $product->id => (float) ($product->stock_qty ?? 0)])
                ->all();
            $productUpdates = [];
            $movementRows = [];
            $now = now();

            foreach ($items as $item) {
                $sku = trim((string) $item->sku);
                $qty = (float) ($item->quantity ?? 0);

                $stats['checked']++;

                if ($existingMovementItemIds->has((int) $item->id)) {
                    $stats['already_confirmed']++;
                    continue;
                }

                $products = $productsBySku->get($sku, collect());

                if ($products->isEmpty()) {
                    throw new InvalidArgumentException("Prodotto {$sku} non trovato per scarico giacenza.");
                }

                $mainProduct = $products->firstWhere('id', $item->product_id) ?? $products->first();

                $stockBefore = (float) ($stockByProductId[(int) $mainProduct->id] ?? $mainProduct->stock_qty ?? 0);
                $noBackorder = $order->isB2c()
                    ? true
                    : (bool) ($mainProduct->no_backorder ?? false);

                if ($noBackorder && $qty > $stockBefore) {
                    throw new InvalidArgumentException(sprintf(
                        'Giacenza insufficiente per %s. Richiesti %s, disponibili %s.',
                        $sku,
                        number_format($qty, 3, ',', '.'),
                        number_format($stockBefore, 3, ',', '.')
                    ));
                }

                $stockAfter = $stockBefore - $qty;

                foreach ($products as $product) {
                    $productId = (int) $product->id;
                    $stockByProductId[$productId] = (float) ($stockByProductId[$productId] ?? $product->stock_qty ?? 0) - $qty;
                    $productUpdates[$productId] = [
                        'id' => $productId,
                        'stock_qty' => $stockByProductId[$productId],
                    ];

                    $stats['products_updated']++;
                }

                $movementRows[] = [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'product_id' => $mainProduct->id,
                    'ditta_cg18' => $item->ditta_cg18,
                    'site_type' => $item->site_type,
                    'sku' => $sku,
                    'type' => 'order_confirmed',
                    'qty_delta' => 0 - $qty,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'meta' => json_encode([
                        'order_number' => $order->order_number,
                        'channel' => $order->channel,
                    ], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $stats['movements_created']++;
            }

            foreach ($productUpdates as $update) {
                Product::query()
                    ->whereKey($update['id'])
                    ->update([
                        'stock_qty' => $update['stock_qty'],
                        'updated_at' => $now,
                    ]);
            }

            foreach (array_chunk($movementRows, 500) as $chunk) {
                StockMovement::query()->insert($chunk);
            }

            return $stats;
        });
    }
}
