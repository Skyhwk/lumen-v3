<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'salary_adjustment_kpi_criteria',
        'salary_adjustment_requests',
        'salary_adjustment_kpi',
        'salary_adjustment_kpi_items',
        'salary_adjustment_status_logs',
        'salary_adjustment_assessments',
        'salary_adjustment_assessment_sessions',
        'salary_adjustment_counselings',
        'salary_adjustment_approval_tokens',
    ];

    public function up(): void
    {
        $sourceDb = config('database.connections.intilab_apps.database', 'intilab_apps');
        $targetDb = config('database.connections.mysql.database');

        if (!$sourceDb || !$targetDb || $sourceDb === $targetDb) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (!Schema::connection('intilab_apps')->hasTable($table)) {
                continue;
            }

            if (!Schema::connection('mysql')->hasTable($table)) {
                DB::statement("CREATE TABLE `{$targetDb}`.`{$table}` LIKE `{$sourceDb}`.`{$table}`");
            }

            if (DB::connection('mysql')->table($table)->count() === 0) {
                DB::statement("INSERT INTO `{$targetDb}`.`{$table}` SELECT * FROM `{$sourceDb}`.`{$table}`");
            }

            Schema::connection('intilab_apps')->dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Tidak di-reverse otomatis untuk menghindari kehilangan data.
    }
};
