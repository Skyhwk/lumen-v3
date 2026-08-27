<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNikKkToMasterKaryawan extends Migration
{
    public function up()
    {
        if (Schema::hasTable('master_karyawan') && !Schema::hasColumn('master_karyawan', 'nik_kk')) {
            Schema::table('master_karyawan', function (Blueprint $table) {
                $table->string('nik_kk', 30)->nullable()->after('nik_ktp');
            });
        }
    }

    public function down()
    {
        // Non-destructive: kolom data karyawan tidak dihapus otomatis.
    }
}
