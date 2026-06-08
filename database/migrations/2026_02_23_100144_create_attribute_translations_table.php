<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attribute_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attribute_id')
                ->constrained('attributes')
                ->cascadeOnDelete();

            $table->string('locale', 5)->index(); // it/en/es
            $table->string('label', 255);
            $table->string('help_text', 255)->nullable();

            $table->unique(['attribute_id', 'locale'], 'uq_attrtr_attr_locale');
            $table->index(['attribute_id', 'locale'], 'ix_attrtr_attr_locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_translations');
    }
};