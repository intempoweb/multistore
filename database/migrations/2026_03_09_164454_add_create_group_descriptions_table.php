<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_descriptions', function (Blueprint $table) {

            $table->id();

            $table->unsignedSmallInteger('ditta_cg18')->index();
            $table->unsignedSmallInteger('site_type')->index();

            // lingua ERP -> locale applicativo
            $table->string('locale', 5)->index();

            // gerarchia catalogo
            $table->string('fam_code', 20)->nullable()->index();
            $table->string('sfam_code', 20)->nullable()->index();
            $table->string('gruppo_code', 20)->nullable()->index();

            // descrizione ERP
            $table->string('description', 255)->nullable();

            // flag ERP
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            /**
             * chiave unica per:
             * ditta
             * site
             * lingua
             * livello catalogo
             */
            $table->unique(
                [
                    'ditta_cg18',
                    'site_type',
                    'locale',
                    'fam_code',
                    'sfam_code',
                    'gruppo_code'
                ],
                'uq_catalog_description'
            );

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_descriptions');
    }
};