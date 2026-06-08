<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();

            /**
             * ⚠️ NON usare $table->morphs('mediable')
             * perché crea mediable_type(255) e poi la UNIQUE sfora.
             */
            $table->string('mediable_type', 120);
            $table->unsignedBigInteger('mediable_id');
            $table->index(['mediable_type', 'mediable_id'], 'ix_media_mediable');

            $table->unsignedSmallInteger('ditta_cg18')->index();

            // 0 = globale (swatch), 1/2/... = sito
            $table->unsignedSmallInteger('site_type')->default(0)->index();

            $table->string('role', 30)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();

            $table->string('erp_path', 255)->nullable();

            // ✅ ridotti per stare dentro ai limiti index (utf8mb4)
            $table->string('filename', 191);

            $table->string('local_path', 500)->nullable();

            // ✅ NOT NULL + default '' per UNIQUE che funzioni sempre
            $table->string('meta_key', 40)->default('')->index();
            $table->string('meta_value', 191)->default('');

            $table->dateTime('erp_lastchange')->nullable()->index();

            $table->timestamps();

            // ✅ ora la UNIQUE rientra nei 3072 bytes
            $table->unique(
                ['mediable_type', 'mediable_id', 'role', 'filename', 'meta_key', 'meta_value'],
                'uq_media_asset_identity'
            );

            $table->index(['role', 'filename'], 'ix_media_role_filename');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};