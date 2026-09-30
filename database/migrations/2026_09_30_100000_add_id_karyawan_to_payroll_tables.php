<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel payroll yang ditambahkan kolom id_karyawan.
     *
     * @var array<string, string> table => index name
     */
    private array $payrollTables = [
        'rekening_karyawan' => 'idx_rekening_karyawan_id_karyawan',
        'master_sallary' => 'idx_master_sallary_id_karyawan',
        'bpjs_tk' => 'idx_bpjs_tk_id_karyawan',
        'bpjs_kesehatan' => 'idx_bpjs_kesehatan_id_karyawan',
        'bonus_karyawan' => 'idx_bonus_karyawan_id_karyawan',
        'pencadangan_upah' => 'idx_pencadangan_upah_id_karyawan',
        'kasbon' => 'idx_kasbon_id_karyawan',
        'denda_karyawan' => 'idx_denda_karyawan_id_karyawan',
        'fee_karyawan' => 'idx_fee_karyawan_id_karyawan',
    ];

    public function up(): void
    {
        foreach ($this->payrollTables as $tableName => $indexName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexName) {
                if (!Schema::hasColumn($tableName, 'id_karyawan')) {
                    $table->unsignedInteger('id_karyawan')->nullable()->after('nik_karyawan');
                    $table->index('id_karyawan', $indexName);
                }
            });
        }

        if (
            !Schema::hasTable('master_karyawan')
            || !Schema::hasColumn('master_karyawan', 'id')
            || !Schema::hasColumn('master_karyawan', 'nik_karyawan')
        ) {
            return;
        }

        foreach (array_keys($this->payrollTables) as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'id_karyawan')) {
                continue;
            }

            DB::table("{$tableName} as payroll")
                ->join('master_karyawan as mk', 'mk.nik_karyawan', '=', 'payroll.nik_karyawan')
                ->whereNull('payroll.id_karyawan')
                ->update(['payroll.id_karyawan' => DB::raw('mk.id')]);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->payrollTables, true) as $tableName => $indexName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexName) {
                if (Schema::hasColumn($tableName, 'id_karyawan')) {
                    $table->dropIndex($indexName);
                    $table->dropColumn('id_karyawan');
                }
            });
        }
    }
};
