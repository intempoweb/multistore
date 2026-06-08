<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attribute_id')
                ->constrained('attributes')
                ->cascadeOnDelete();

            // value_code ERP/tecnico (es: "26", "99", "GIALLO"... dipende da ERP)
            $table->string('value_code', 64);

            $table->unsignedInteger('sort_order')->default(0)->index();

            $table->dateTime('erp_lastchange')->nullable()->index();

            $table->timestamps();

            // ✅ unico per attributo (globale)
            $table->unique(['attribute_id', 'value_code'], 'uq_attrvalues_attr_valuecode');
            $table->index(['attribute_id', 'sort_order'], 'ix_attrvalues_attr_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
    }
};