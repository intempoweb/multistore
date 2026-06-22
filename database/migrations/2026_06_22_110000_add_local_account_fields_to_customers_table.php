<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained('stores')->nullOnDelete();
            $table->string('account_origin', 20)->default('erp')->after('store_id');
            $table->unsignedTinyInteger('tipocf_cg44')->nullable()->change();
            $table->unsignedInteger('clifor_cg44')->nullable()->change();
            $table->unique(['store_id', 'indemail_cg16'], 'uq_customers_store_email');
            $table->index(['store_id', 'account_origin', 'is_active'], 'ix_customers_local_account');
        });
    }

    public function down(): void
    {
        DB::table('customers')
            ->where('account_origin', 'storefront')
            ->whereNull('clifor_cg44')
            ->orderBy('id')
            ->each(function ($customer) {
                DB::table('customers')->where('id', $customer->id)->update([
                    'tipocf_cg44' => 0,
                    'clifor_cg44' => 3000000000 + (int) $customer->id,
                ]);
            });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('uq_customers_store_email');
            $table->dropIndex('ix_customers_local_account');
            $table->dropConstrainedForeignId('store_id');
            $table->dropColumn('account_origin');
            $table->unsignedTinyInteger('tipocf_cg44')->nullable(false)->change();
            $table->unsignedInteger('clifor_cg44')->nullable(false)->change();
        });
    }
};
