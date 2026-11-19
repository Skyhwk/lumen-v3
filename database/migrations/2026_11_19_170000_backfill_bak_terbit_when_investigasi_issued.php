<?php

use App\Services\BakLaporanService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BAK yang sudah punya Investigasi F1 terbit tetapi status dokumen masih draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('berita_acara_kejadian_disipliner')
            || !Schema::hasTable('bak_investigasi_berita_acara')
            || !Schema::hasTable('bak_investigasi')
        ) {
            return;
        }

        $bakIds = DB::table('bak_investigasi_berita_acara as link')
            ->join('bak_investigasi as inv', 'inv.id', '=', 'link.bak_investigasi_id')
            ->join('berita_acara_kejadian_disipliner as bak', 'bak.id', '=', 'link.berita_acara_id')
            ->where('inv.is_active', true)
            ->where('bak.is_active', true)
            ->where('inv.status', 'issued')
            ->where('bak.status', 'draft')
            ->pluck('bak.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($bakIds as $bakId) {
            BakLaporanService::syncBeritaAcaraProsesFromInvestigasi($bakId);
        }
    }

    public function down(): void
    {
        // Tidak mengembalikan status BAK yang sudah disinkronkan otomatis.
    }
};