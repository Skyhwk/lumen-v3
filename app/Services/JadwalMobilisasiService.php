<?php

namespace App\Services;

use App\Models\GaAsset;
use App\Models\JadwalMobilisasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JadwalMobilisasiService
{
    public const PRESET_TUJUAN_OPTIONS = [
        'Monitoring/Pemantauan Lingkungan',
        'Pengumpulan Data Rona Awal Lingkungan',
        'Pengawasan/Penegakkan Hukum',
        'Penelitian di Bidang Lingkungan',
    ];

    public const KETERANGAN_OPS_DIR = 'keterangan_operasional';

    /**
     * Normalisasi array batch ID jadwal menjadi array integer berurutan & string CSV.
     *
     * @param array|string|int|null $batchIds
     * @return array{ids: list<int>, csv: string}
     */
    public static function normalizeBatchIds($batchIds): array
    {
        if (is_string($batchIds)) {
            $raw = explode(',', $batchIds);
        } elseif (is_array($batchIds)) {
            $raw = $batchIds;
        } elseif ($batchIds !== null && $batchIds !== '') {
            $raw = [$batchIds];
        } else {
            $raw = [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $raw), fn ($v) => $v > 0)));

        return [
            'ids' => $ids,
            'csv' => implode(',', $ids),
        ];
    }

    /**
     * Simpan / perbarui data mobilisasi & peralatan sampling untuk 1 group batch_id.
     *
     * Konsep:
     * - Jika sudah ada row aktif (berdasarkan $oldBatchIds saat update jadwal atau $newBatchIds),
     *   maka cukup UPDATE row yang sudah ada (termasuk mengupdate batch_id ke ID baru) tanpa mematikan row lama.
     * - Row hanya dimatikan (is_active = false) ketika jadwal benar-benar di-cancel dari Sampling Plan.
     * - Keterangan Operasional (HTML dari TinyMCE) disimpan sebagai file .txt di public/keterangan_operasional/,
     *   sehingga di database hanya menyimpan namafile.txt.
     */
    public static function saveForBatch(
        $newBatchIds,
        array $payload,
        ?string $actor = null,
        $oldBatchIds = null
    ): ?JadwalMobilisasi {
        if (!Schema::hasTable('jadwal_mobilisasi')) {
            return null;
        }

        $normalizedNew = self::normalizeBatchIds($newBatchIds);
        if (empty($normalizedNew['ids'])) {
            return null;
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');

        // Cari row aktif yang sudah ada (dari batch lama saat update jadwal, atau dari batch baru)
        $existingRecord = null;
        if ($oldBatchIds !== null) {
            $existingRecord = self::findActiveRecordByBatchIds($oldBatchIds);
        }
        if (!$existingRecord) {
            $existingRecord = self::findActiveRecordByBatchIds($normalizedNew['ids']);
        }

        $tujuanRaw = trim((string) ($payload['tujuan_sampling'] ?? ''));
        $tujuanPilihan = trim((string) ($payload['tujuan_sampling_pilihan'] ?? ''));
        $tujuanManual = trim((string) ($payload['tujuan_sampling_manual'] ?? ''));

        if ($tujuanPilihan === '' && $tujuanRaw !== '') {
            if (in_array($tujuanRaw, self::PRESET_TUJUAN_OPTIONS, true)) {
                $tujuanPilihan = $tujuanRaw;
            } else {
                $tujuanPilihan = 'Input Manual';
                $tujuanManual = $tujuanRaw;
            }
        }

        $estimasiJam = isset($payload['estimasi_jam']) ? (int) $payload['estimasi_jam'] : 0;
        $estimasiMenit = isset($payload['estimasi_menit']) ? (int) $payload['estimasi_menit'] : 0;
        $waktuBerangkat = trim((string) ($payload['waktu_berangkat_estimasi'] ?? $payload['waktu_berangkat'] ?? ''));

        if (($estimasiJam === 0 && $estimasiMenit === 0) && $waktuBerangkat !== '') {
            if (preg_match('/(\d+)\s*Jam/i', $waktuBerangkat, $mJam)) {
                $estimasiJam = (int) $mJam[1];
            }
            if (preg_match('/(\d+)\s*Menit/i', $waktuBerangkat, $mMenit)) {
                $estimasiMenit = (int) $mMenit[1];
            }
        }

        if ($waktuBerangkat === '') {
            $parts = [];
            if ($estimasiJam > 0) {
                $parts[] = "{$estimasiJam} Jam";
            }
            if ($estimasiMenit > 0) {
                $parts[] = "{$estimasiMenit} Menit";
            }
            $waktuBerangkat = !empty($parts) ? implode(' ', $parts) : '0 Menit';
        }

        $keteranganAkomodasi = trim((string) ($payload['keterangan_akomodasi'] ?? 'PT INTI SURYA LABORATORIUM'));
        $rutePerjalanan = trim((string) ($payload['rute_perjalanan'] ?? 'TOLL'));
        $estimasiPerjalanan = trim((string) ($payload['estimasi_perjalanan'] ?? ''));
        if ($estimasiPerjalanan === '') {
            $estimasiPerjalanan = "{$waktuBerangkat} dari {$keteranganAkomodasi} & RUTE ({$rutePerjalanan})";
        }

        $peralatanList = self::normalizePeralatan($payload['peralatan_sampling'] ?? []);
        $keteranganHtmlInput = trim((string) ($payload['keterangan_operasional'] ?? $payload['keterangan_operasional_sp'] ?? ''));

        // Simpan isi HTML TinyMCE ke dalam file .txt dan simpan namafile.txt di DB
        $existingSpFile = $existingRecord ? ($existingRecord->keterangan_operasional_sp ?? null) : null;
        $existingOpsFile = $existingRecord ? ($existingRecord->keterangan_operasional ?? null) : null;

        $spFilename = self::writeTxtContent($keteranganHtmlInput, $existingSpFile, 'sp');
        $opsFilename = self::writeTxtContent($keteranganHtmlInput, $existingOpsFile, 'ops');

        $dataAttributes = [
            'id_sampling' => isset($payload['id_sampling']) && $payload['id_sampling'] !== '' ? (int) $payload['id_sampling'] : null,
            'no_quotation' => $payload['no_quotation'] ?? null,
            'tanggal' => !empty($payload['tanggal']) ? Carbon::parse($payload['tanggal'])->format('Y-m-d') : null,
            'warna' => $payload['warna'] ?? null,
            'batch_id' => $normalizedNew['csv'],
            'batch_jadwal_ids' => $normalizedNew['ids'],
            'tujuan_sampling' => $tujuanRaw !== '' ? $tujuanRaw : null,
            'tujuan_sampling_pilihan' => $tujuanPilihan !== '' ? $tujuanPilihan : null,
            'tujuan_sampling_manual' => $tujuanManual !== '' ? $tujuanManual : null,
            'estimasi_jam' => $estimasiJam,
            'estimasi_menit' => $estimasiMenit,
            'waktu_berangkat' => $waktuBerangkat,
            'keterangan_akomodasi' => $keteranganAkomodasi !== '' ? $keteranganAkomodasi : null,
            'rute_perjalanan' => $rutePerjalanan !== '' ? $rutePerjalanan : null,
            'estimasi_perjalanan' => $estimasiPerjalanan !== '' ? $estimasiPerjalanan : null,
            'peralatan_sampling' => $peralatanList,
            'is_active' => true,
            'updated_by' => $actor,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('jadwal_mobilisasi', 'keterangan_operasional_sp')) {
            $dataAttributes['keterangan_operasional_sp'] = $spFilename;
        }
        if (Schema::hasColumn('jadwal_mobilisasi', 'keterangan_operasional')) {
            $dataAttributes['keterangan_operasional'] = $opsFilename;
        }

        // Jika sudah ada record aktif pada group jadwal ini -> cukup UPDATE row yang sama!
        if ($existingRecord) {
            $existingRecord->update($dataAttributes);
            return $existingRecord->fresh();
        }

        // Jika belum ada (saat create jadwal baru / parsial baru) -> INSERT row baru
        $dataAttributes['created_by'] = $actor;
        $dataAttributes['created_at'] = $now;
        if (Schema::hasColumn('jadwal_mobilisasi', 'status_rilis_io')) {
            $dataAttributes['status_rilis_io'] = $payload['status_rilis_io'] ?? 'draft';
        }

        return JadwalMobilisasi::create($dataAttributes);
    }

    /**
     * Nonaktifkan data jadwal_mobilisasi HANYA ketika jadwal benar-benar di-cancel dari Sampling Plan.
     */
    public static function deactivateByBatchIds($batchIds, ?string $actor = null): int
    {
        if (!Schema::hasTable('jadwal_mobilisasi')) {
            return 0;
        }

        $normalized = self::normalizeBatchIds($batchIds);
        if (empty($normalized['ids'])) {
            return 0;
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');

        $query = JadwalMobilisasi::where('is_active', true)
            ->where(function ($q) use ($normalized) {
                $q->where('batch_id', $normalized['csv']);
                foreach ($normalized['ids'] as $jid) {
                    $q->orWhereRaw('FIND_IN_SET(?, batch_id) > 0', [(string) $jid])
                        ->orWhereJsonContains('batch_jadwal_ids', $jid);
                }
            });

        return $query->update([
            'is_active' => false,
            'updated_by' => $actor,
            'updated_at' => $now,
        ]);
    }

    /**
     * Nonaktifkan data jadwal_mobilisasi berdasarkan id_sampling (misal saat direject dari Validator).
     */
    public static function deactivateBySamplingId(int $samplingId, ?string $actor = null): int
    {
        if (!Schema::hasTable('jadwal_mobilisasi') || $samplingId <= 0) {
            return 0;
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');

        return JadwalMobilisasi::where('id_sampling', $samplingId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_by' => $actor,
                'updated_at' => $now,
            ]);
    }

    /**
     * Ambil data jadwal_mobilisasi aktif beserta isi HTML dari file .txt untuk ditampilkan kembali ke frontend.
     */
    public static function findActiveByBatchIds($batchIds): ?array
    {
        $record = self::findActiveRecordByBatchIds($batchIds);
        if (!$record) {
            return null;
        }

        $data = $record->toArray();

        $opsRaw = $data['keterangan_operasional'] ?? null;
        $spRaw = $data['keterangan_operasional_sp'] ?? null;

        $data['keterangan_operasional_file'] = self::isTxtFilename($opsRaw) ? $opsRaw : null;
        $data['keterangan_operasional_sp_file'] = self::isTxtFilename($spRaw) ? $spRaw : null;

        $data['keterangan_operasional'] = self::readTxtContent($opsRaw);
        $data['keterangan_operasional_sp'] = self::readTxtContent($spRaw);

        return $data;
    }

    private static function findActiveRecordByBatchIds($batchIds): ?JadwalMobilisasi
    {
        if (!Schema::hasTable('jadwal_mobilisasi')) {
            return null;
        }

        $normalized = self::normalizeBatchIds($batchIds);
        if (empty($normalized['ids'])) {
            return null;
        }

        return JadwalMobilisasi::where('is_active', true)
            ->where(function ($q) use ($normalized) {
                $q->where('batch_id', $normalized['csv']);
                foreach ($normalized['ids'] as $jid) {
                    $q->orWhereRaw('FIND_IN_SET(?, batch_id) > 0', [(string) $jid])
                        ->orWhereJsonContains('batch_jadwal_ids', $jid);
                }
            })
            ->orderByDesc('id')
            ->first();
    }

    private static function isTxtFilename(?string $value): bool
    {
        if ($value === null) {
            return false;
        }
        $trimmed = trim($value);
        return $trimmed !== '' && preg_match('/^[A-Za-z0-9_.-]+\.txt$/', $trimmed) === 1;
    }

    private static function writeTxtContent(string $htmlContent, ?string $existingFilename = null, string $prefix = 'ops'): ?string
    {
        $plainCheck = trim(strip_tags(html_entity_decode($htmlContent)));
        if ($htmlContent === '' || $plainCheck === '') {
            return null;
        }

        $dirPath = public_path(self::KETERANGAN_OPS_DIR);
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0777, true);
        }

        // Gunakan kembali file .txt yang sudah ada jika merupakan nama file .txt yang valid
        if (self::isTxtFilename($existingFilename)) {
            $filename = trim($existingFilename);
        } else {
            $microtime = str_replace('.', '', (string) microtime(true));
            $filename = $prefix . '_' . $microtime . '_' . mt_rand(100, 999) . '.txt';
        }

        file_put_contents($dirPath . '/' . $filename, $htmlContent);

        return $filename;
    }

    public static function readTxtContent(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);
        if (self::isTxtFilename($trimmed)) {
            $filePath = public_path(self::KETERANGAN_OPS_DIR . '/' . $trimmed);
            if (is_file($filePath)) {
                return (string) file_get_contents($filePath);
            }
            return null;
        }

        // Fallback jika ada data lama yang tersimpan langsung berupa teks/HTML
        return $value;
    }

    private static function normalizePeralatan($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $decodedItem = json_decode($item, true);
                if (is_array($decodedItem)) {
                    $item = $decodedItem;
                }
            }
            if (!is_array($item)) {
                continue;
            }
            $nama = trim((string) ($item['nama_alat'] ?? ''));
            $jumlah = (int) ($item['jumlah_alat'] ?? $item['jumlah'] ?? 0);
            if ($nama !== '' && $jumlah > 0) {
                $entry = [
                    'nama_alat' => $nama,
                    'jumlah_alat' => $jumlah,
                ];
                foreach (['sub_kategori_aset_id', 'kategori_aset_id', 'condition', 'branch_id', 'location_id'] as $metaKey) {
                    if (array_key_exists($metaKey, $item) && $item[$metaKey] !== null && $item[$metaKey] !== '') {
                        $entry[$metaKey] = $item[$metaKey];
                    }
                }
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * Ambil daftar peralatan dari ga_assets:
     * WHERE sub_kategori_aset_id = 10 AND is_active = 1 AND condition = 'normal'
     * GROUP BY name, sub_kategori_aset_id, kategori_aset_id, condition, branch_id, location_id
     * beserta perhitungan sisa ketersediaan pada tanggal tersebut.
     */
    public static function getPeralatanAvailable(?string $tanggal = null, $branchId = null, $excludeBatchIds = null): array
    {
        if (!Schema::hasTable('ga_assets')) {
            return [];
        }

        $query = GaAsset::query()
            ->select(
                'name',
                'sub_kategori_aset_id',
                'kategori_aset_id',
                'condition',
                'branch_id',
                'location_id',
                DB::raw('COUNT(*) as total_unit')
            )
            ->where('sub_kategori_aset_id', 10)
            ->where('is_active', 1)
            ->where('condition', 'normal');

        if ($branchId !== null && $branchId !== '' && (!is_array($branchId) || count(array_filter($branchId)) > 0)) {
            if (is_array($branchId)) {
                $cleanBranchIds = array_values(array_filter(array_map('intval', $branchId), fn ($v) => $v > 0));
                if (!empty($cleanBranchIds)) {
                    $query->whereIn('branch_id', $cleanBranchIds);
                }
            } else {
                $query->where('branch_id', (int) $branchId);
            }
        }

        $rows = $query
            ->groupBy(
                'name',
                'sub_kategori_aset_id',
                'kategori_aset_id',
                'condition',
                'branch_id',
                'location_id'
            )
            ->orderBy('name', 'asc')
            ->get();

        // Hitung jumlah alat yang sudah terpakai di tanggal yang sama pada jadwal_mobilisasi aktif
        $usedByToolKey = [];
        if ($tanggal && Schema::hasTable('jadwal_mobilisasi')) {
            $normalizedExclude = self::normalizeBatchIds($excludeBatchIds);
            $activeMobilisasi = JadwalMobilisasi::where('is_active', true)
                ->whereDate('tanggal', $tanggal)
                ->get();

            foreach ($activeMobilisasi as $mob) {
                if (!empty($normalizedExclude['ids'])) {
                    $mobBatch = self::normalizeBatchIds($mob->batch_id);
                    if (!empty(array_intersect($normalizedExclude['ids'], $mobBatch['ids']))) {
                        continue;
                    }
                }

                $peralatan = $mob->peralatan_sampling;
                if (is_string($peralatan)) {
                    $peralatan = json_decode($peralatan, true);
                }
                if (!is_array($peralatan)) {
                    continue;
                }

                foreach ($peralatan as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $namaAlat = trim((string) ($item['nama_alat'] ?? ''));
                    $qty = (int) ($item['jumlah_alat'] ?? $item['jumlah'] ?? 0);
                    $itemBranch = isset($item['branch_id']) && $item['branch_id'] !== '' ? (int) $item['branch_id'] : null;
                    $itemLoc = isset($item['location_id']) && $item['location_id'] !== '' ? (int) $item['location_id'] : null;

                    if ($namaAlat !== '' && $qty > 0) {
                        $specificKey = $namaAlat . '|' . ($itemBranch ?? '*') . '|' . ($itemLoc ?? '*');
                        $usedByToolKey[$specificKey] = ($usedByToolKey[$specificKey] ?? 0) + $qty;
                        $usedByToolKey[$namaAlat] = ($usedByToolKey[$namaAlat] ?? 0) + $qty;
                    }
                }
            }
        }

        return $rows->map(function ($row) use ($usedByToolKey) {
            $total = (int) $row->total_unit;
            $specificKey = $row->name . '|' . ($row->branch_id ?? '*') . '|' . ($row->location_id ?? '*');
            $used = $usedByToolKey[$specificKey] ?? ($usedByToolKey[$row->name] ?? 0);
            $sisa = max(0, $total - (int) $used);

            return [
                'id' => $row->name,
                'nama' => $row->name,
                'name' => $row->name,
                'sub_kategori_aset_id' => (int) $row->sub_kategori_aset_id,
                'kategori_aset_id' => (int) $row->kategori_aset_id,
                'condition' => $row->condition,
                'branch_id' => $row->branch_id !== null ? (int) $row->branch_id : null,
                'location_id' => $row->location_id !== null ? (int) $row->location_id : null,
                'total' => $total,
                'terpakai' => (int) $used,
                'stok' => $sisa,
            ];
        })->values()->all();
    }
}
