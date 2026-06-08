<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->unsignedInteger('ditta_cg18');
            $table->unsignedInteger('site_type')->nullable();

            // Riferimenti ERP WEB
            $table->unsignedBigInteger('erp_web_row_id')->nullable(); // WDO30_IDWEB
            $table->string('erp_web_numreg', 64)->nullable(); // WDO30_NUMREG_MAGE_WDO11
            $table->unsignedInteger('erp_web_row_number')->nullable(); // WDO30_PROGRESRIGA
            $table->unsignedInteger('erp_row_type')->nullable(); // WDO30_INDTIPORIGA

            // Prodotto
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('sku')->nullable();

            $table->string('product_name')->nullable();
            $table->text('product_description')->nullable();
            $table->string('product_thumbnail_url')->nullable();

            $table->json('variant_attributes')->nullable();

            // Quantità
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('min_qty', 15, 3)->nullable();
            $table->decimal('step_qty', 15, 3)->nullable();

            // Prezzo snapshot sito
            $table->string('price_source')->nullable(); // b2b_tier | public | erp
            $table->decimal('price', 15, 6)->nullable();
            $table->decimal('price_net', 15, 6)->nullable();
            $table->decimal('price_gross', 15, 6)->nullable();

            // ERP WEB pricing mapping WDO30
            $table->decimal('erp_price', 15, 6)->nullable(); // WDO30_PREZZO1
            $table->decimal('erp_price_tax', 15, 6)->nullable(); // WDO30_PREZZOIVA
            $table->decimal('erp_price_gross', 15, 6)->nullable(); // WDO30_PREZZOIVATO

            $table->unsignedInteger('listino_id')->nullable();

            $table->decimal('qty_from', 15, 3)->nullable();
            $table->decimal('qty_to', 15, 3)->nullable();

            // Sconti
            $table->decimal('sc1', 8, 3)->nullable();
            $table->decimal('sc2', 8, 3)->nullable();
            $table->decimal('sc3', 8, 3)->nullable();
            $table->decimal('sc4', 8, 3)->nullable();
            $table->decimal('sc5', 8, 3)->nullable();
            $table->decimal('sc6', 8, 3)->nullable();

            // IVA
            $table->decimal('tax_percent', 8, 3)->nullable(); // WDO30_ALIQIVA
            $table->string('tax_code', 20)->nullable(); // WDO30_CODIVA_CG28
            $table->string('tax_label')->nullable(); // WDO30_DESCRIVA

            // Totali riga sito
            $table->decimal('row_subtotal', 15, 3)->default(0);
            $table->decimal('row_discount_total', 15, 3)->default(0);
            $table->decimal('row_tax_total', 15, 3)->default(0);
            $table->decimal('row_total', 15, 3)->default(0);

            // Totali riga ERP WEB
            $table->decimal('erp_row_subtotal', 15, 3)->nullable(); // WDO30_IMPNESCTR
            $table->decimal('erp_row_tax_total', 15, 3)->nullable(); // WDO30_IMPIVATR
            $table->decimal('erp_row_net_total', 15, 3)->nullable(); // WDO30_IMPNESCTOTR
            $table->decimal('erp_row_cash_total', 15, 2)->nullable(); // WDO30_IMPTOTRIGAINCAS

            $table->json('price_payload')->nullable();

            // Giacenza unica SKU: MAGPROQTAUNICA.QGIACATT_MG70
            $table->decimal('stock_qty', 15, 3)->nullable();
            $table->boolean('no_backorder')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['ditta_cg18']);
            $table->index(['site_type']);
            $table->index(['product_id']);
            $table->index(['sku']);
            $table->index(['erp_web_row_id']);
            $table->index(['erp_web_numreg']);
            $table->index(['erp_web_row_number']);
            $table->index(['erp_row_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};