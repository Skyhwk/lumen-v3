<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJabatanToPayrollTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('payroll')) {
            Schema::table('payroll', function (Blueprint $table) {
                if (!Schema::hasColumn('payroll', 'id_jabatan')) {
                    $table->integer('id_jabatan')->nullable()->after('status_karyawan');
                }
                if (!Schema::hasColumn('payroll', 'nama_jabatan')) {
                    $table->string('nama_jabatan', 150)->nullable()->after('id_jabatan');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('payroll')) {
            Schema::table('payroll', function (Blueprint $table) {
                if (Schema::hasColumn('payroll', 'id_jabatan')) {
                    $table->dropColumn('id_jabatan');
                }
                if (Schema::hasColumn('payroll', 'nama_jabatan')) {
                    $table->dropColumn('nama_jabatan');
                }
            });
        }
    }
}
