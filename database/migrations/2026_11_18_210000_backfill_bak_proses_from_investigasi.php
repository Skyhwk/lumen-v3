<?php

use App\Services\BakLaporanService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bak_investigasi_berita_acara')) {
            return;
        }

        $ids = DB::table('bak_investigasi_berita_acara')
            ->distinct()
            ->pluck('berita_acara_id')
            ->map(static fn ($v) => (int) $v)
            ->filter(static fn ($v) => $v > 0)
            ->all();

        BakLaporanService::syncBeritaAcaraProsesFromInvestigasiIds($ids);
    }

    public function down(): void
    {
        // Data-driven sync; tidak di-rollback.
    }
};