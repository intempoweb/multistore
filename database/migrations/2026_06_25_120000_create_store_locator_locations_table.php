<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_locator_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->foreignId('customer_shipping_address_id')
                ->nullable()
                ->constrained('customer_shipping_addresses')
                ->cascadeOnDelete();

            $table->string('source_type', 20);
            $table->string('source_key', 80);

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('geocoded_at')->nullable();
            $table->string('geocode_status', 40)->default('pending');
            $table->string('geocode_error', 512)->nullable();
            $table->string('address_fingerprint', 64)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'source_key'], 'uq_store_locator_locations_source');
            $table->index(['store_id', 'is_active'], 'ix_store_locator_store_active');
            $table->index(['customer_id'], 'ix_store_locator_customer');
            $table->index(['customer_shipping_address_id'], 'ix_store_locator_shipping_address');
            $table->index(['latitude', 'longitude'], 'ix_store_locator_coordinates');
            $table->index(['geocode_status'], 'ix_store_locator_geocode_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_locator_locations');
    }
};
