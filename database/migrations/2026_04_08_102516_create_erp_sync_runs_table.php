<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_sync_runs', function (Blueprint $table) {
            $table->id();

            $table->string('command_key');      // es: customers
            $table->string('command_name');     // es: erp:sync-customers

            $table->string('status')->default('queued'); 
            // queued | running | completed | failed

            $table->json('params_json')->nullable();

            $table->longText('output')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_sync_runs');
    }
};