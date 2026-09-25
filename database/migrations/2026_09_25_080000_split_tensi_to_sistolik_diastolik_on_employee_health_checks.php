<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_health_checks')) {
            return;
        }

        if (Schema::hasColumn('employee_health_checks', 'tensi_sistolik')) {
            return;
        }

        Schema::table('employee_health_checks', function (Blueprint $table) {
            $table->unsignedSmallInteger('tensi_sistolik')->default(0)->after('check_time');
            $table->unsignedSmallInteger('tensi_diastolik')->default(0)->after('tensi_sistolik');
        });

        if (Schema::hasColumn('employee_health_checks', 'tensi')) {
            DB::table('employee_health_checks')->update([
                'tensi_sistolik' => DB::raw('tensi'),
                'tensi_diastolik' => 80,
            ]);

            Schema::table('employee_health_checks', function (Blueprint $table) {
                $table->dropColumn('tensi');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_health_checks')) {
            return;
        }

        if (!Schema::hasColumn('employee_health_checks', 'tensi_sistolik')) {
            return;
        }

        Schema::table('employee_health_checks', function (Blueprint $table) {
            $table->unsignedSmallInteger('tensi')->default(0)->after('check_time');
        });

        DB::table('employee_health_checks')->update([
            'tensi' => DB::raw('tensi_sistolik'),
        ]);

        Schema::table('employee_health_checks', function (Blueprint $table) {
            $table->dropColumn(['tensi_sistolik', 'tensi_diastolik']);
        });
    }
};
