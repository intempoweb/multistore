<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attribute_value_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attribute_value_id')
                ->constrained('attribute_values')
                ->cascadeOnDelete();

            $table->string('locale', 5)->index();
            $table->string('label', 255);

            $table->unique(['attribute_value_id', 'locale'], 'uq_attrvaltr_val_locale');
            $table->index(['attribute_value_id', 'locale'], 'ix_attrvaltr_val_locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_value_translations');
    }
};