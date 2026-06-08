<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('base_price', 15, 3)->nullable()->after('price_gross');
            $table->decimal('base_row_total', 15, 3)->nullable()->after('base_price');
            $table->decimal('web_discount_total', 15, 3)->default(0)->after('base_row_total');
            $table->decimal('final_price', 15, 3)->nullable()->after('web_discount_total');
            $table->decimal('final_row_total', 15, 3)->nullable()->after('final_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'base_price',
                'base_row_total',
                'web_discount_total',
                'final_price',
                'final_row_total',
            ]);
        });
    }
};
