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

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            if (!Schema::hasColumn('kategori_peralatan_sampling', 'status_alat')) {
                $column = $table->text('status_alat')->nullable();
                if (Schema::hasColumn('kategori_peralatan_sampling', 'lokasi_penempatan_alat')) {
                    $column->after('lokasi_penempatan_alat');
                }
            }
        });

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            if (!Schema::hasColumn('kategori_peralatan_sampling', 'jumlah_alat_tersedia')) {
                $column = $table->unsignedInteger('jumlah_alat_tersedia')->default(0);
                if (Schema::hasColumn('kategori_peralatan_sampling', 'status_alat')) {
                    $column->after('status_alat');
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('kategori_peralatan_sampling')) {
            return;
        }

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('kategori_peralatan_sampling', 'jumlah_alat_tersedia')) {
                $drop[] = 'jumlah_alat_tersedia';
            }
            if (Schema::hasColumn('kategori_peralatan_sampling', 'status_alat')) {
                $drop[] = 'status_alat';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
