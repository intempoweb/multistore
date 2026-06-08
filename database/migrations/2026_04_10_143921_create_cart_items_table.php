<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            // Relazione con cart
            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();

            // Contesto ERP / store
            $table->unsignedInteger('ditta_cg18');
            $table->unsignedInteger('site_type')->nullable();

            // Prodotto (snapshot + riferimento logico)
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('sku');

            // Snapshot prodotto (fondamentale per stabilità carrello)
            $table->string('product_name')->nullable();
            $table->text('product_description')->nullable();
            $table->string('product_thumbnail_url')->nullable();

            // Varianti (es: colore, formato)
            $table->json('variant_attributes')->nullable();

            // Quantità
            $table->decimal('quantity', 15, 3);
            $table->decimal('min_qty', 15, 3)->nullable();
            $table->decimal('step_qty', 15, 3)->nullable();

            // Prezzo snapshot (da PriceResolver)
            $table->string('price_source')->nullable(); // b2b_tier | public
            $table->decimal('price', 15, 3)->nullable();
            $table->decimal('price_net', 15, 3)->nullable();
            $table->decimal('price_gross', 15, 3)->nullable();

            // Listino applicato (B2B)
            $table->unsignedInteger('listino_id')->nullable();

            // Range quantità applicato (tier)
            $table->decimal('qty_from', 15, 3)->nullable();
            $table->decimal('qty_to', 15, 3)->nullable();

            // Sconti ERP
            $table->decimal('sc1', 8, 3)->nullable();
            $table->decimal('sc2', 8, 3)->nullable();
            $table->decimal('sc3', 8, 3)->nullable();
            $table->decimal('sc4', 8, 3)->nullable();
            $table->decimal('sc5', 8, 3)->nullable();
            $table->decimal('sc6', 8, 3)->nullable();

            // Totali riga
            $table->decimal('row_subtotal', 15, 3)->nullable();
            $table->decimal('row_discount_total', 15, 3)->nullable();
            $table->decimal('row_tax_total', 15, 3)->nullable();
            $table->decimal('row_total', 15, 3)->nullable();

            // Snapshot pricing payload completo (debug / ricostruzione)
            $table->json('price_payload')->nullable();

            // Snapshot stock (per UX)
            $table->decimal('stock_qty', 15, 3)->nullable();
            $table->boolean('no_backorder')->nullable();

            $table->timestamps();

            // Index utili
            $table->index(['cart_id']);
            $table->index(['sku']);
            $table->index(['ditta_cg18']);
            $table->index(['site_type']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};