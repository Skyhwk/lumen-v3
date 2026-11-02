<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rekap_masuk_kerja') && !Schema::hasColumn('rekap_masuk_kerja', 'hari_kerja')) {
            Schema::table('rekap_masuk_kerja', function (Blueprint $table) {
                $table->unsignedSmallInteger('hari_kerja')
                    ->nullable()
                    ->after('tanggal')
                    ->comment('Jumlah hari kerja expected periode (diisi saat generate absensi)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rekap_masuk_kerja', 'hari_kerja')) {
            Schema::table('rekap_masuk_kerja', function (Blueprint $table) {
                $table->dropColumn('hari_kerja');
            });
        }
    }
};
