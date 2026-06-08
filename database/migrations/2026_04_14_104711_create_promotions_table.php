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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            // contesto ERP
            $table->integer('ditta_cg18');
            $table->integer('site_type')->nullable();

            // identificazione
            $table->string('name');
            $table->string('code')->nullable()->index();

            // tipo promo legacy (manteniamo per compatibilità)
            $table->string('type')->nullable();

            // definizione sconto (strutturata per il motore)
            $table->string('discount_type')->nullable(); // percent | fixed
            $table->decimal('discount_value', 12, 3)->nullable();
            $table->string('scope')->default('cart'); // cart | line

            // configurazione dinamica
            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();

            // gestione
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);

            // validità
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->index(['ditta_cg18', 'site_type']);
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
