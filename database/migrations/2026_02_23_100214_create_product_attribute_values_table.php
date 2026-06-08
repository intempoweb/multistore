<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('attribute_id')
                ->constrained('attributes')
                ->cascadeOnDelete();

            // null per attributi non-select
            $table->foreignId('attribute_value_id')
                ->nullable()
                ->constrained('attribute_values')
                ->nullOnDelete();

            // per text/number/boolean (oppure per valori ERP non-normalizzati)
            $table->string('raw_value', 255)->nullable();

            /**
             * chiave normalizzata per evitare duplicati anche con NULL
             * - select: "v:{attribute_value_id}"
             * - raw:    "r:{raw_value}"
             *
             * ✅ 191 per evitare errori "key too long" su MySQL utf8mb4
             */
            $table->string('value_key', 191);

            $table->dateTime('erp_lastchange')->nullable()->index();

            $table->timestamps();

            // indici utili (questa coppia è quella che userai sempre)
            $table->index(['product_id', 'attribute_id'], 'ix_pav_product_attr');

            // utile per query "dammi i prodotti che hanno quel valore"
            $table->index(['attribute_id', 'attribute_value_id'], 'ix_pav_attr_value');

            // blocca duplicati identici (supporta multivalore)
            $table->unique(['product_id', 'attribute_id', 'value_key'], 'uq_pav_product_attr_valuekey');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};