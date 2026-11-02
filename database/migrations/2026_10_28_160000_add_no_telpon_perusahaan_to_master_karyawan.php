<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNoTelponPerusahaanToMasterKaryawan extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('master_karyawan') || Schema::hasColumn('master_karyawan', 'no_telpon_perusahaan')) {
            return;
        }

        Schema::table('master_karyawan', function (Blueprint $table) {
            $column = $table->string('no_telpon_perusahaan', 30)->nullable();
            if (Schema::hasColumn('master_karyawan', 'no_telpon')) {
                $column->after('no_telpon');
            }
        });
    }

    public function down()
    {
        // Non-destructive: kolom data karyawan tidak dihapus otomatis.
    }
}
