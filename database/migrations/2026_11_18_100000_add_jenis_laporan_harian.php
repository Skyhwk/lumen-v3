<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('laporan_harian')) {
            return;
        }

        if (!Schema::hasColumn('laporan_harian', 'jenis')) {
            Schema::table('laporan_harian', function (Blueprint $table) {
                $table->string('jenis', 16)->default('harian')->after('karyawan_id');
            });
        }

        DB::table('laporan_harian')->whereNull('jenis')->orWhere('jenis', '')->update(['jenis' => 'harian']);

        $indexes = collect(DB::select('SHOW INDEX FROM laporan_harian'))->pluck('Key_name');

        Schema::table('laporan_harian', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('uq_laporan_harian_karyawan_tanggal')) {
                $table->dropUnique('uq_laporan_harian_karyawan_tanggal');
            }
            if (!$indexes->contains('uq_laporan_harian_karyawan_jenis_tanggal')) {
                $table->unique(['karyawan_id', 'jenis', 'tanggal'], 'uq_laporan_harian_karyawan_jenis_tanggal');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('laporan_harian')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM laporan_harian'))->pluck('Key_name');

        Schema::table('laporan_harian', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('uq_laporan_harian_karyawan_jenis_tanggal')) {
                $table->dropUnique('uq_laporan_harian_karyawan_jenis_tanggal');
            }
            if (!$indexes->contains('uq_laporan_harian_karyawan_tanggal')) {
                $table->unique(['karyawan_id', 'tanggal'], 'uq_laporan_harian_karyawan_tanggal');
            }
            if (Schema::hasColumn('laporan_harian', 'jenis')) {
                $table->dropColumn('jenis');
            }
        });
    }
};
