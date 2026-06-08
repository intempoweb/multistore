<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_visible_groups', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | ERP columns (MUST EXIST)
            |--------------------------------------------------------------------------
            */

            // numeric(5,0)
            $table->unsignedSmallInteger('ditta_cg18');

            // varchar(1)  (B2B/B2C flag in ERP table)
            $table->string('flg_b2b_b2c_webt81', 1);

            // numeric(1,0)
            $table->unsignedTinyInteger('tipocf_cg44');

            // numeric(8,0)
            $table->unsignedInteger('clifor_cg44');

            // char(25)
            $table->string('codice_xx32', 25);

            // char(60)
            $table->string('descrizione_xx32', 60);

            // numeric(1,0)
            $table->unsignedTinyInteger('flgattivo_xx32');

            // char(10) nullable => salvato come DATE (YYYY-MM-DD)
            $table->date('dataultimoagg_xx32')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Local watermarks
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')->default(false)->index();
            $table->dateTime('erp_last_seen_at')->nullable()->index();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Constraints
            |--------------------------------------------------------------------------
            */

            $table->unique(
                [
                    'ditta_cg18',
                    'flg_b2b_b2c_webt81',
                    'tipocf_cg44',
                    'clifor_cg44',
                    'codice_xx32'
                ],
                'uq_customer_visible_groups_key'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes (ottimizzati per visibilità B2B)
            |--------------------------------------------------------------------------
            */

            // Lookup gruppi per cliente
            $table->index(
                ['ditta_cg18', 'tipocf_cg44', 'clifor_cg44'],
                'ix_cvg_customer_key'
            );

            // Lookup gruppo generico
            $table->index(
                ['ditta_cg18', 'flg_b2b_b2c_webt81', 'codice_xx32'],
                'ix_cvg_group_key'
            );

            // 🔥 Query reale B2B: gruppi attivi per cliente
            $table->index(
                ['ditta_cg18', 'tipocf_cg44', 'clifor_cg44', 'is_active'],
                'ix_cvg_customer_active'
            );

            // 🔥 Query reale B2B: gruppo attivo per site
            $table->index(
                ['ditta_cg18', 'flg_b2b_b2c_webt81', 'codice_xx32', 'is_active'],
                'ix_cvg_group_active'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_visible_groups');
    }
};