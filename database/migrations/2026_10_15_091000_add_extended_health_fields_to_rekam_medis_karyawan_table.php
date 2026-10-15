<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rekam_medis_karyawan')) {
            return;
        }

        $addObatRutin = !Schema::hasColumn('rekam_medis_karyawan', 'obat_rutin');
        $addGangguanMental = !Schema::hasColumn('rekam_medis_karyawan', 'gangguan_mental');
        $addAlergi = !Schema::hasColumn('rekam_medis_karyawan', 'alergi');

        if (!$addObatRutin && !$addGangguanMental && !$addAlergi) {
            return;
        }

        Schema::table('rekam_medis_karyawan', function (Blueprint $table) use ($addObatRutin, $addGangguanMental, $addAlergi) {
            if ($addObatRutin) {
                $table->text('obat_rutin')->nullable()->after('riwayat_kecelakaan');
            }
            if ($addGangguanMental) {
                $table->text('gangguan_mental')->nullable()->after('obat_rutin');
            }
            if ($addAlergi) {
                $table->text('alergi')->nullable()->after('gangguan_mental');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('rekam_medis_karyawan')) {
            return;
        }

        foreach (['alergi', 'gangguan_mental', 'obat_rutin'] as $column) {
            if (Schema::hasColumn('rekam_medis_karyawan', $column)) {
                Schema::table('rekam_medis_karyawan', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
