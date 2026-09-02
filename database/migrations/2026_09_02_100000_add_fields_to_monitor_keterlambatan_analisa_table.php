<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToMonitorKeterlambatanAnalisaTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('monitor_keterlambatan_analisa')) {
            return;
        }

        Schema::table('monitor_keterlambatan_analisa', function (Blueprint $table) {
            if (!Schema::hasColumn('monitor_keterlambatan_analisa', 'id_parameter')) {
                $table->unsignedInteger('id_parameter')->nullable()->after('nama_parameter');
                $table->index('id_parameter', 'idx_id_parameter');
            }
            if (!Schema::hasColumn('monitor_keterlambatan_analisa', 'tanggal_jadwal')) {
                $table->date('tanggal_jadwal')->nullable()->after('kategori_2');
                $table->index('tanggal_jadwal', 'idx_tanggal_jadwal');
            }
            if (!Schema::hasColumn('monitor_keterlambatan_analisa', 'analis_input')) {
                $table->dateTime('analis_input')->nullable()->after('ftc_verifier');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('monitor_keterlambatan_analisa')) {
            return;
        }

        Schema::table('monitor_keterlambatan_analisa', function (Blueprint $table) {
            if (Schema::hasColumn('monitor_keterlambatan_analisa', 'analis_input')) {
                $table->dropColumn('analis_input');
            }
            if (Schema::hasColumn('monitor_keterlambatan_analisa', 'tanggal_jadwal')) {
                $table->dropIndex('idx_tanggal_jadwal');
                $table->dropColumn('tanggal_jadwal');
            }
            if (Schema::hasColumn('monitor_keterlambatan_analisa', 'id_parameter')) {
                $table->dropIndex('idx_id_parameter');
                $table->dropColumn('id_parameter');
            }
        });
    }
}
