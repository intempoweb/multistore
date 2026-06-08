<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('configurable_products', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | ERP mapping
            |--------------------------------------------------------------------------
            | ditta_cg18   => DITTA_CG18
            | site_type    => FLG_B2B_B2C_WEBT01 (SITIWEBB2BEB2C)
            | parent_code  => CODARTPADRE_WEBT00 (codice padre)
            */

            $table->unsignedSmallInteger('ditta_cg18')->index();
            $table->unsignedSmallInteger('site_type')->index();

            // coerenza con RADICEARTIC_WEBT01 (108)
            $table->string('parent_code', 108);

            /*
            |--------------------------------------------------------------------------
            | Extra (padre)
            |--------------------------------------------------------------------------
            */

            // legacy / fallback (se serve)
            $table->string('photo', 255)->nullable();

            // ERP: data ultimo agg (char10 -> date)
            $table->date('dataultimoagg')->nullable()->index();

            // Watermark / delta
            $table->dateTime('erp_lastchange')->nullable()->index();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Vincoli e indici
            |--------------------------------------------------------------------------
            */

            // 1 record per padre per ditta + site_type
            $table->unique(
                ['ditta_cg18', 'site_type', 'parent_code'],
                'uq_configurable_ditta_site_parent'
            );

            // (opzionale) se fai lookup "globale" solo per parent_code
            $table->index('parent_code', 'ix_configurable_parent_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurable_products');
    }
};