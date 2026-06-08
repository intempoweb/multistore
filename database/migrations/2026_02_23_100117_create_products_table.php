<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | ERP Context
            |--------------------------------------------------------------------------
            */

            // ERP: DITTA_CG18
            $table->unsignedSmallInteger('ditta_cg18')->index();

            // ERP: FLG_B2B_B2C_WEBT01 (SITIWEBB2BEB2C)
            $table->unsignedSmallInteger('site_type')->index();

            /*
            |--------------------------------------------------------------------------
            | Identità prodotto
            |--------------------------------------------------------------------------
            */

            // ERP: CODART_MG66
            $table->string('sku', 25);

            // ERP: RADICEARTIC_WEBT01 (codice padre)
            $table->string('parent_code', 108)->nullable()->index();

            // Se il padre vive su site_type diverso
            // Se NULL => assume stesso site_type del figlio
            $table->unsignedSmallInteger('parent_site_type')->nullable()->index();

            // Tipo nel read model
            $table->enum('type', ['simple', 'configurable'])
                  ->default('simple')
                  ->index();

            /*
            |--------------------------------------------------------------------------
            | Stato commerciale
            |--------------------------------------------------------------------------
            */

            // ERP: FLGATTIVO_WEBT01
            $table->boolean('is_active')->default(false)->index();

            // ERP: FLGNOORDINZERO_WEBT01 (true = NO backorder)
            $table->boolean('no_backorder')->default(true)->index();

            // Stock replicato per contesto (per ora)
            $table->decimal('stock_qty', 15, 3)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Dati tecnici ERP
            |--------------------------------------------------------------------------
            */

            // ERP: CODGRUPFIS_MG61
            $table->string('codgrupfis_mg61', 12)->nullable()->index();

            // ERP: CODBARCODE_MG65
            $table->string('barcode', 40)->nullable()->index();

            // ERP: UNITAMISURA_WEBT01
            $table->string('unit', 4)->nullable();

            // ERP: DATAULTIMOAGG_WEBT01
            $table->date('erp_dataultimoagg')->nullable()->index();

            // Delta robusto (es: VWEBT0_LASTCHANGE)
            $table->dateTime('erp_lastchange')->nullable()->index();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Vincoli e indici
            |--------------------------------------------------------------------------
            */

            // SKU unica per ditta + site
            $table->unique(
                ['ditta_cg18', 'site_type', 'sku'],
                'uq_products_ditta_site_sku'
            );

            // Query figli per contesto
            $table->index(
                ['ditta_cg18', 'site_type', 'parent_code'],
                'ix_products_ditta_site_parent'
            );

            // Query per tipo in contesto
            $table->index(
                ['ditta_cg18', 'site_type', 'type'],
                'ix_products_ditta_site_type'
            );

            // Padre con site esplicito
            $table->index(
                ['ditta_cg18', 'parent_site_type', 'parent_code'],
                'ix_products_ditta_parentctx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};