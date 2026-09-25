<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * completed_at untuk auto-arsip task Done > 2 hari.
 *
 * php artisan migrate --path=database/migrations/2026_09_23_170000_add_completed_at_to_pm_tasks.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pm_tasks')) {
            return;
        }
        if (!Schema::hasColumn('pm_tasks', 'completed_at')) {
            Schema::table('pm_tasks', function (Blueprint $table) {
                $table->dateTime('completed_at')->nullable()->after('position')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_tasks') && Schema::hasColumn('pm_tasks', 'completed_at')) {
            Schema::table('pm_tasks', function (Blueprint $table) {
                $table->dropColumn('completed_at');
            });
        }
    }
};
