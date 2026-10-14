<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNoNpwpToMasterKaryawan extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('master_karyawan') || Schema::hasColumn('master_karyawan', 'no_npwp')) {
            return;
        }

        Schema::table('master_karyawan', function (Blueprint $table) {
            $column = $table->string('no_npwp', 30)->nullable();
            if (Schema::hasColumn('master_karyawan', 'nik_kk')) {
                $column->after('nik_kk');
            } elseif (Schema::hasColumn('master_karyawan', 'nik_ktp')) {
                $column->after('nik_ktp');
            }
        });
    }

    public function down()
    {
        // Non-destructive: kolom data karyawan tidak dihapus otomatis.
    }
}
