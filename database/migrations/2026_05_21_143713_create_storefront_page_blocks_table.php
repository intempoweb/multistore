
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
        Schema::create('storefront_page_blocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('storefront_page_id')
                ->constrained('storefront_pages')
                ->cascadeOnDelete();

            $table->string('type', 60);
            $table->string('name', 190)->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->string('title', 190)->nullable();
            $table->string('subtitle', 255)->nullable();
            $table->text('content')->nullable();

            $table->string('image_path')->nullable();
            $table->string('mobile_image_path')->nullable();
            $table->string('video_path')->nullable();

            $table->string('button_label', 120)->nullable();
            $table->string('button_url')->nullable();
            $table->boolean('button_new_tab')->default(false);

            $table->string('background_color', 20)->nullable();
            $table->string('text_color', 20)->nullable();
            $table->string('overlay_color', 20)->nullable();
            $table->unsignedTinyInteger('overlay_opacity')->nullable();

            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index([
                'storefront_page_id',
                'is_active',
                'sort_order',
            ], 'ix_storefront_page_blocks_page_active_sort');

            $table->index([
                'storefront_page_id',
                'type',
            ], 'ix_storefront_page_blocks_page_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storefront_page_blocks');
    }
};
