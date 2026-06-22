<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_page_block_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_page_block_id')
                ->constrained('storefront_page_blocks')
                ->cascadeOnDelete();
            $table->string('media_type', 20)->default('image');
            $table->string('desktop_path')->nullable();
            $table->string('mobile_path')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(
                ['storefront_page_block_id', 'is_active', 'sort_order'],
                'ix_page_block_media_active_sort'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_page_block_media');
    }
};
