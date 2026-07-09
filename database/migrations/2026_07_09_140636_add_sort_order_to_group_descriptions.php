<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('group_descriptions', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('gruppo_code');
        });

        // Imposta sort_order per il sito CIAK (ditta=1, site_type=5)
        // Ordine: AGENDE(CKA)=10, TACCUINI(CKT)=20, EDIZIONI SPECIALI(CKS)=30, ACCESSORI(CKC)=99
        $sortMap = ['CKA' => 10, 'CKT' => 20, 'CKS' => 30, 'CKC' => 99];
        foreach ($sortMap as $famCode => $sortOrder) {
            DB::table('group_descriptions')->where('fam_code', $famCode)->update(['sort_order' => $sortOrder]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_descriptions', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
