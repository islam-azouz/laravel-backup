<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenants') || Schema::hasColumn('tenants', 'restore_state')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('restore_state', 32)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tenants') || !Schema::hasColumn('tenants', 'restore_state')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('restore_state');
        });
    }
};