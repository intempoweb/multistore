<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_popups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('display_scope', 40)->default('home');
            $table->string('frequency', 40)->default('once_session');
            $table->string('position', 40)->default('center');
            $table->string('image_url')->nullable();
            $table->string('cta_url')->nullable();
            $table->boolean('open_in_new_tab')->default(false);
            $table->string('background_color', 20)->nullable();
            $table->string('text_color', 20)->nullable();
            $table->string('button_background_color', 20)->nullable();
            $table->string('button_text_color', 20)->nullable();
            $table->unsignedInteger('delay_ms')->default(0);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'is_active', 'display_scope']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('storefront_popup_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_popup_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('body')->nullable();
            $table->string('cta_label')->nullable();
            $table->timestamps();

            $table->unique(['storefront_popup_id', 'locale'], 'storefront_popup_locale_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_popup_translations');
        Schema::dropIfExists('storefront_popups');
    }
};
