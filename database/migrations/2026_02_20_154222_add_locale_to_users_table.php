<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make it idempotent (safe if the DB is already partially updated)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('password');
            }

            if (!Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 5)->default('it')->after('is_admin');
            }
        });
    }

    public function down(): void
    {
        // IMPORTANT: do checks OUTSIDE the Schema::table closure to avoid trying to drop non-existing columns
        $drops = [];

        if (Schema::hasColumn('users', 'locale')) {
            $drops[] = 'locale';
        }

        if (Schema::hasColumn('users', 'is_admin')) {
            $drops[] = 'is_admin';
        }

        if (! empty($drops)) {
            Schema::table('users', function (Blueprint $table) use ($drops) {
                $table->dropColumn($drops);
            });
        }
    }
};