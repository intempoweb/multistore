<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Prezzo pubblico finale B2C da ERP LISTARTIC_TOT
            // Per ditta 1 arriva dal listino 31
            // Il valore che userai sul frontend è PREZZONETTO
            $table->decimal('public_price', 18, 2)->nullable()->after('erp_lastchange');

            // Solo tracciamento sorgente ERP
            $table->unsignedInteger('public_price_listino_id')->nullable()->after('public_price');

            // Valore ERP lordo originale, utile per debug/confronti
            $table->decimal('public_price_gross', 18, 6)->nullable()->after('public_price_listino_id');

            // Ultima modifica ERP della riga prezzo pubblico
            $table->date('public_price_lastchange')->nullable()->after('public_price_gross');
            $table->index('public_price_lastchange');

            // Ultima volta che il prezzo pubblico è stato visto in sync
            $table->dateTime('public_price_last_seen_at')->nullable()->after('public_price_lastchange');
            $table->index('public_price_last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['public_price_lastchange']);
            $table->dropIndex(['public_price_last_seen_at']);

            $table->dropColumn([
                'public_price',
                'public_price_listino_id',
                'public_price_gross',
                'public_price_lastchange',
                'public_price_last_seen_at',
            ]);
        });
    }
};