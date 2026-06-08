<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rule_store', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shipping_rule_id')
                ->constrained('shipping_rules')
                ->cascadeOnDelete();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(
                ['shipping_rule_id', 'store_id'],
                'shipping_rule_store_unique'
            );

            $table->index(
                ['store_id'],
                'shipping_rule_store_store_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rule_store');
    }
};