<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_wishlist_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->nullOnDelete();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            $table->unsignedSmallInteger('ditta_cg18');
            $table->unsignedSmallInteger('site_type')->nullable();
            $table->string('sku', 25);

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(
                ['customer_id', 'ditta_cg18', 'site_type', 'sku'],
                'uq_customer_wishlist_context_sku'
            );

            $table->index(['customer_id']);
            $table->index(['store_id']);
            $table->index(['product_id']);
            $table->index(['ditta_cg18', 'site_type']);
            $table->index(['sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_wishlist_items');
    }
};
