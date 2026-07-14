<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_returns', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->nullable()->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedInteger('ditta_cg18');
            $table->unsignedInteger('clifor_cg44');
            $table->string('numreg_co99', 64);
            $table->string('document_number')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_date')->nullable();
            $table->string('status', 32)->default('open');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode', 32)->nullable();
            $table->string('province', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['store_id', 'created_at']);
            $table->index(['ditta_cg18', 'clifor_cg44']);
            $table->index(['numreg_co99']);
            $table->index(['status']);
        });

        Schema::create('customer_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_return_id')->constrained('customer_returns')->cascadeOnDelete();
            $table->string('erp_row_number', 64);
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 32)->nullable();
            $table->decimal('document_quantity', 15, 3)->default(0);
            $table->decimal('requested_quantity', 15, 3)->default(0);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_return_id']);
            $table->index(['sku']);
            $table->index(['erp_row_number']);
        });

        Schema::create('customer_support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->nullable()->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedInteger('ditta_cg18');
            $table->unsignedInteger('clifor_cg44');
            $table->string('numreg_co99', 64);
            $table->string('document_number')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_date')->nullable();
            $table->string('status', 32)->default('open');
            $table->string('subject');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode', 32)->nullable();
            $table->string('province', 32)->nullable();
            $table->text('message');
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['store_id', 'created_at']);
            $table->index(['ditta_cg18', 'clifor_cg44']);
            $table->index(['numreg_co99']);
            $table->index(['status']);
        });

        Schema::create('customer_support_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_support_ticket_id')->constrained('customer_support_tickets')->cascadeOnDelete();
            $table->string('erp_row_number', 64);
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 32)->nullable();
            $table->decimal('document_quantity', 15, 3)->default(0);
            $table->timestamps();

            $table->index(['customer_support_ticket_id']);
            $table->index(['sku']);
            $table->index(['erp_row_number']);
        });

        Schema::create('customer_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('request_type', 32);
            $table->unsignedBigInteger('request_id');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->index(['request_type', 'request_id']);
            $table->index(['customer_id']);
            $table->index(['store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_request_attachments');
        Schema::dropIfExists('customer_support_ticket_items');
        Schema::dropIfExists('customer_support_tickets');
        Schema::dropIfExists('customer_return_items');
        Schema::dropIfExists('customer_returns');
    }
};
