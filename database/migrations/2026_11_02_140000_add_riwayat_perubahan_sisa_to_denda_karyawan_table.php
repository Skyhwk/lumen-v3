<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('denda_karyawan')) {
            return;
        }

        Schema::table('denda_karyawan', function (Blueprint $table) {
            if (!Schema::hasColumn('denda_karyawan', 'riwayat_perubahan_sisa')) {
                $table->json('riwayat_perubahan_sisa')->nullable()->after('sisa_denda');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('denda_karyawan')) {
            return;
        }

        Schema::table('denda_karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('denda_karyawan', 'riwayat_perubahan_sisa')) {
                $table->dropColumn('riwayat_perubahan_sisa');
            }
        });
    }
};
