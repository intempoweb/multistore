<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_popup_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_popup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['storefront_popup_id', 'store_id'], 'storefront_popup_store_unique');
            $table->index('store_id');
        });

        DB::table('storefront_popups')
            ->select(['id', 'store_id'])
            ->orderBy('id')
            ->get()
            ->each(function ($popup): void {
                DB::table('storefront_popup_store')->insert([
                    'storefront_popup_id' => $popup->id,
                    'store_id' => $popup->store_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_popup_store');
    }
};
