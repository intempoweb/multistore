<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();

            // codice tecnico (A09, A07, A02, ecc.) -> GLOBALE
            $table->string('code', 64);

            $table->enum('type', ['select', 'text', 'number', 'boolean'])
                ->default('select')
                ->index();

            $table->boolean('is_filterable')->default(false)->index();
            $table->boolean('is_variant')->default(false)->index();

            $table->unsignedInteger('sort_order')->default(0)->index();

            $table->dateTime('erp_lastchange')->nullable()->index();

            $table->timestamps();

            // ✅ unico globale
            $table->unique(['code'], 'uq_attributes_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};