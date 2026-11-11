<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHasK3SamplerCertificationToMasterKaryawan extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('master_karyawan') || Schema::hasColumn('master_karyawan', 'has_k3_sampler_certification')) {
            return;
        }

        Schema::table('master_karyawan', function (Blueprint $table) {
            $table->boolean('has_k3_sampler_certification')->default(false);
        });
    }

    public function down()
    {
        // Non-destructive: kolom data karyawan tidak dihapus otomatis.
    }
}
