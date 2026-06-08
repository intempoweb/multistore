<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();

            /**
             * ERP Mapping (source of truth)
             * - DITTA_CG18: ditta (numeric)
             * - FLG_B2B_B2C_WEBT01: "sito" (numeric) -> tabella SITIWEBB2BEB2C
             */
            $table->unsignedSmallInteger('ditta_cg18');          // es: 1=Intempo, 3=Fipell
            $table->unsignedSmallInteger('erp_site_code');       // es: 1,2,4,5,6,99 (SITIWEBB2BEB2C)

            /**
             * App aliases (leggibili) - NON source of truth
             * utili per config, seed, UI ecc.
             */
            $table->string('company_code', 50)->nullable();      // "INTEMPO", "FIPELL" (alias)
            $table->string('site_code', 50)->nullable();         // "CIAK", "TEKNIKO" (alias)

            /**
             * Dominio (multistore via host header / vhost)
             */
            $table->string('domain', 255)->unique();             // ciak.test, teknikoshop.test, ecc.

            /**
             * Etichetta
             */
            $table->string('name', 120);

            /**
             * Tipo store (B2B/B2C)
             * (può derivare da ERP o essere configurazione tua)
             */
            $table->boolean('is_b2b')->default(false);

            /**
             * Tema frontend
             */
            $table->string('theme', 80)->default('default');

            /**
             * Locales
             */
            $table->string('default_locale', 5)->default('it');
            $table->json('supported_locales')->nullable();

            /**
             * Stato
             */
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            /**
             * Vincoli logici
             * uno store è identificato da (ditta, sito ERP)
             */
            $table->unique(['ditta_cg18', 'erp_site_code'], 'stores_erp_unique');

            /**
             * (opzionale) se vuoi mantenere anche unicità sugli alias
             * commentalo se preferisci alias ripetibili
             */
            $table->unique(['company_code', 'site_code'], 'stores_company_site_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};