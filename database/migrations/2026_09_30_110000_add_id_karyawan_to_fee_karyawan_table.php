<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fee_karyawan')) {
            return;
        }

        if (!Schema::hasColumn('fee_karyawan', 'id_karyawan')) {
            Schema::table('fee_karyawan', function (Blueprint $table) {
                $table->unsignedInteger('id_karyawan')->nullable()->after('nik_karyawan');
                $table->index('id_karyawan', 'idx_fee_karyawan_id_karyawan');
            });
        }

        if (
            Schema::hasColumn('fee_karyawan', 'id_karyawan')
            && Schema::hasTable('master_karyawan')
            && Schema::hasColumn('master_karyawan', 'id')
            && Schema::hasColumn('master_karyawan', 'nik_karyawan')
        ) {
            DB::table('fee_karyawan as payroll')
                ->join('master_karyawan as mk', 'mk.nik_karyawan', '=', 'payroll.nik_karyawan')
                ->whereNull('payroll.id_karyawan')
                ->update(['payroll.id_karyawan' => DB::raw('mk.id')]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('fee_karyawan') || !Schema::hasColumn('fee_karyawan', 'id_karyawan')) {
            return;
        }

        Schema::table('fee_karyawan', function (Blueprint $table) {
            $table->dropIndex('idx_fee_karyawan_id_karyawan');
            $table->dropColumn('id_karyawan');
        });
    }
};
