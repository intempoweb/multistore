<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Container Google Tag Manager dedicato allo store (es: GTM-XXXXXXX)
            $table->string('gtm_container_id', 32)->nullable()->after('supported_locales');

            // Codice del meta tag google-site-verification per Google Search Console
            $table->string('gsc_verification_code', 255)->nullable()->after('gtm_container_id');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['gtm_container_id', 'gsc_verification_code']);
        });
    }
};
