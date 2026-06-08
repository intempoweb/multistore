<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // Store / canale (B2B/B2C)
            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->nullOnDelete();

            // Derivabile da store->is_b2b (manteniamo per snapshot/debug)
            $table->string('channel')->default('b2b'); // b2b | b2c

            // Token carrello (per guest / API / frontend)
            $table->string('cart_token')->unique();

            // Contesto ERP / multistore
            $table->unsignedInteger('ditta_cg18');
            $table->unsignedInteger('site_type')->nullable();
            // Snapshot store info (ridondante ma utile per storico)
            $table->string('store_code')->nullable();
            $table->boolean('is_b2b')->nullable();

            // Customer collegato (nullable per guest futuro, ma ora B2B)
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            // Sessione (utile per minicart anonima o fallback)
            $table->string('session_id')->nullable();

            // Scadenza carrello (utile per cleanup e guest)
            $table->timestamp('expires_at')->nullable();

            // Stato carrello
            $table->string('status')->default('active'); // active, converted, abandoned

            // Snapshot dati cliente (per sicurezza / storico)
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->unsignedInteger('customer_clifor_cg44')->nullable();

            // Snapshot indirizzo spedizione selezionato
            $table->foreignId('shipping_address_id')
                ->nullable()
                ->constrained('customer_shipping_addresses')
                ->nullOnDelete();
            // Snapshot indirizzo (denormalizzato per sicurezza storico)
            $table->string('shipping_name')->nullable();
            $table->string('shipping_address')->nullable();
            $table->string('shipping_zip')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_province')->nullable();
            $table->string('shipping_country')->nullable();

            // Totali (calcolati lato service, NON ERP definitivo)
            $table->decimal('subtotal', 15, 3)->nullable();   // somma righe
            $table->decimal('discount_total', 15, 3)->nullable();
            $table->decimal('shipping_total', 15, 3)->nullable();
            $table->decimal('tax_total', 15, 3)->nullable();
            $table->decimal('grand_total', 15, 3)->nullable();

            // Valuta (utile se multi-country in futuro)
            $table->string('currency', 3)->default('EUR');

            // Note cliente
            $table->text('notes')->nullable();

            // Metadata extra (promo, coupon, flags FE)
            $table->json('meta')->nullable();

            $table->timestamps();

            // Index utili
            $table->index(['ditta_cg18']);
            $table->index(['site_type']);
            $table->index(['store_id']);
            $table->index(['status']);
            $table->index(['channel']);
            $table->index(['customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};