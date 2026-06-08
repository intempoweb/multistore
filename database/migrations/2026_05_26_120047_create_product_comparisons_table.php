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
        Schema::create('product_comparisons', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | ERP Context
            |--------------------------------------------------------------------------
            */

            // ERP: DITTA_CG18
            $table->unsignedSmallInteger('ditta_cg18')->index();

            // ERP: FLG_B2B_B2C_WEBT04
            $table->unsignedSmallInteger('site_type')->index();

            /*
            |--------------------------------------------------------------------------
            | Product reference
            |--------------------------------------------------------------------------
            */

            // ERP: CODART_MG66
            $table->string('sku', 25);

            /*
            |--------------------------------------------------------------------------
            | Comparative source
            |--------------------------------------------------------------------------
            */

            // buffetti / flex / dataufficio / semper / etc.
            $table->string('source', 40);

            // codice articolo concorrente
            $table->string('comparison_sku', 100);

            // ERP: LASTCHANGE
            $table->date('erp_lastchange')->nullable()->index();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->unique(
                ['ditta_cg18', 'site_type', 'sku', 'source', 'comparison_sku'],
                'uq_product_comparisons'
            );

            $table->index(
                ['ditta_cg18', 'site_type', 'sku'],
                'ix_product_comparisons_product'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_comparisons');
    }
};
