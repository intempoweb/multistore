<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_kit_requests', function (Blueprint $table) {
            $table->string('email_to', 255)->nullable()->after('error_message');
            $table->string('email_status', 30)->default('not_sent')->index()->after('email_to');
            $table->timestamp('email_sent_at')->nullable()->after('email_status');
            $table->text('email_error_message')->nullable()->after('email_sent_at');
            $table->unsignedInteger('email_attempts')->default(0)->after('email_error_message');
        });
    }

    public function down(): void
    {
        Schema::table('media_kit_requests', function (Blueprint $table) {
            $table->dropIndex(['email_status']);
            $table->dropColumn([
                'email_to',
                'email_status',
                'email_sent_at',
                'email_error_message',
                'email_attempts',
            ]);
        });
    }
};
