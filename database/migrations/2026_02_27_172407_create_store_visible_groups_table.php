<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_visible_groups', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | ERP columns (MUST EXIST)
            |--------------------------------------------------------------------------
            | ERP: dbo.ANAGRAMARCLIVIS
            | - DITTA_CG18      numeric(5,0)
            | - CODICESITO      int
            | - CODICE_XX32     char(25)
            | - DESCRIZIONE_XX32 char(60)
            */

            $table->unsignedSmallInteger('ditta_cg18');

            // CODICESITO (int) => in app lo chiamiamo site_type
            $table->unsignedInteger('site_type');

            // char(25) -> salvare trim (lato sync)
            $table->string('codice_xx32', 25);

            // char(60) -> salvare trim (lato sync)
            $table->string('descrizione_xx32', 60)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Local watermarks
            |--------------------------------------------------------------------------
            */

            $table->dateTime('erp_last_seen_at')->nullable()->index();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Constraints / Indexes
            |--------------------------------------------------------------------------
            */

            $table->unique(
                ['ditta_cg18', 'site_type', 'codice_xx32'],
                'uq_store_visible_groups_key'
            );

            // Query per contesto store
            $table->index(
                ['ditta_cg18', 'site_type'],
                'ix_svg_ctx'
            );

            // Query per join con prodotti (ditta + codice gruppo)
            $table->index(
                ['ditta_cg18', 'codice_xx32'],
                'ix_svg_ditta_code'
            );

            // Join diretto (ditta + site + codice)
            $table->index(
                ['ditta_cg18', 'site_type', 'codice_xx32'],
                'ix_svg_ctx_code'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_visible_groups');
    }
};