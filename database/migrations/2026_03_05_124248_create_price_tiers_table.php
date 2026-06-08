<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | ERP context (B2B only)
            |--------------------------------------------------------------------------
            */
            $table->unsignedSmallInteger('ditta_cg18');
            $table->unsignedInteger('listino_id');
            $table->string('sku', 25);

            /*
            |--------------------------------------------------------------------------
            | Quantity range
            |--------------------------------------------------------------------------
            */
            $table->decimal('qty_from', 18, 3);
            $table->decimal('qty_to', 18, 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | B2B pricing data from LISTINOCLI_RAGG
            |--------------------------------------------------------------------------
            */
            $table->decimal('price_net', 18, 6)->nullable();

            $table->decimal('sc1', 8, 3)->nullable();
            $table->decimal('sc2', 8, 3)->nullable();
            $table->decimal('sc3', 8, 3)->nullable();
            $table->decimal('sc4', 8, 3)->nullable();
            $table->decimal('sc5', 8, 3)->nullable();
            $table->decimal('sc6', 8, 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | ERP watermarks
            |--------------------------------------------------------------------------
            */
            $table->date('erp_lastchange')->nullable()->index();
            $table->dateTime('erp_last_seen_at')->nullable()->index();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Constraints
            |--------------------------------------------------------------------------
            */
            $table->unique(
                ['ditta_cg18', 'listino_id', 'sku', 'qty_from'],
                'uq_price_tiers_key'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index(
                ['ditta_cg18', 'listino_id', 'sku'],
                'ix_price_tiers_lookup'
            );

            $table->index(
                ['ditta_cg18', 'listino_id', 'sku', 'qty_from'],
                'ix_price_tiers_qty_lookup'
            );

            $table->index(
                ['sku', 'qty_from', 'qty_to'],
                'ix_price_tiers_qty_range'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_tiers');
    }
};