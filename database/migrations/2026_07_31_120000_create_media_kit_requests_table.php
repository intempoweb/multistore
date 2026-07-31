<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_kit_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();

            $table->string('actor_type', 100)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->index(['actor_type', 'actor_id']);

            $table->string('source_type', 40)->index();
            $table->string('source_reference', 191)->nullable()->index();

            $table->string('status', 30)->default('queued')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('product_count')->default(0);
            $table->unsignedInteger('asset_count')->default(0);

            $table->string('input_disk', 60)->nullable();
            $table->text('input_path')->nullable();

            $table->string('output_disk', 60)->nullable();
            $table->text('output_path')->nullable();
            $table->string('output_filename', 255)->nullable();
            $table->unsignedBigInteger('output_size')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('delete_after')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('delete_reason', 40)->nullable();

            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['deleted_at', 'delete_after']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_kit_requests');
    }
};
