<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('password')->nullable()->after('indemail_cg16');
            $table->timestamp('email_verified_at')->nullable()->after('password');
            $table->rememberToken();

            $table->string('magic_login_token_hash', 255)->nullable()->after('remember_token');
            $table->dateTime('magic_login_expires_at')->nullable()->after('magic_login_token_hash');
            $table->dateTime('magic_login_used_at')->nullable()->after('magic_login_expires_at');
            $table->dateTime('last_login_at')->nullable()->after('magic_login_used_at');

            $table->index(['ditta_cg18', 'indemail_cg16'], 'ix_customers_ditta_email');
            $table->index('magic_login_expires_at', 'ix_customers_magic_login_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('ix_customers_ditta_email');
            $table->dropIndex('ix_customers_magic_login_expires_at');

            $table->dropColumn([
                'password',
                'email_verified_at',
                'remember_token',
                'magic_login_token_hash',
                'magic_login_expires_at',
                'magic_login_used_at',
                'last_login_at',
            ]);
        });
    }
};