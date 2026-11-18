<?php

use App\Services\BakInvestigasiDocumentService;
use App\Services\BakLaporanService;
use App\Support\InvestigasiAwalStatus;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bak_investigasi')) {
            return;
        }

        $now = Carbon::now();
        $rows = DB::table('bak_investigasi')
            ->where('is_active', true)
            ->where('status', InvestigasiAwalStatus::MENUNGGU_PENGESAHAN)
            ->whereNotNull('legal_approved_at')
            ->get(['id', 'legal_approved_at', 'legal_approved_by', 'file_path']);

        foreach ($rows as $row) {
            $id = (int) $row->id;
            DB::table('bak_investigasi')
                ->where('id', $id)
                ->update([
                    'status' => InvestigasiAwalStatus::ISSUED,
                    'published_at' => $row->legal_approved_at ?? $now,
                    'published_by' => $row->legal_approved_by,
                    'updated_at' => $now,
                ]);

            if (trim((string) ($row->file_path ?? '')) === '') {
                try {
                    app(BakInvestigasiDocumentService::class)->issuePdf($id);
                } catch (\Throwable $ex) {
                    // PDF bisa diterbitkan ulang dari menu jika gagal di migrasi.
                }
            }

            BakLaporanService::syncBeritaAcaraProsesForInvestigasi($id);
        }
    }

    public function down(): void
    {
        // Status tidak di-rollback ke alur lama.
    }
};