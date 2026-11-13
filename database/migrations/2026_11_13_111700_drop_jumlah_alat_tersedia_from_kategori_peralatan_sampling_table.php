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

        if (!Schema::hasColumn('kategori_peralatan_sampling', 'jumlah_alat_tersedia')) {
            return;
        }

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            $table->dropColumn('jumlah_alat_tersedia');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('kategori_peralatan_sampling')) {
            return;
        }

        if (Schema::hasColumn('kategori_peralatan_sampling', 'jumlah_alat_tersedia')) {
            return;
        }

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            $column = $table->unsignedInteger('jumlah_alat_tersedia')->default(0);
            if (Schema::hasColumn('kategori_peralatan_sampling', 'status_alat')) {
                $column->after('status_alat');
            }
        });
    }
};
