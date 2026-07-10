<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_page_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('storefront_page_id');
            $table->unsignedBigInteger('store_id');
            $table->string('locale', 5);
            $table->string('slug', 120)->nullable();
            $table->string('title', 190)->nullable();
            $table->text('description')->nullable();
            $table->string('meta_title', 190)->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->unique(['storefront_page_id', 'locale'], 'uq_sf_page_tr_page_locale');
            $table->unique(['store_id', 'locale', 'slug'], 'uq_sf_page_tr_store_locale_slug');
            $table->index('locale', 'ix_sf_page_tr_locale');
            $table->foreign('storefront_page_id', 'fk_sf_page_tr_page')
                ->references('id')
                ->on('storefront_pages')
                ->cascadeOnDelete();
            $table->foreign('store_id', 'fk_sf_page_tr_store')
                ->references('id')
                ->on('stores')
                ->cascadeOnDelete();
        });

        Schema::create('storefront_page_block_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('storefront_page_block_id');
            $table->string('locale', 5);
            $table->string('title', 190)->nullable();
            $table->string('subtitle', 255)->nullable();
            $table->text('content')->nullable();
            $table->string('button_label', 120)->nullable();
            $table->timestamps();

            $table->unique(['storefront_page_block_id', 'locale'], 'uq_sf_block_tr_block_locale');
            $table->index('locale', 'ix_sf_block_tr_locale');
            $table->foreign('storefront_page_block_id', 'fk_sf_block_tr_block')
                ->references('id')
                ->on('storefront_page_blocks')
                ->cascadeOnDelete();
        });

        $now = now();

        DB::table('storefront_pages')
            ->orderBy('id')
            ->get()
            ->each(function ($page) use ($now) {
                DB::table('storefront_page_translations')->updateOrInsert(
                    [
                        'storefront_page_id' => $page->id,
                        'locale' => 'it',
                    ],
                    [
                        'store_id' => $page->store_id,
                        'slug' => $page->slug,
                        'title' => $page->title,
                        'description' => $page->description ?? null,
                        'meta_title' => $page->meta_title ?? null,
                        'meta_description' => $page->meta_description ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            });

        DB::table('storefront_page_blocks')
            ->orderBy('id')
            ->get()
            ->each(function ($block) use ($now) {
                DB::table('storefront_page_block_translations')->updateOrInsert(
                    [
                        'storefront_page_block_id' => $block->id,
                        'locale' => 'it',
                    ],
                    [
                        'title' => $block->title ?? null,
                        'subtitle' => $block->subtitle ?? null,
                        'content' => $block->content ?? null,
                        'button_label' => $block->button_label ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_page_block_translations');
        Schema::dropIfExists('storefront_page_translations');
    }
};
