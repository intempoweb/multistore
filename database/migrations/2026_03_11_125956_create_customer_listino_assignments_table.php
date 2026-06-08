<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_listino_assignments', function (Blueprint $table) {
            $table->id();

            // ERP keys from LISTINO_ASSOCCLI
            $table->unsignedSmallInteger('ditta_cg18');
            $table->unsignedInteger('clifor_cg44');
            $table->unsignedInteger('listino_id');

            // local sync flags
            $table->boolean('is_active')->default(true);
            $table->dateTime('erp_last_seen_at')->nullable();

            $table->timestamps();

            // unique logical key
            $table->unique(
                ['ditta_cg18', 'clifor_cg44', 'listino_id'],
                'uq_customer_listino_assignments'
            );

            // indexes
            $table->index(
                ['ditta_cg18', 'clifor_cg44'],
                'ix_customer_listino_customer'
            );

            $table->index(
                ['ditta_cg18', 'listino_id'],
                'ix_customer_listino_listino'
            );

            $table->index(
                ['ditta_cg18', 'is_active'],
                'ix_customer_listino_active_by_ditta'
            );

            $table->index(
                'erp_last_seen_at',
                'ix_customer_listino_last_seen'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_listino_assignments');
    }
};