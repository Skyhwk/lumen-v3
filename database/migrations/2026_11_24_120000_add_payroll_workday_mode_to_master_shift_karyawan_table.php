<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('master_shift_karyawan')) {
            return;
        }

        Schema::table('master_shift_karyawan', function (Blueprint $table) {
            if (!Schema::hasColumn('master_shift_karyawan', 'payroll_workday_mode')) {
                $table->string('payroll_workday_mode', 30)
                    ->default('calendar')
                    ->after('sort_order');
            }
        });

        DB::table('master_shift_karyawan')
            ->whereIn('kode', ['SHSECURITY', 'SHSECURITY2', 'SHSECURITYGO1', 'SHSECURITYGO2'])
            ->update(['payroll_workday_mode' => 'per_shift']);

        DB::table('master_shift_karyawan')
            ->whereIn('kode', ['SHOB', 'SHOB2'])
            ->update(['payroll_workday_mode' => 'masuk_per_shift']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('master_shift_karyawan')) {
            return;
        }

        Schema::table('master_shift_karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('master_shift_karyawan', 'payroll_workday_mode')) {
                $table->dropColumn('payroll_workday_mode');
            }
        });
    }
};
