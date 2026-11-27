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

        if (Schema::hasColumn('kategori_peralatan_sampling', 'kode_alat')) {
            return;
        }

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            $column = $table->text('kode_alat')->nullable();
            if (Schema::hasColumn('kategori_peralatan_sampling', 'nama_alat')) {
                $column->after('nama_alat');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('kategori_peralatan_sampling')) {
            return;
        }

        if (!Schema::hasColumn('kategori_peralatan_sampling', 'kode_alat')) {
            return;
        }

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            $table->dropColumn('kode_alat');
        });
    }
};
