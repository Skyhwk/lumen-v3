<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monitoring_peralatan') && Schema::hasColumn('monitoring_peralatan', 'kode_aset')) {
            Schema::table('monitoring_peralatan', function (Blueprint $table) {
                $table->dropColumn('kode_aset');
            });
        }

        if (Schema::hasTable('peralatan') && !Schema::hasColumn('peralatan', 'kode_aset')) {
            Schema::table('peralatan', function (Blueprint $table) {
                $column = $table->string('kode_aset', 255)->nullable();

                if (Schema::hasColumn('peralatan', 'nama_alat')) {
                    $column->after('nama_alat');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('peralatan') && Schema::hasColumn('peralatan', 'kode_aset')) {
            Schema::table('peralatan', function (Blueprint $table) {
                $table->dropColumn('kode_aset');
            });
        }

        if (Schema::hasTable('monitoring_peralatan') && !Schema::hasColumn('monitoring_peralatan', 'kode_aset')) {
            Schema::table('monitoring_peralatan', function (Blueprint $table) {
                $column = $table->string('kode_aset', 255)->nullable();

                if (Schema::hasColumn('monitoring_peralatan', 'end_date')) {
                    $column->after('end_date');
                } elseif (Schema::hasColumn('monitoring_peralatan', 'keterangan')) {
                    $column->after('keterangan');
                } elseif (Schema::hasColumn('monitoring_peralatan', 'status_alat')) {
                    $column->after('status_alat');
                }
            });
        }
    }
};
