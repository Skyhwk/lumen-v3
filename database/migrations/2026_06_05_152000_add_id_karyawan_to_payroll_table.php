<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdKaryawanToPayrollTable extends Migration
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
                if (!Schema::hasColumn('payroll', 'id_karyawan')) {
                    $table->integer('id_karyawan')->nullable()->after('payroll_header_id');
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
                if (Schema::hasColumn('payroll', 'id_karyawan')) {
                    $table->dropColumn('id_karyawan');
                }
            });
        }
    }
}
