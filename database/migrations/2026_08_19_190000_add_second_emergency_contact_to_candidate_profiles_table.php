<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSecondEmergencyContactToCandidateProfilesTable extends Migration
{
    public function up()
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('candidate_profiles', 'nama_kontak_darurat_2')) {
                $table->string('nama_kontak_darurat_2', 150)->nullable()->after('no_telepon_darurat');
            }
            if (!Schema::hasColumn('candidate_profiles', 'hubungan_kontak_darurat_2')) {
                $table->string('hubungan_kontak_darurat_2', 100)->nullable()->after('nama_kontak_darurat_2');
            }
            if (!Schema::hasColumn('candidate_profiles', 'no_telepon_darurat_2')) {
                $table->string('no_telepon_darurat_2', 50)->nullable()->after('hubungan_kontak_darurat_2');
            }
        });
    }

    public function down()
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            foreach (['no_telepon_darurat_2', 'hubungan_kontak_darurat_2', 'nama_kontak_darurat_2'] as $column) {
                if (Schema::hasColumn('candidate_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
