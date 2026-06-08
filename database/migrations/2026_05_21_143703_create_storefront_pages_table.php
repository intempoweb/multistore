
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
        Schema::create('storefront_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('slug', 120);
            $table->string('title', 190);
            $table->string('template', 80)->default('default');
            $table->string('layout', 80)->nullable();
            $table->string('meta_title', 190)->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'slug'], 'uq_storefront_pages_store_slug');
            $table->index(['store_id', 'is_active'], 'ix_storefront_pages_store_active');
            $table->index(['store_id', 'template'], 'ix_storefront_pages_store_template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storefront_pages');
    }
};
