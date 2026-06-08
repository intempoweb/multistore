<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // it/en/es...
            $table->string('locale', 5);

            $table->string('name', 255)->nullable();
            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();

            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();

            $table->dateTime('erp_lastchange')->nullable()->index();

            $table->timestamps();

            // 1 traduzione per prodotto per lingua
            $table->unique(['product_id', 'locale'], 'uq_prodtr_product_locale');

            // utile per query tipo: "tutti i prodotti in it con name like ..."
            $table->index(['locale', 'name'], 'ix_prodtr_locale_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_translations');
    }
};