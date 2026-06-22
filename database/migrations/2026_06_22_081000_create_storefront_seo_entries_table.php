<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_seo_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('entity_type', 30);
            $table->string('entity_key', 255);
            $table->string('meta_title', 190)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('heading', 190)->nullable();
            $table->text('intro')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('robots', 80)->default('index,follow');
            $table->string('og_title', 190)->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['store_id', 'locale', 'entity_type', 'entity_key'],
                'uq_storefront_seo_entity'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_seo_entries');
    }
};
