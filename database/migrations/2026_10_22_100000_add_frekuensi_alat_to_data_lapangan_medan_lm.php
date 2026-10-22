<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFrekuensiAlatToDataLapanganMedanLm extends Migration
{
    public function up()
    {
        if (Schema::hasTable('data_lapangan_medan_lm') && !Schema::hasColumn('data_lapangan_medan_lm', 'frekuensi_alat')) {
            Schema::table('data_lapangan_medan_lm', function (Blueprint $table) {
                $table->string('frekuensi_alat', 50)->nullable()->after('frekuensi_100');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('data_lapangan_medan_lm', 'frekuensi_alat')) {
            Schema::table('data_lapangan_medan_lm', function (Blueprint $table) {
                $table->dropColumn('frekuensi_alat');
            });
        }
    }
}
