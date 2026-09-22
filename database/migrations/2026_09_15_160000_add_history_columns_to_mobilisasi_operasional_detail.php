<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHistoryColumnsToMobilisasiOperasionalDetail extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('mobilisasi_operasional_detail')) {
            return;
        }

        Schema::table('mobilisasi_operasional_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('mobilisasi_operasional_detail', 'alasan_perubahan')) {
                $table->text('alasan_perubahan')->nullable()->after('durasi');
            }
            if (!Schema::hasColumn('mobilisasi_operasional_detail', 'sumber_perubahan')) {
                $table->string('sumber_perubahan', 50)->nullable()->index()->after('alasan_perubahan');
            }
            if (!Schema::hasColumn('mobilisasi_operasional_detail', 'id_jadwal_asal')) {
                $table->unsignedBigInteger('id_jadwal_asal')->nullable()->index()->after('sumber_perubahan');
            }
            if (!Schema::hasColumn('mobilisasi_operasional_detail', 'sampler_sebelum')) {
                $table->string('sampler_sebelum', 255)->nullable()->after('id_jadwal_asal');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('mobilisasi_operasional_detail')) {
            return;
        }

        Schema::table('mobilisasi_operasional_detail', function (Blueprint $table) {
            foreach (['alasan_perubahan', 'sumber_perubahan', 'id_jadwal_asal', 'sampler_sebelum'] as $column) {
                if (Schema::hasColumn('mobilisasi_operasional_detail', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
