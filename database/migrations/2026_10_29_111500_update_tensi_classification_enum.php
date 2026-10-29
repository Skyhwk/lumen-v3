<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_health_checks') || !Schema::hasColumn('employee_health_checks', 'tensi_classification')) {
            return;
        }

        DB::statement(
            "ALTER TABLE employee_health_checks MODIFY tensi_classification ENUM('Rendah', 'Cukup Rendah', 'Cukup Normal', 'Normal', 'Rata-Rata', 'Tinggi') NOT NULL"
        );

        DB::statement(
            "UPDATE employee_health_checks SET tensi_classification = CASE
                WHEN tensi_sistolik < 91 THEN 'Rendah'
                WHEN tensi_sistolik <= 99 THEN 'Cukup Rendah'
                WHEN tensi_sistolik <= 119 THEN 'Cukup Normal'
                WHEN tensi_sistolik <= 129 THEN 'Normal'
                ELSE 'Tinggi'
            END"
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_health_checks') || !Schema::hasColumn('employee_health_checks', 'tensi_classification')) {
            return;
        }

        DB::statement(
            "UPDATE employee_health_checks SET tensi_classification = CASE
                WHEN tensi_sistolik <= 90 THEN 'Rendah'
                WHEN tensi_sistolik <= 135 THEN 'Rata-Rata'
                ELSE 'Tinggi'
            END"
        );

        DB::statement(
            "ALTER TABLE employee_health_checks MODIFY tensi_classification ENUM('Rendah', 'Rata-Rata', 'Tinggi') NOT NULL"
        );
    }
};
