<?php

namespace App\Services;

use App\Models\BeritaAcaraKejadianDisipliner;
use App\Support\InvestigasiAwalStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Filter data menu Laporan BAK:
 * - Belum diproses: sudah diverifikasi atasan (menunggu_diproses), belum ditambahkan di Investigasi Awal.
 * - Sudah diproses: nomor BAK sudah ditautkan pada dokumen Investigasi Awal.
 */
final class BakLaporanService
{
    /**
     * @param  Builder  $query  Query pada berita_acara_kejadian_disipliner (alias opsional)
     */
    public static function applyLaporanTabFilter(Builder $query, bool $isProcessed, string $bakAlias = 'berita_acara_kejadian_disipliner'): void
    {
        if ($isProcessed) {
            self::applySudahDiprosesLaporan($query, $bakAlias);
            return;
        }

        self::applyBelumDiprosesLaporan($query, $bakAlias);
    }

    public static function applyBelumDiprosesLaporan(Builder $query, string $bakAlias = 'berita_acara_kejadian_disipliner'): void
    {
        $query->where("{$bakAlias}.proses_status", BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES);
        $query->whereNotExists(function ($sub) use ($bakAlias) {
            self::wrapInvestigasiLinkExists($sub, $bakAlias);
        });
    }

    public static function applySudahDiprosesLaporan(Builder $query, string $bakAlias = 'berita_acara_kejadian_disipliner'): void
    {
        $query->whereExists(function ($sub) use ($bakAlias) {
            self::wrapInvestigasiLinkExists($sub, $bakAlias);
        });
    }

    /**
     * BAK yang boleh dipilih di form Investigasi Awal (belum ditautkan, sudah lolos atasan).
     */
    public static function applyInvestigasiBakOptionsScope(Builder $query, string $bakAlias = 'bak'): void
    {
        $query->where("{$bakAlias}.proses_status", BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES);
        $query->whereNotExists(function ($sub) use ($bakAlias) {
            self::wrapInvestigasiLinkExists($sub, $bakAlias);
        });
    }

    /**
     * @param  list<int>  $beritaAcaraIds
     */
    public static function syncInvestigasiLinks(int $bakInvestigasiId, array $beritaAcaraIds): void
    {
        if (!Schema::hasTable('bak_investigasi_berita_acara')) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $beritaAcaraIds), fn ($id) => $id > 0)));
        $now = Carbon::now();

        $previousLinked = DB::table('bak_investigasi_berita_acara')
            ->where('bak_investigasi_id', $bakInvestigasiId)
            ->pluck('berita_acara_id')
            ->map(static fn ($v) => (int) $v)
            ->all();

        $unlinkQuery = DB::table('bak_investigasi_berita_acara')
            ->where('bak_investigasi_id', $bakInvestigasiId);
        if ($ids === []) {
            $unlinkQuery->delete();
        } else {
            $unlinkQuery->whereNotIn('berita_acara_id', $ids)->delete();
        }

        foreach ($ids as $beritaAcaraId) {
            $exists = DB::table('bak_investigasi_berita_acara')
                ->where('berita_acara_id', $beritaAcaraId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('bak_investigasi_berita_acara')->insert([
                'bak_investigasi_id' => $bakInvestigasiId,
                'berita_acara_id' => $beritaAcaraId,
                'created_at' => $now,
            ]);
        }

        $affected = array_values(array_unique(array_merge($previousLinked, $ids)));
        self::syncBeritaAcaraProsesFromInvestigasiIds($affected);
    }

    /**
     * @param  list<int>  $beritaAcaraIds
     */
    public static function syncBeritaAcaraProsesFromInvestigasiIds(array $beritaAcaraIds): void
    {
        foreach ($beritaAcaraIds as $beritaAcaraId) {
            $id = (int) $beritaAcaraId;
            if ($id > 0) {
                self::syncBeritaAcaraProsesFromInvestigasi($id);
            }
        }
    }

    public static function syncBeritaAcaraProsesForInvestigasi(int $bakInvestigasiId): void
    {
        if (!Schema::hasTable('bak_investigasi_berita_acara')) {
            return;
        }

        $ids = DB::table('bak_investigasi_berita_acara')
            ->where('bak_investigasi_id', $bakInvestigasiId)
            ->pluck('berita_acara_id')
            ->map(static fn ($v) => (int) $v)
            ->all();

        self::syncBeritaAcaraProsesFromInvestigasiIds($ids);
    }

    public static function isBeritaAcaraLinkedToInvestigasi(int $beritaAcaraId): bool
    {
        if (!Schema::hasTable('bak_investigasi_berita_acara') || $beritaAcaraId <= 0) {
            return false;
        }

        return DB::table('bak_investigasi_berita_acara')
            ->where('berita_acara_id', $beritaAcaraId)
            ->exists();
    }

    public static function syncBeritaAcaraProsesFromInvestigasi(int $beritaAcaraId): void
    {
        if (
            !Schema::hasTable('berita_acara_kejadian_disipliner')
            || $beritaAcaraId <= 0
        ) {
            return;
        }

        $row = DB::table('berita_acara_kejadian_disipliner')
            ->where('id', $beritaAcaraId)
            ->where('is_active', true)
            ->first(['id', 'proses_status']);

        if ($row === null) {
            return;
        }

        $current = (string) ($row->proses_status ?? '');
        $eligible = [
            BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES,
            BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES,
            BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES,
        ];

        if (!in_array($current, $eligible, true)) {
            return;
        }

        $next = self::resolveProsesStatusFromLinkedInvestigasi($beritaAcaraId);
        if ($next === null || $next === $current) {
            return;
        }

        DB::table('berita_acara_kejadian_disipliner')
            ->where('id', $beritaAcaraId)
            ->update([
                'proses_status' => $next,
                'updated_at' => Carbon::now(),
            ]);
    }

    private static function resolveProsesStatusFromLinkedInvestigasi(int $beritaAcaraId): ?string
    {
        if (!Schema::hasTable('bak_investigasi_berita_acara') || !Schema::hasTable('bak_investigasi')) {
            return BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES;
        }

        /** @var Collection<int, string> $statuses */
        $statuses = DB::table('bak_investigasi_berita_acara as link')
            ->join('bak_investigasi as inv', 'inv.id', '=', 'link.bak_investigasi_id')
            ->where('link.berita_acara_id', $beritaAcaraId)
            ->where('inv.is_active', true)
            ->pluck('inv.status');

        if ($statuses->isEmpty()) {
            return BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES;
        }

        $normalized = $statuses
            ->map(static fn ($s) => strtolower(trim((string) $s)))
            ->filter(static fn ($s) => $s !== '' && $s !== InvestigasiAwalStatus::CANCELLED);

        if ($normalized->isEmpty()) {
            return BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES;
        }

        if ($normalized->contains(InvestigasiAwalStatus::ISSUED)) {
            return BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES;
        }

        return BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES;
    }

    private static function wrapInvestigasiLinkExists($sub, string $bakAlias): void
    {
        if (Schema::hasTable('bak_investigasi_berita_acara')) {
            $sub->from('bak_investigasi_berita_acara as inv_ba_link')
                ->whereColumn('inv_ba_link.berita_acara_id', "{$bakAlias}.id");
            return;
        }

        if (!Schema::hasTable('bak_investigasi')) {
            $sub->from('bak_investigasi_berita_acara as inv_ba_link')
                ->whereRaw('0 = 1');
            return;
        }

        $sub->from('bak_investigasi as inv_hdr')
            ->where('inv_hdr.is_active', true)
            ->where(function ($w) use ($bakAlias) {
                $w->whereColumn('inv_hdr.no_bak_pertama', "{$bakAlias}.nomor_surat")
                    ->orWhereColumn('inv_hdr.no_bak_terakhir', "{$bakAlias}.nomor_surat");
            });
    }
}