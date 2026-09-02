<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ditta_cg18');
            $table->unsignedInteger('site_type');
            $table->string('name');
            $table->string('subject');
            $table->string('preview_text')->nullable();
            $table->string('locale', 8)->default('it');
            $table->string('status', 40)->default('draft');
            $table->string('selection_type', 40)->default('manual');
            $table->string('provider', 40)->default('mailchimp');
            $table->unsignedInteger('listino_id')->nullable();
            $table->string('price_mode', 40)->default('listino');
            $table->string('hero_title')->nullable();
            $table->text('hero_text')->nullable();
            $table->string('hero_image_url', 2048)->nullable();
            $table->text('intro_html')->nullable();
            $table->json('filters')->nullable();
            $table->json('settings')->nullable();
            $table->longText('rendered_html')->nullable();
            $table->string('provider_campaign_id')->nullable();
            $table->string('provider_web_id')->nullable();
            $table->string('provider_edit_url', 2048)->nullable();
            $table->timestamp('provider_synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['ditta_cg18', 'site_type']);
            $table->index(['provider', 'provider_campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletters');
    }
};
