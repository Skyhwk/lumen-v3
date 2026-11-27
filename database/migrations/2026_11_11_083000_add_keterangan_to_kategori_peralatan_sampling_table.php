<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kategori_peralatan_sampling')) {
            return;
        }

        if (Schema::hasColumn('kategori_peralatan_sampling', 'keterangan')) {
            return;
        }

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            $column = $table->text('keterangan')->nullable();

            if (Schema::hasColumn('kategori_peralatan_sampling', 'jumlah_alat_tersedia')) {
                $column->after('jumlah_alat_tersedia');
            } elseif (Schema::hasColumn('kategori_peralatan_sampling', 'status_alat')) {
                $column->after('status_alat');
            } elseif (Schema::hasColumn('kategori_peralatan_sampling', 'lokasi_penempatan_alat')) {
                $column->after('lokasi_penempatan_alat');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('kategori_peralatan_sampling')) {
            return;
        }

        if (!Schema::hasColumn('kategori_peralatan_sampling', 'keterangan')) {
            return;
        }

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });
    }
};
