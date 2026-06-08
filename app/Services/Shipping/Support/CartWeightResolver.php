<?php

namespace App\Services\Shipping\Support;

use App\Models\Cart;

class CartWeightResolver
{
    public function resolve(Cart $cart): float
    {
        $weight = 0.0;

        foreach ($cart->items as $item) {
            $itemWeight = (float) (
                $item->weight
                ?? $item->product_weight
                ?? 0
            );

            $weight += $itemWeight * (float) $item->quantity;
        }

        return $weight;
    }
}