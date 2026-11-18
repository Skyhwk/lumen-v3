<?php

namespace App\Services;

use App\Models\IoPeralatanTim;
use App\Models\Jadwal;
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
        $names = self::petugasNameList($raw);
        if ($names === []) {
            return '';
        }

        sort($names, SORT_STRING);
        $joined = implode('|', $names);

        return sha1($joined);
    }

    /**
     * Daftar nama petugas (lower+trim) dari string CSV / HTML entity.
     *
     * @return list<string>
     */
    public static function petugasNameList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');

        return array_values(array_filter(array_map(function ($name) {
            return mb_strtolower(trim($name));
        }, preg_split('/\s*,\s*/', $decoded) ?: [])));
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

    /**
     * Ambil peralatan aktual aktif yang memuat salah satu batch_id (paling akurat untuk index IO).
     * Lookup by petugas_key sering gagal karena sampler di jadwal = 1 orang, sementara IO = tim.
     *
     * @param  mixed  $batchIds
     * @return array<int, array>
     */
    public static function resolvePeralatanForBatches(string $tanggal, $batchIds): array
    {
        if (!Schema::hasTable('io_peralatan_tim') || trim($tanggal) === '') {
            return [];
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', JadwalMobilisasiService::flattenBatchIds($batchIds)),
            fn ($id) => $id > 0
        )));
        if ($ids === []) {
            return [];
        }

        $rows = IoPeralatanTim::query()
            ->where('is_active', true)
            ->whereDate('tanggal', Carbon::parse($tanggal)->format('Y-m-d'))
            ->get(['batch_ids', 'peralatan']);

        foreach ($rows as $row) {
            $rowIds = array_values(array_unique(array_filter(
                array_map('intval', JadwalMobilisasiService::flattenBatchIds($row->batch_ids ?? [])),
                fn ($id) => $id > 0
            )));
            if ($rowIds === []) {
                continue;
            }
            if (array_intersect($ids, $rowIds) !== []) {
                return JadwalMobilisasiService::normalizePeralatanList($row->peralatan ?? []);
            }
        }

        return [];
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
     * Cari row by tanggal+petugas_key (aktif dulu, fallback inactive).
     * Perlu untuk upsert: unique index tidak membedakan is_active.
     */
    public static function findByTanggalPetugas(string $tanggal, string $petugasKey): ?IoPeralatanTim
    {
        if (!Schema::hasTable('io_peralatan_tim') || $petugasKey === '') {
            return null;
        }

        $active = self::findActive($tanggal, $petugasKey);
        if ($active) {
            return $active;
        }

        return IoPeralatanTim::query()
            ->whereDate('tanggal', $tanggal)
            ->where('petugas_key', $petugasKey)
            ->orderByDesc('id')
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
                $kodeList = [];
                if (!empty($item['kode_aset_list']) && is_array($item['kode_aset_list'])) {
                    $kodeList = array_values(array_filter(array_map(function ($k) {
                        return trim((string) $k);
                    }, $item['kode_aset_list'])));
                }
                $qty = $kodeList !== []
                    ? count($kodeList)
                    : (int) ($item['jumlah_alat'] ?? $item['jumlah'] ?? 0);
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
     * Daftar kode_aset yang sudah dibawa tim lain di tanggal sampling yang sama.
     * (Tanpa durasi — kunci hanya by tanggal.)
     *
     * @return array{kodes: list<string>, by_kode: array<string, string>, range: array{start: string, end: string, durasi: int}}
     */
    public static function usedKodeAsetByTanggal(
        string $tanggal,
        ?string $excludePetugasKey = null,
        int $durasi = 1
    ): array {
        $start = Carbon::parse($tanggal)->format('Y-m-d');
        // Param $durasi diabaikan (kompatibilitas signature); kunci hanya tanggal yang sama.
        $empty = [
            'kodes' => [],
            'by_kode' => [],
            'range' => ['start' => $start, 'end' => $start, 'durasi' => 1],
        ];

        if (!Schema::hasTable('io_peralatan_tim') || trim($tanggal) === '') {
            return $empty;
        }

        $excludeKey = $excludePetugasKey !== null ? trim($excludePetugasKey) : '';
        $rows = IoPeralatanTim::query()
            ->where('is_active', true)
            ->whereDate('tanggal', $start)
            ->get(['petugas_key', 'petugas_sampling', 'peralatan']);

        $kodes = [];
        $byKode = [];
        foreach ($rows as $row) {
            $key = (string) ($row->petugas_key ?? '');
            if ($excludeKey !== '' && $key === $excludeKey) {
                continue;
            }

            $owner = trim((string) ($row->petugas_sampling ?? '')) ?: $key;
            $peralatan = JadwalMobilisasiService::normalizePeralatanList($row->peralatan ?? []);
            foreach ($peralatan as $item) {
                $list = $item['kode_aset_list'] ?? [];
                if (!is_array($list)) {
                    continue;
                }
                foreach ($list as $kode) {
                    $kode = trim((string) $kode);
                    if ($kode === '' || isset($byKode[$kode])) {
                        continue;
                    }
                    $byKode[$kode] = $owner;
                    $kodes[] = $kode;
                }
            }
        }

        return [
            'kodes' => $kodes,
            'by_kode' => $byKode,
            'range' => ['start' => $start, 'end' => $start, 'durasi' => 1],
        ];
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

        // Unique (tanggal, petugas_key) berlaku juga untuk row is_active=0 (hasil release).
        // Jangan insert baru — reaktivasi / update row yang sudah ada.
        $record = self::findByTanggalPetugas($tanggal, $petugasKey);
        $isCreate = !$record;
        $wasInactive = $record && !(bool) $record->is_active;
        if (!$record) {
            $record = new IoPeralatanTim();
            $record->created_by = $actor;
            $record->created_at = $now;
            $record->is_active = true;
        }

        $before = $wasInactive
            ? []
            : JadwalMobilisasiService::normalizePeralatanList($record->peralatan ?? []);
        $diff = self::diffPeralatan($before, $normalized);
        $snapshots = is_array($record->snapshots) ? $record->snapshots : [];

        // Catat snapshot jika create, reactivate, atau ada perubahan alat/qty.
        if ($isCreate || $wasInactive || $diff['has_changes']) {
            if (Schema::hasColumn('io_peralatan_tim', 'snapshots')) {
                $action = $isCreate ? 'create' : ($wasInactive ? 'reactivate' : 'update');
                $snapshots[] = self::buildSnapshotEntry(
                    $before,
                    $normalized,
                    $action,
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

    /**
     * Rekonsiliasi kepemilikan alat setelah sampler SP berubah.
     * - Detach batch dari io_peralatan_tim milik tim lama (petugas_key ≠ newKey)
     * - Jika batch_ids kosong → nonaktifkan row + release monitoring
     * - Tim baru tidak auto-dapat alat
     *
     * @param  mixed  $batchIds  satu id atau list jadwal id
     * @return array{detached: list<array>, released: list<array>, remapped: list<array>}
     */
    public static function reconcileBatchSamplerChange(
        string $tanggal,
        $batchIds,
        ?string $newPetugasSampling = null,
        ?string $actor = null
    ): array {
        $result = ['detached' => [], 'released' => [], 'remapped' => []];
        if (!Schema::hasTable('io_peralatan_tim')) {
            return $result;
        }

        $tanggalNorm = Carbon::parse($tanggal)->format('Y-m-d');
        $ids = JadwalMobilisasiService::normalizeBatchIds($batchIds)['ids'] ?? [];
        if ($ids === []) {
            return $result;
        }

        $newKey = self::normalizePetugasKey($newPetugasSampling);
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $rows = IoPeralatanTim::query()
            ->where('is_active', true)
            ->whereDate('tanggal', $tanggalNorm)
            ->get();

        foreach ($rows as $row) {
            $rowBatches = array_values(array_unique(array_filter(
                array_map('intval', JadwalMobilisasiService::flattenBatchIds($row->batch_ids ?? [])),
                fn ($id) => $id > 0
            )));
            $overlap = array_values(array_intersect($rowBatches, $ids));
            if ($overlap === []) {
                continue;
            }

            $rowKey = (string) ($row->petugas_key ?? '');
            // Tim sama dengan sampler baru → biarkan (tidak transfer diam-diam).
            if ($newKey !== '' && $rowKey === $newKey) {
                continue;
            }

            $beforePeralatan = JadwalMobilisasiService::normalizePeralatanList($row->peralatan ?? []);
            $remaining = array_values(array_diff($rowBatches, $ids));
            $action = $remaining === [] ? 'sampler_release' : 'sampler_detach';

            $snapshots = is_array($row->snapshots) ? $row->snapshots : [];
            if (Schema::hasColumn('io_peralatan_tim', 'snapshots')) {
                $entry = self::buildSnapshotEntry(
                    $beforePeralatan,
                    $remaining === [] ? [] : $beforePeralatan,
                    $action,
                    $actor,
                    (string) ($row->petugas_sampling ?? ''),
                    $now
                );
                $entry['batch_ids_removed'] = $overlap;
                $entry['batch_ids_remaining'] = $remaining;
                $entry['new_petugas_sampling'] = trim((string) $newPetugasSampling);
                $entry['new_petugas_key'] = $newKey;
                $snapshots[] = $entry;
            }

            if ($remaining === []) {
                $row->batch_ids = [];
                $row->peralatan = [];
                $row->is_active = false;
                $row->updated_by = $actor;
                $row->updated_at = $now;
                if (Schema::hasColumn('io_peralatan_tim', 'snapshots')) {
                    $row->snapshots = $snapshots;
                }
                $row->save();

                MonitoringPeralatanService::syncSedangDigunakanFromPeralatan(
                    $beforePeralatan,
                    [],
                    $actor
                );

                $result['released'][] = [
                    'io_id' => (int) $row->id,
                    'petugas_sampling' => (string) ($row->petugas_sampling ?? ''),
                    'batch_ids_removed' => $overlap,
                ];
            } else {
                $row->batch_ids = $remaining;
                $row->updated_by = $actor;
                $row->updated_at = $now;
                if (Schema::hasColumn('io_peralatan_tim', 'snapshots')) {
                    $row->snapshots = $snapshots;
                }
                $row->save();

                $result['detached'][] = [
                    'io_id' => (int) $row->id,
                    'petugas_sampling' => (string) ($row->petugas_sampling ?? ''),
                    'batch_ids_removed' => $overlap,
                    'batch_ids_remaining' => $remaining,
                ];
            }
        }

        return $result;
    }

    /**
     * Hook SP: rekonsiliasi batch lama + baru (dan tanggal lama bila pindah hari).
     *
     * @param  mixed  $oldBatchIds
     * @param  mixed  $newBatchIds
     * @return array{tanggal: array, tanggal_lama: array|null}
     */
    public static function reconcileAfterSamplerScheduleChange(
        string $tanggal,
        $oldBatchIds,
        $newBatchIds,
        ?string $newPetugasSampling,
        ?string $actor = null,
        ?string $tanggalLama = null
    ): array {
        $out = [
            'tanggal' => ['detached' => [], 'released' => [], 'remapped' => []],
            'tanggal_lama' => null,
        ];

        $oldIds = JadwalMobilisasiService::normalizeBatchIds($oldBatchIds)['ids'] ?? [];
        $newIds = JadwalMobilisasiService::normalizeBatchIds($newBatchIds)['ids'] ?? [];
        // Batch yang perlu dicek di tanggal baru: lama (masih tercatat di IO) + baru.
        $idsOnNewDate = array_values(array_unique(array_merge($oldIds, $newIds)));

        if ($idsOnNewDate !== []) {
            $out['tanggal'] = self::reconcileBatchSamplerChange(
                $tanggal,
                $idsOnNewDate,
                $newPetugasSampling,
                $actor
            );
        }

        $tanggalLamaNorm = $tanggalLama ? Carbon::parse($tanggalLama)->format('Y-m-d') : null;
        $tanggalNorm = Carbon::parse($tanggal)->format('Y-m-d');
        if ($tanggalLamaNorm && $tanggalLamaNorm !== $tanggalNorm && $oldIds !== []) {
            // Pindah tanggal: lepaskan batch lama dari IO di hari lama (sampler baru bukan pemilik hari lama).
            $out['tanggal_lama'] = self::reconcileBatchSamplerChange(
                $tanggalLamaNorm,
                $oldIds,
                null, // force detach dari siapa pun yang memegang batch lama
                $actor
            );
        }

        return $out;
    }

    /**
     * Safety net IO: detach batch yang sudah tidak match sampler jadwal aktif /
     * batch jadwal sudah nonaktif (orphan setelah re-insert SP).
     *
     * @param  mixed  $batchIds  batch yang sedang dibuka di modal IO (konteks)
     * @return array{changed: bool, detached: list<array>, released: list<array>, message: string|null}
     */
    public static function reconcileStaleOwnershipForBatches(
        string $tanggal,
        $batchIds = null,
        ?string $actor = null
    ): array {
        $empty = [
            'changed' => false,
            'detached' => [],
            'released' => [],
            'message' => null,
        ];
        if (!Schema::hasTable('io_peralatan_tim')) {
            return $empty;
        }

        $tanggalNorm = Carbon::parse($tanggal)->format('Y-m-d');
        $contextIds = JadwalMobilisasiService::normalizeBatchIds($batchIds)['ids'] ?? [];

        $rows = IoPeralatanTim::query()
            ->where('is_active', true)
            ->whereDate('tanggal', $tanggalNorm)
            ->get();

        $detached = [];
        $released = [];
        $now = Carbon::now()->format('Y-m-d H:i:s');

        // Flatten batch ids (bisa tersimpan sebagai "175637,175638" per elemen).
        $allBatchIds = [];
        foreach ($rows as $row) {
            foreach (JadwalMobilisasiService::flattenBatchIds($row->batch_ids ?? []) as $id) {
                $allBatchIds[] = (int) $id;
            }
        }
        $allBatchIds = array_values(array_unique(array_filter($allBatchIds, fn ($id) => $id > 0)));
        if ($allBatchIds === []) {
            return $empty;
        }

        // Map id jadwal aktif → nama sampler (bukan petugas_key tim).
        // Jadwal.sampler = 1 orang; io petugas_key = hash seluruh tim → jangan dibanding hash-ke-hash.
        $activeSamplerNameById = [];
        if (Schema::hasTable('jadwal')) {
            $jadwalRows = Jadwal::query()
                ->whereIn('id', $allBatchIds)
                ->where('is_active', true)
                ->get(['id', 'sampler']);
            foreach ($jadwalRows as $j) {
                $names = self::petugasNameList($j->sampler ?? '');
                $activeSamplerNameById[(int) $j->id] = $names[0] ?? '';
            }
        }

        foreach ($rows as $row) {
            $rowBatches = JadwalMobilisasiService::flattenBatchIds($row->batch_ids ?? []);
            $rowBatches = array_values(array_unique(array_filter(array_map('intval', $rowBatches), fn ($id) => $id > 0)));
            if ($rowBatches === []) {
                continue;
            }

            $stale = [];
            $teamNames = array_fill_keys(self::petugasNameList($row->petugas_sampling ?? ''), true);
            foreach ($rowBatches as $bid) {
                // Orphan: jadwal id sudah tidak aktif / tidak ada.
                if (!isset($activeSamplerNameById[$bid])) {
                    $stale[] = $bid;
                    continue;
                }
                $samplerName = $activeSamplerNameById[$bid];
                // Masih milik tim ini jika nama sampler ada di daftar petugas_sampling tim.
                if ($teamNames !== [] && $samplerName !== '' && !isset($teamNames[$samplerName])) {
                    $stale[] = $bid;
                }
            }
            $stale = array_values(array_unique($stale));
            if ($stale === []) {
                continue;
            }

            // Hanya laporkan ke UI jika stale menyentuh batch konteks modal / semua bila konteks kosong.
            $touchesContext = $contextIds === [] || array_intersect($stale, $contextIds) !== [];

            $beforePeralatan = JadwalMobilisasiService::normalizePeralatanList($row->peralatan ?? []);
            $remaining = array_values(array_diff($rowBatches, $stale));
            $action = $remaining === [] ? 'sampler_release' : 'sampler_detach';

            $snapshots = is_array($row->snapshots) ? $row->snapshots : [];
            if (Schema::hasColumn('io_peralatan_tim', 'snapshots')) {
                $entry = self::buildSnapshotEntry(
                    $beforePeralatan,
                    $remaining === [] ? [] : $beforePeralatan,
                    $action,
                    $actor,
                    (string) ($row->petugas_sampling ?? ''),
                    $now
                );
                $entry['batch_ids_removed'] = $stale;
                $entry['batch_ids_remaining'] = $remaining;
                $entry['reason'] = 'stale_ownership';
                $snapshots[] = $entry;
            }

            if ($remaining === []) {
                $row->batch_ids = [];
                $row->peralatan = [];
                $row->is_active = false;
                $row->updated_by = $actor;
                $row->updated_at = $now;
                if (Schema::hasColumn('io_peralatan_tim', 'snapshots')) {
                    $row->snapshots = $snapshots;
                }
                $row->save();
                MonitoringPeralatanService::syncSedangDigunakanFromPeralatan(
                    $beforePeralatan,
                    [],
                    $actor
                );
                if ($touchesContext) {
                    $released[] = [
                        'io_id' => (int) $row->id,
                        'petugas_sampling' => (string) ($row->petugas_sampling ?? ''),
                        'batch_ids_removed' => $stale,
                    ];
                }
            } else {
                $row->batch_ids = $remaining;
                $row->updated_by = $actor;
                $row->updated_at = $now;
                if (Schema::hasColumn('io_peralatan_tim', 'snapshots')) {
                    $row->snapshots = $snapshots;
                }
                $row->save();
                if ($touchesContext) {
                    $detached[] = [
                        'io_id' => (int) $row->id,
                        'petugas_sampling' => (string) ($row->petugas_sampling ?? ''),
                        'batch_ids_removed' => $stale,
                        'batch_ids_remaining' => $remaining,
                    ];
                }
            }
        }

        $changed = $detached !== [] || $released !== [];
        $message = null;
        if ($changed) {
            $message = 'Alat sempat tercatat di tim lama dan sudah dilepas otomatis. Silakan pilih ulang.';
        }

        return [
            'changed' => $changed,
            'detached' => $detached,
            'released' => $released,
            'message' => $message,
        ];
    }
}
