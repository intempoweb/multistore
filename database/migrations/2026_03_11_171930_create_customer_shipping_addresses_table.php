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
        Schema::create('customer_shipping_addresses', function (Blueprint $table) {
            $table->id();

            // ERP key
            $table->integer('ditta_cg18');
            $table->integer('tipocf_cg44');
            $table->integer('clifor_cg44');
            $table->integer('coddestin_mg22');

            // ERP destination data
            $table->string('destragsoc_mg22', 60)->nullable();
            $table->string('destind_mg22', 40)->nullable();
            $table->string('destcap_mg22', 10)->nullable();
            $table->string('destcitta_mg22', 40)->nullable();
            $table->string('destprov_mg22', 2)->nullable();

            // ERP contacts
            $table->string('desttel_mg22', 24)->nullable();
            $table->string('destcell_mg22', 24)->nullable();
            $table->string('destemail_mg22', 128)->nullable();
            $table->string('destfax_mg22', 24)->nullable();

            // ERP notes / logistics
            $table->string('destnote_mg22', 512)->nullable();
            $table->string('aliqrid_cg28', 4)->nullable();
            $table->integer('statoest_cg07')->nullable();
            $table->string('vett1_mg14', 3)->nullable();

            // ERP sync metadata
            $table->date('erp_lastchange')->nullable();
            $table->timestamp('erp_last_seen_at')->nullable();

            // Local flags
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['ditta_cg18', 'tipocf_cg44', 'clifor_cg44', 'coddestin_mg22'],
                'customer_shipping_addresses_erp_unique'
            );

            $table->index(['ditta_cg18', 'tipocf_cg44', 'clifor_cg44'], 'customer_shipping_addresses_customer_idx');
            $table->index(['ditta_cg18', 'is_active'], 'customer_shipping_addresses_ditta_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_shipping_addresses');
    }
};