<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Per query visibilità (ditta + site + attivo + gruppo fisico)
            $table->index(
                ['ditta_cg18', 'site_type', 'is_active', 'codgrupfis_mg61'],
                'ix_products_visibility_ctx'
            );

            // Per EXISTS sui figli (parent_code) in contesto
            // (già c'è ix_products_ditta_site_parent nella tua migration products,
            // ma questo aiuta anche quando filtriamo per type + active)
            $table->index(
                ['ditta_cg18', 'site_type', 'parent_code', 'type', 'is_active'],
                'ix_products_parent_visibility'
            );
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('ix_products_visibility_ctx');
            $table->dropIndex('ix_products_parent_visibility');
        });
    }
};