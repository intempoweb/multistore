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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // contesto ERP
            $table->integer('ditta_cg18');
            $table->integer('site_type')->nullable();

            // codice coupon
            $table->string('code')->unique();
            $table->index(['code', 'ditta_cg18', 'site_type']);

            // collegamento promo
            $table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();

            // limiti utilizzo
            $table->integer('usage_limit')->nullable(); // totale utilizzi
            $table->integer('usage_limit_per_customer')->nullable(); // per cliente
            $table->integer('used_count')->default(0);

            // validità
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // stato
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['ditta_cg18', 'site_type']);
            $table->index(['starts_at', 'expires_at']);
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
