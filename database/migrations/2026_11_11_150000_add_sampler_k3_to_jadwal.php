<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSamplerK3ToJadwal extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('jadwal') || Schema::hasColumn('jadwal', 'sampler_k3')) {
            return;
        }

        Schema::table('jadwal', function (Blueprint $table) {
                $table->string('sampler_k3', 255)->nullable()->after('pendampingan_k3');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('jadwal') || !Schema::hasColumn('jadwal', 'sampler_k3')) {
            return;
        }

        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropColumn('sampler_k3');
        });
    }
}
