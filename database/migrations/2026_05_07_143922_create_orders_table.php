<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('channel')->default('b2b'); // b2b | b2c

            $table->unsignedInteger('ditta_cg18');
            $table->unsignedInteger('site_type')->nullable();

            $table->foreignId('cart_id')->nullable()->constrained('carts')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->unsignedInteger('customer_tipocf_cg44')->nullable();
            $table->unsignedInteger('customer_clifor_cg44')->nullable();

            // Numero ordine nuovo sito: SOLO NUMERICO, salvato stringa per evitare limiti integer.
            // Deve stare fuori dai progressivi Magento/ERP.
            $table->string('order_number', 32)->unique();

            // Riferimenti Magento legacy / ERP web
            $table->string('legacy_magento_order_number', 32)->nullable();
            $table->unsignedBigInteger('erp_web_id')->nullable(); // WDO11_IDWEB
            $table->string('erp_web_numreg', 64)->nullable(); // WDO11_NUMREG_MAGE / WDO30_NUMREG_MAGE_WDO11

            // Documento ERP finale
            $table->string('erp_document_number', 64)->nullable(); // NUMREG_CO99
            $table->string('erp_document_visible_number', 64)->nullable(); // NUMSEZDOC_DO11 es. 69 / 3M

            $table->string('status')->default('draft');
            $table->string('payment_status')->default('pending');
            $table->string('fulfillment_status')->default('pending');

            $table->string('source')->default('storefront');
            $table->string('currency', 3)->default('EUR');

            // Fattura / ERP export
            $table->boolean('invoice_required')->default(false);
            $table->string('invoice_status')->default('not_required'); // not_required|required|exported|failed
            $table->string('erp_export_status')->default('pending'); // pending|exported|failed|skipped
            $table->text('erp_export_error')->nullable();

            $table->string('customer_code')->nullable();
            $table->string('customer_company_name')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_vat_number')->nullable();
            $table->string('customer_tax_code')->nullable();

            $table->string('billing_company')->nullable();
            $table->string('billing_first_name')->nullable();
            $table->string('billing_last_name')->nullable();
            $table->string('billing_address_line_1')->nullable();
            $table->string('billing_address_line_2')->nullable();
            $table->string('billing_postcode')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_province')->nullable();
            $table->string('billing_country_code', 3)->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('billing_email')->nullable();

            $table->foreignId('customer_shipping_address_id')
                ->nullable()
                ->constrained('customer_shipping_addresses')
                ->nullOnDelete();

            $table->unsignedInteger('shipping_address_code')->nullable();
            $table->string('shipping_company')->nullable();
            $table->string('shipping_contact_name')->nullable();
            $table->string('shipping_first_name')->nullable();
            $table->string('shipping_last_name')->nullable();
            $table->string('shipping_address_line_1')->nullable();
            $table->string('shipping_address_line_2')->nullable();
            $table->string('shipping_postcode')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_province')->nullable();
            $table->string('shipping_country_code', 3)->nullable();
            $table->string('shipping_phone')->nullable();
            $table->string('shipping_email')->nullable();

            $table->string('payment_method_code')->nullable();
            $table->string('payment_method_label')->nullable();
            $table->unsignedInteger('payment_terms_code')->nullable();
            $table->string('payment_terms_label')->nullable();
            $table->decimal('payment_vat_percent', 8, 2)->nullable();

            $table->unsignedInteger('bank_abi')->nullable();
            $table->unsignedInteger('bank_cab')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_iban')->nullable();

            $table->string('payment_gateway')->nullable(); // stripe|paypal|manual
            $table->string('payment_transaction_id')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->string('shipping_method_code')->nullable();
            $table->string('shipping_method_label')->nullable();

            // B2C Sendcloud
            $table->string('shipping_gateway')->nullable(); // sendcloud
            $table->string('shipping_carrier')->nullable(); // brt|dhl
            $table->string('shipping_service_code')->nullable();
            $table->string('shipping_tracking_number')->nullable();
            $table->string('shipping_label_url')->nullable();
            $table->timestamp('shipping_label_created_at')->nullable();

            $table->string('coupon_code')->nullable();

            $table->decimal('subtotal', 15, 3)->default(0);
            $table->decimal('discount_total', 15, 3)->default(0);
            $table->decimal('shipping_total', 15, 3)->default(0);
            $table->decimal('tax_total', 15, 3)->default(0);
            $table->decimal('grand_total', 15, 3)->default(0);

            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamp('erp_exported_at')->nullable();
            $table->timestamp('erp_synced_at')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id']);
            $table->index(['cart_id']);
            $table->index(['customer_id']);
            $table->index(['ditta_cg18', 'site_type']);
            $table->index(['channel']);
            $table->index(['customer_tipocf_cg44', 'customer_clifor_cg44']);
            $table->index(['legacy_magento_order_number']);
            $table->index(['erp_web_id']);
            $table->index(['erp_web_numreg']);
            $table->index(['erp_document_number']);
            $table->index(['erp_document_visible_number']);
            $table->index(['status']);
            $table->index(['payment_status']);
            $table->index(['fulfillment_status']);
            $table->index(['invoice_required']);
            $table->index(['invoice_status']);
            $table->index(['erp_export_status']);
            $table->index(['placed_at']);
            $table->index(['paid_at']);
            $table->index(['payment_gateway']);
            $table->index(['payment_transaction_id']);
            $table->index(['shipping_gateway']);
            $table->index(['shipping_carrier']);
            $table->index(['shipping_tracking_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};