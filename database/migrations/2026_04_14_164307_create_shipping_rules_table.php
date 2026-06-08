<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();

            // contesto ERP fallback
            $table->unsignedInteger('ditta_cg18')->nullable()->index();
            $table->unsignedInteger('erp_site_code')->nullable()->index();

            /*
            |--------------------------------------------------------------------------
            | Tipo regola
            |--------------------------------------------------------------------------
            | fixed       => costo fisso
            | free_over   => gratis sopra soglia importo
            | table       => table rate B2C da CSV
            */
            $table->string('type', 20)->index();

            /*
            |--------------------------------------------------------------------------
            | Regole economiche (B2B / generiche)
            |--------------------------------------------------------------------------
            */
            $table->decimal('min_amount', 12, 3)->nullable();
            $table->decimal('max_amount', 12, 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Regole geografiche / table rate (B2C)
            |--------------------------------------------------------------------------
            | CSV: Nazione | Provincia | CAP | Peso (e superiore) | Prezzo di spedizione
            */
            $table->string('country', 3)->nullable()->index();          // es. IT
            $table->string('province', 10)->nullable()->index();        // es. MI
            $table->string('cap', 20)->nullable()->index();             // es. 20100, 201*
            $table->decimal('weight_from', 12, 3)->nullable();          // soglia peso minima

            /*
            |--------------------------------------------------------------------------
            | Valore regola
            |--------------------------------------------------------------------------
            */
            $table->decimal('amount', 12, 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Ordinamento / stato
            |--------------------------------------------------------------------------
            */
            $table->integer('priority')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indici utili
            |--------------------------------------------------------------------------
            */
            $table->index(
                ['store_id', 'type', 'is_active', 'priority'],
                'shipping_rules_store_type_active_priority_idx'
            );

            $table->index(
                ['ditta_cg18', 'erp_site_code', 'type', 'is_active'],
                'shipping_rules_erp_context_type_active_idx'
            );

            $table->index(
                ['store_id', 'country', 'province', 'cap'],
                'shipping_rules_store_geo_idx'
            );

            $table->index(
                ['store_id', 'type', 'country', 'province', 'cap', 'weight_from'],
                'shipping_rules_store_table_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rules');
    }
};