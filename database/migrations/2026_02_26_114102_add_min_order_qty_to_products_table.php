<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // minimo acquistabile (ERP: CONFMINACQ_WEBT01)
            $table->unsignedInteger('min_order_qty')
                ->default(1)
                ->after('unit')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('min_order_qty');
        });
    }
};