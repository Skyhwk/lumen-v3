<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_health_checks') || !Schema::hasColumn('employee_health_checks', 'stetoskop')) {
            return;
        }

        DB::statement(
            "ALTER TABLE employee_health_checks MODIFY stetoskop ENUM('normal', 'ada_kelainan') NULL"
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_health_checks') || !Schema::hasColumn('employee_health_checks', 'stetoskop')) {
            return;
        }

        DB::statement(
            "UPDATE employee_health_checks SET stetoskop = 'normal' WHERE stetoskop IS NULL"
        );
        DB::statement(
            "ALTER TABLE employee_health_checks MODIFY stetoskop ENUM('normal', 'ada_kelainan') NOT NULL"
        );
    }
};
