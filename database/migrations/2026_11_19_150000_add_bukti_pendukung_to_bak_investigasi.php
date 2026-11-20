<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran bukti pendukung Investigasi Awal (di luar lampiran BAK) — JSON daftar nama file.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bak_investigasi')) {
            return;
        }

        if (!Schema::hasColumn('bak_investigasi', 'bukti_pendukung')) {
            Schema::table('bak_investigasi', function (Blueprint $table) {
                $table->json('bukti_pendukung')
                    ->nullable()
                    ->after('jenis_bukti')
                    ->comment('Nama file lampiran tambahan investigasi (folder public/dokumentasi/investigasi)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bak_investigasi') && Schema::hasColumn('bak_investigasi', 'bukti_pendukung')) {
            Schema::table('bak_investigasi', function (Blueprint $table) {
                $table->dropColumn('bukti_pendukung');
            });
        }
    }
};