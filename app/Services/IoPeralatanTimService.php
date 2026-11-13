<?php

namespace App\Services;

use App\Models\IoPeralatanTim;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class IoPeralatanTimService
{
    /**
     * Normalisasi nama petugas agar match merge IO frontend:
     * split koma → trim → lower → sort → join "|" → sha1 (aman untuk unique index)
     */
    public static function normalizePetugasKey(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '';
        }

        $decoded = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
        $names = array_values(array_filter(array_map(function ($name) {
            return mb_strtolower(trim($name));
        }, preg_split('/\s*,\s*/', $decoded) ?: [])));

        sort($names, SORT_STRING);
        $joined = implode('|', $names);

        return $joined === '' ? '' : sha1($joined);
    }

    /**
     * @return array<string, array> map petugas_key => peralatan list
     */
    public static function mapByTanggal(string $tanggal): array
    {
        if (!Schema::hasTable('io_peralatan_tim')) {
            return [];
        }

        $map = [];
        $rows = IoPeralatanTim::query()
            ->where('is_active', true)
            ->whereDate('tanggal', $tanggal)
            ->get(['petugas_key', 'peralatan']);

        foreach ($rows as $row) {
            $key = (string) $row->petugas_key;
            if ($key === '') {
                continue;
            }
            $map[$key] = JadwalMobilisasiService::normalizePeralatanList($row->peralatan ?? []);
        }

        return $map;
    }

    public static function findActive(string $tanggal, string $petugasKey): ?IoPeralatanTim
    {
        if (!Schema::hasTable('io_peralatan_tim') || $petugasKey === '') {
            return null;
        }

        return IoPeralatanTim::query()
            ->where('is_active', true)
            ->whereDate('tanggal', $tanggal)
            ->where('petugas_key', $petugasKey)
            ->first();
    }

    /**
     * Agregasi qty alat bawaan aktual tim untuk 1 tanggal.
     * Ini sumber pengunci stok (bukan peralatan_sampling jadwal).
     *
     * @param  array<int, int|string>|null  $excludeBatchIds
     * @return array<string, int> map "NAMA|cabangId" => qty
     */
    public static function usedQtyByTanggal(
        string $tanggal,
        ?string $excludePetugasKey = null,
        $excludeBatchIds = null
    ): array {
        if (!Schema::hasTable('io_peralatan_tim') || trim($tanggal) === '') {
            return [];
        }

        $excludeKey = $excludePetugasKey !== null ? trim($excludePetugasKey) : '';
        $excludeBatches = JadwalMobilisasiService::normalizeBatchIds($excludeBatchIds);
        $excludeIds = $excludeBatches['ids'] ?? [];

        $rows = IoPeralatanTim::query()
            ->where('is_active', true)
            ->whereDate('tanggal', $tanggal)
            ->get(['petugas_key', 'batch_ids', 'peralatan']);

        $used = [];
        foreach ($rows as $row) {
            $key = (string) ($row->petugas_key ?? '');
            if ($excludeKey !== '' && $key === $excludeKey) {
                continue;
            }

            if ($excludeIds !== []) {
                $rowBatches = JadwalMobilisasiService::normalizeBatchIds($row->batch_ids ?? []);
                if (!empty(array_intersect($excludeIds, $rowBatches['ids'] ?? []))) {
                    continue;
                }
            }

            $peralatan = JadwalMobilisasiService::normalizePeralatanList($row->peralatan ?? []);
            foreach ($peralatan as $item) {
                $namaAlat = strtoupper(trim((string) ($item['nama_alat'] ?? '')));
                $qty = (int) ($item['jumlah_alat'] ?? $item['jumlah'] ?? 0);
                $itemCabang = isset($item['id_cabang']) && $item['id_cabang'] !== ''
                    ? (int) $item['id_cabang']
                    : (isset($item['branch_id']) && $item['branch_id'] !== '' ? (int) $item['branch_id'] : 0);

                if ($namaAlat === '' || $qty < 1) {
                    continue;
                }

                $specificKey = $namaAlat . '|' . $itemCabang;
                $used[$specificKey] = ($used[$specificKey] ?? 0) + $qty;
            }
        }

        return $used;
    }

    /**
     * Map peralatan → [nama_lower => qty] untuk diff snapshot.
     *
     * @param  array<int, array>  $list
     * @return array<string, array{nama_alat: string, jumlah_alat: int}>
     */
    private static function peralatanIndex(array $list): array
    {
        $map = [];
        foreach (JadwalMobilisasiService::normalizePeralatanList($list) as $item) {
            $nama = trim((string) ($item['nama_alat'] ?? ''));
            if ($nama === '') {
                continue;
            }
            $key = mb_strtolower($nama);
            $map[$key] = [
                'nama_alat' => $nama,
                'jumlah_alat' => (int) ($item['jumlah_alat'] ?? 0),
            ];
        }

        return $map;
    }

    /**
     * Hitung diff antara sebelum & sesudah untuk audit trail.
     *
     * @param  array<int, array>  $before
     * @param  array<int, array>  $after
     * @return array{added: array, removed: array, changed: array, has_changes: bool}
     */
    public static function diffPeralatan(array $before, array $after): array
    {
        $beforeMap = self::peralatanIndex($before);
        $afterMap = self::peralatanIndex($after);

        $added = [];
        $removed = [];
        $changed = [];

        foreach ($afterMap as $key => $item) {
            if (!isset($beforeMap[$key])) {
                $added[] = [
                    'nama_alat' => $item['nama_alat'],
                    'jumlah_alat' => $item['jumlah_alat'],
                    'delta' => $item['jumlah_alat'],
                ];
                continue;
            }
            $prevQty = (int) $beforeMap[$key]['jumlah_alat'];
            $nextQty = (int) $item['jumlah_alat'];
            if ($prevQty !== $nextQty) {
                $changed[] = [
                    'nama_alat' => $item['nama_alat'],
                    'jumlah_sebelum' => $prevQty,
                    'jumlah_sesudah' => $nextQty,
                    'delta' => $nextQty - $prevQty,
                ];
            }
        }

        foreach ($beforeMap as $key => $item) {
            if (!isset($afterMap[$key])) {
                $removed[] = [
                    'nama_alat' => $item['nama_alat'],
                    'jumlah_alat' => $item['jumlah_alat'],
                    'delta' => -1 * (int) $item['jumlah_alat'],
                ];
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'has_changes' => $added !== [] || $removed !== [] || $changed !== [],
        ];
    }

    /**
     * Append 1 entri snapshot ke record (append-only).
     *
     * @param  array<int, array>  $before
     * @param  array<int, array>  $after
     */
    public static function buildSnapshotEntry(
        array $before,
        array $after,
        string $action,
        ?string $actor,
        string $petugasSampling,
        string $at
    ): array {
        $diff = self::diffPeralatan($before, $after);

        return [
            'at' => $at,
            'action' => $action,
            'updated_by' => $actor,
            'petugas_sampling' => trim($petugasSampling),
            'before' => JadwalMobilisasiService::normalizePeralatanList($before),
            'after' => JadwalMobilisasiService::normalizePeralatanList($after),
            'added' => $diff['added'],
            'removed' => $diff['removed'],
            'changed' => $diff['changed'],
        ];
    }

    /**
     * Upsert bawaan aktual peralatan untuk 1 tim di 1 tanggal.
     * Setiap simpan yang berubah meng-append ke kolom snapshots.
     *
     * @param  array<int, mixed>  $batchIds
     * @param  array<int, array>  $peralatan
     */
    public static function upsert(
        string $tanggal,
        string $petugasSampling,
        array $batchIds,
        array $peralatan,
        ?int $idCabang = null,
        ?string $actor = null
    ): IoPeralatanTim {
        $petugasKey = self::normalizePetugasKey($petugasSampling);
        if ($petugasKey === '') {
            throw new \InvalidArgumentException('petugas_sampling wajib diisi untuk menyimpan peralatan tim.');
        }

        $normalized = JadwalMobilisasiService::normalizePeralatanList($peralatan);
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $record = self::findActive($tanggal, $petugasKey);
        $isCreate = !$record;
        if (!$record) {
            $record = new IoPeralatanTim();
            $record->created_by = $actor;
            $record->created_at = $now;
            $record->is_active = true;
        }

        $before = JadwalMobilisasiService::normalizePeralatanList($record->peralatan ?? []);
        $diff = self::diffPeralatan($before, $normalized);
        $snapshots = is_array($record->snapshots) ? $record->snapshots : [];

        // Catat snapshot jika create atau ada perubahan alat/qty.
        if ($isCreate || $diff['has_changes']) {
            if (Schema::hasColumn('io_peralatan_tim', 'snapshots')) {
                $snapshots[] = self::buildSnapshotEntry(
                    $before,
                    $normalized,
                    $isCreate ? 'create' : 'update',
                    $actor,
                    $petugasSampling,
                    $now
                );
            }
        }

        $payload = [
            'tanggal' => $tanggal,
            'id_cabang' => $idCabang,
            'petugas_key' => $petugasKey,
            'petugas_sampling' => trim($petugasSampling),
            'batch_ids' => array_values($batchIds),
            'peralatan' => $normalized,
            'updated_by' => $actor,
            'updated_at' => $now,
            'is_active' => true,
        ];
        if (Schema::hasColumn('io_peralatan_tim', 'snapshots')) {
            $payload['snapshots'] = $snapshots;
        }

        $record->fill($payload);
        $record->save();

        return $record->fresh();
    }
}
