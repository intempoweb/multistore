<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();

            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedInteger('ditta_cg18')->nullable();
            $table->unsignedInteger('site_type')->nullable();
            $table->string('sku', 64);

            $table->string('type')->default('order_confirmed');

            $table->decimal('qty_delta', 15, 3);
            $table->decimal('stock_before', 15, 3)->nullable();
            $table->decimal('stock_after', 15, 3)->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['type', 'order_item_id'], 'uq_stock_movements_type_order_item');
            $table->index(['order_id']);
            $table->index(['sku']);
            $table->index(['ditta_cg18', 'site_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};