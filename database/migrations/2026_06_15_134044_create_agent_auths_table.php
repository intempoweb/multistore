<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_auths', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('ditta_cg18');
            $table->string('indeemail_vwebdcg44', 128);

            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            $table->string('magic_login_token_hash')->nullable();
            $table->timestamp('magic_login_expires_at')->nullable();
            $table->timestamp('magic_login_used_at')->nullable();
            $table->timestamp('last_login_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['ditta_cg18', 'indeemail_vwebdcg44'], 'agent_auths_ditta_email_unique');
            $table->index('indeemail_vwebdcg44', 'agent_auths_email_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_auths');
    }
};