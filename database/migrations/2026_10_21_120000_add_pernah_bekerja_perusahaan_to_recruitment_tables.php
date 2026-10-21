<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPernahBekerjaPerusahaanToRecruitmentTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('new_recruitment') && !Schema::hasColumn('new_recruitment', 'pernah_bekerja_perusahaan')) {
            Schema::table('new_recruitment', function (Blueprint $table) {
                $table->json('pernah_bekerja_perusahaan')->nullable()->after('masalah_hukum');
            });
        }

        if (Schema::hasTable('master_karyawan') && !Schema::hasColumn('master_karyawan', 'pernah_bekerja_perusahaan')) {
            Schema::table('master_karyawan', function (Blueprint $table) {
                $table->json('pernah_bekerja_perusahaan')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('new_recruitment') && Schema::hasColumn('new_recruitment', 'pernah_bekerja_perusahaan')) {
            Schema::table('new_recruitment', function (Blueprint $table) {
                $table->dropColumn('pernah_bekerja_perusahaan');
            });
        }

        if (Schema::hasTable('master_karyawan') && Schema::hasColumn('master_karyawan', 'pernah_bekerja_perusahaan')) {
            Schema::table('master_karyawan', function (Blueprint $table) {
                $table->dropColumn('pernah_bekerja_perusahaan');
            });
        }
    }
}
