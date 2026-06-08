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

            $stats = [
                'checked' => 0,
                'movements_created' => 0,
                'already_confirmed' => 0,
                'products_updated' => 0,
            ];

            foreach ($order->items as $item) {
                if (!$item instanceof OrderItem) {
                    continue;
                }

                $sku = trim((string) $item->sku);
                $qty = (float) ($item->quantity ?? 0);

                if ($sku === '' || $qty <= 0) {
                    continue;
                }

                $stats['checked']++;

                $alreadyExists = StockMovement::query()
                    ->where('type', 'order_confirmed')
                    ->where('order_item_id', $item->id)
                    ->exists();

                if ($alreadyExists) {
                    $stats['already_confirmed']++;
                    continue;
                }

                $products = Product::query()
                    ->where('sku', $sku)
                    ->where('type', 'simple')
                    ->lockForUpdate()
                    ->get();

                if ($products->isEmpty()) {
                    throw new InvalidArgumentException("Prodotto {$sku} non trovato per scarico giacenza.");
                }

                $mainProduct = $products->firstWhere('id', $item->product_id) ?? $products->first();

                $stockBefore = (float) ($mainProduct->stock_qty ?? 0);
                $noBackorder = (bool) ($mainProduct->no_backorder ?? false);

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
                    $product->forceFill([
                        'stock_qty' => ((float) $product->stock_qty) - $qty,
                    ])->save();

                    $stats['products_updated']++;
                }

                StockMovement::query()->create([
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
                    'meta' => [
                        'order_number' => $order->order_number,
                        'channel' => $order->channel,
                    ],
                ]);

                $stats['movements_created']++;
            }

            return $stats;
        });
    }
}