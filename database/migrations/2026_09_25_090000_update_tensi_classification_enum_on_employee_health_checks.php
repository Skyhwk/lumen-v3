<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_health_checks')) {
            return;
        }

        DB::statement("
            ALTER TABLE employee_health_checks
            MODIFY tensi_classification ENUM(
                'Rendah',
                'Rata-Rata',
                'Normal',
                'Cukup Tinggi',
                'Normal Tinggi',
                'Tinggi'
            ) NOT NULL
        ");

        DB::table('employee_health_checks')
            ->where('tensi_classification', 'Cukup Tinggi')
            ->update(['tensi_classification' => 'Normal Tinggi']);

        DB::statement("
            ALTER TABLE employee_health_checks
            MODIFY tensi_classification ENUM(
                'Rendah',
                'Rata-Rata',
                'Normal',
                'Normal Tinggi',
                'Tinggi'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_health_checks')) {
            return;
        }

        DB::statement("
            ALTER TABLE employee_health_checks
            MODIFY tensi_classification ENUM(
                'Rendah',
                'Rata-Rata',
                'Normal',
                'Cukup Tinggi',
                'Normal Tinggi',
                'Tinggi'
            ) NOT NULL
        ");

        DB::table('employee_health_checks')
            ->where('tensi_classification', 'Normal Tinggi')
            ->update(['tensi_classification' => 'Cukup Tinggi']);

        DB::statement("
            ALTER TABLE employee_health_checks
            MODIFY tensi_classification ENUM(
                'Rendah',
                'Rata-Rata',
                'Normal',
                'Cukup Tinggi',
                'Tinggi'
            ) NOT NULL
        ");
    }
};
