<?php

namespace App\Services;

use App\Support\InvestigasiAnalisisKasus;
use App\Support\InvestigasiStatusCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Investigasi Awal (F1) — Case Code & persistensi header.
 */
final class BakInvestigasiService
{
    public const JENIS_ST = 'st';

    public const JENIS_SP = 'sp';

    /**
     * Case Code: tepat 9 karakter HEX (0-9, A-F), unik di bak_investigasi.no_document.
     */
    public static function generateUniqueNoDocument(int $maxAttempts = 80): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = strtoupper(bin2hex(random_bytes(5)));
            $code = substr($code, 0, 9);

            if (!Schema::hasTable('bak_investigasi')) {
                return $code;
            }

            $exists = DB::table('bak_investigasi')
                ->where('no_document', $code)
                ->exists();

            if (!$exists) {
                return $code;
            }
        }

        return strtoupper(substr(bin2hex(random_bytes(5)), 0, 9));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function mapHeaderRow(array $input, ?string $noDocument = null): array
    {
        $now = Carbon::now();

        return [
            'no_document' => $noDocument,
            'status_case' => self::normalizeStatusCase($input['status_case'] ?? null),
            'kode_dokumen' => $input['kode_dokumen'] ?? 'ISL-FIAW-10-T1.Rev0',
            'judul_kejadian' => $input['judul_kejadian'] ?? null,
            'tanggal_kejadian' => $input['tanggal_kejadian'] ?? null,
            'pukul_kejadian' => $input['pukul_kejadian'] ?? null,
            'lokasi_kejadian' => $input['lokasi_kejadian'] ?? null,
            'tgl_bak_pertama' => $input['tgl_bak_pertama'] ?? null,
            'tgl_bak_terakhir' => $input['tgl_bak_terakhir'] ?? null,
            'no_bak_pertama' => $input['no_bak_pertama'] ?? null,
            'no_bak_terakhir' => $input['no_bak_terakhir'] ?? null,
            'jumlah_bukti' => isset($input['jumlah_bukti']) ? (int) $input['jumlah_bukti'] : null,
            'jenis_bukti' => $input['jenis_bukti'] ?? null,
            'kronologi_ringkasan' => $input['kronologi_ringkasan'] ?? null,
            'kronologi_what' => $input['kronologi_what'] ?? null,
            'kronologi_who' => $input['kronologi_who'] ?? null,
            'kronologi_when' => $input['kronologi_when'] ?? null,
            'kronologi_where' => $input['kronologi_where'] ?? null,
            'kronologi_why' => $input['kronologi_why'] ?? null,
            'kronologi_how' => $input['kronologi_how'] ?? null,
            'kronologi_catatan_khusus' => $input['kronologi_catatan_khusus'] ?? null,
            'analisis_kasus' => self::encodeAnalisisKasus($input),
            'status' => $input['status'] ?? 'draft',
            'created_by' => $input['created_by'] ?? null,
            'updated_by' => $input['updated_by'] ?? null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function replacePihak(int $bakInvestigasiId, array $rows): void
    {
        if (!Schema::hasTable('bak_investigasi_pihak')) {
            return;
        }

        DB::table('bak_investigasi_pihak')
            ->where('bak_investigasi_id', $bakInvestigasiId)
            ->delete();

        $now = Carbon::now();

        foreach ($rows as $index => $row) {
            $insert = [
                'bak_investigasi_id' => $bakInvestigasiId,
                'urutan' => (int) ($row['urutan'] ?? ($index + 1)),
                'nama' => $row['nama'] ?? null,
                'divisi' => $row['divisi'] ?? null,
                'peran' => $row['peran'] ?? null,
                'keterangan' => $row['keterangan'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
                'karyawan_id' => $row['karyawan_id'] ?? null,
            ];
            DB::table('bak_investigasi_pihak')->insert($insert);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function replaceTindakLanjut(int $bakInvestigasiId, array $rows): void
    {
        if (!Schema::hasTable('bak_investigasi_tindak_lanjut')) {
            return;
        }

        DB::table('bak_investigasi_tindak_lanjut')
            ->where('bak_investigasi_id', $bakInvestigasiId)
            ->delete();

        $now = Carbon::now();
        foreach ($rows as $index => $row) {
            DB::table('bak_investigasi_tindak_lanjut')->insert([
                'bak_investigasi_id' => $bakInvestigasiId,
                'urutan' => (int) ($row['urutan'] ?? ($index + 1)),
                'corrective_action' => $row['corrective_action'] ?? null,
                'preventive_action' => $row['preventive_action'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public static function actorFromRequest($request): ?string
    {
        $user = $request->user ?? $request->auth ?? null;
        if (is_array($user)) {
            return trim((string) ($user['nama_lengkap'] ?? $user['name'] ?? '')) ?: null;
        }

        return null;
    }

    public static function actorKaryawanIdFromRequest($request): ?int
    {
        if (isset($request->user_id) && (int) $request->user_id > 0) {
            return (int) $request->user_id;
        }

        return null;
    }

    /**
     * Hanya field A–F untuk simpan draft (tanpa G.1).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function mapDraftHeaderUpdate(array $input): array
    {
        $g1Keys = [
            'status_case',
            'rekomendasi_alur',
            'keputusan_sanksi',
            'sanksi_st',
            'sanksi_sp',
            'sanksi_ganti_rugi',
            'sanksi_lainnya',
            'sanksi_st_detail',
            'sanksi_surat_teguran_id',
            'sanksi_surat_teguran_ids',
            'sanksi_sp_detail',
            'sanksi_surat_peringatan_id',
            'sanksi_surat_peringatan_ids',
            'sanksi_ganti_rugi_detail',
            'sanksi_lainnya_detail',
            'catatan_tambahan',
            'tindak_lanjut_awal',
        ];

        $base = self::mapHeaderRow($input, null);
        foreach ($g1Keys as $key) {
            unset($base[$key]);
        }

        return $base;
    }

    /**
     * Normalisasi daftar ID surat (array / JSON / comma-separated / legacy single ID) ke list<int> unik terurut.
     *
     * @param  mixed  $rawIds
     * @param  mixed  $legacySingleId
     * @return list<int>
     */
    public static function normalizeSuratIds($rawIds, $legacySingleId = null): array
    {
        $items = [];
        if (is_array($rawIds)) {
            $items = $rawIds;
        } elseif (is_string($rawIds)) {
            $trimmed = trim($rawIds);
            if ($trimmed !== '') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $items = $decoded;
                } else {
                    $items = explode(',', $trimmed);
                }
            }
        } elseif (is_numeric($rawIds)) {
            $items = [$rawIds];
        }

        if ($items === [] && $legacySingleId !== null && $legacySingleId !== '') {
            $items = [$legacySingleId];
        }

        $seen = [];
        $out = [];
        foreach ($items as $item) {
            $id = (int) $item;
            if ($id > 0 && !isset($seen[$id])) {
                $seen[$id] = true;
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * Daftar ID surat (ST / SP) yang tertaut ke satu investigasi.
     *
     * @return list<int>
     */
    public static function getLinkedSuratIds(int $investigasiId, string $jenis, ?object $row = null): array
    {
        if ($investigasiId <= 0) {
            return [];
        }

        $ids = [];
        if (Schema::hasTable('bak_investigasi_surat_sanksi')) {
            $ids = DB::table('bak_investigasi_surat_sanksi')
                ->where('bak_investigasi_id', $investigasiId)
                ->where('jenis', $jenis)
                ->orderBy('id')
                ->pluck('surat_id')
                ->map(static fn($v) => (int) $v)
                ->filter(static fn($v) => $v > 0)
                ->values()
                ->all();
        }

        if ($ids !== []) {
            return array_values(array_unique($ids));
        }

        if ($row !== null) {
            if ($jenis === self::JENIS_ST && !empty($row->sanksi_st) && !empty($row->sanksi_surat_teguran_id)) {
                return [(int) $row->sanksi_surat_teguran_id];
            }
            if ($jenis === self::JENIS_SP && !empty($row->sanksi_sp) && !empty($row->sanksi_surat_peringatan_id)) {
                return [(int) $row->sanksi_surat_peringatan_id];
            }
        }

        return [];
    }

    /**
     * Daftar ID surat (ST / SP) yang sudah dipakai di investigasi aktif lain (status != cancelled).
     *
     * @return list<int>
     */
    public static function usedSuratIds(string $jenis, int $excludeInvestigasiId = 0): array
    {
        if (!Schema::hasTable('bak_investigasi')) {
            return [];
        }

        $used = [];

        if (Schema::hasTable('bak_investigasi_surat_sanksi')) {
            $pivotQuery = DB::table('bak_investigasi_surat_sanksi as ss')
                ->join('bak_investigasi as inv', 'inv.id', '=', 'ss.bak_investigasi_id')
                ->where('ss.jenis', $jenis)
                ->where('inv.is_active', true)
                ->where('inv.status', '!=', 'cancelled');

            if ($excludeInvestigasiId > 0) {
                $pivotQuery->where('ss.bak_investigasi_id', '!=', $excludeInvestigasiId);
            }

            $used = array_merge(
                $used,
                $pivotQuery->pluck('ss.surat_id')->map(static fn($v) => (int) $v)->all()
            );
        }

        $legacyCol = $jenis === self::JENIS_ST ? 'sanksi_surat_teguran_id' : 'sanksi_surat_peringatan_id';
        $flagCol = $jenis === self::JENIS_ST ? 'sanksi_st' : 'sanksi_sp';
        if (Schema::hasColumn('bak_investigasi', $legacyCol)) {
            $legacyQuery = DB::table('bak_investigasi')
                ->where('is_active', true)
                ->where('status', '!=', 'cancelled')
                ->where($flagCol, true)
                ->whereNotNull($legacyCol)
                ->where($legacyCol, '>', 0);

            if ($excludeInvestigasiId > 0) {
                $legacyQuery->where('id', '!=', $excludeInvestigasiId);
            }

            $used = array_merge(
                $used,
                $legacyQuery->pluck($legacyCol)->map(static fn($v) => (int) $v)->all()
            );
        }

        return array_values(array_unique(array_filter($used, static fn($id) => $id > 0)));
    }

    /**
     * @param  list<int>  $stIds
     * @param  list<int>  $spIds
     */
    public static function syncSuratSanksiLinks(int $investigasiId, array $stIds, array $spIds): void
    {
        if ($investigasiId <= 0 || !Schema::hasTable('bak_investigasi_surat_sanksi')) {
            return;
        }

        DB::table('bak_investigasi_surat_sanksi')
            ->where('bak_investigasi_id', $investigasiId)
            ->delete();

        $now = Carbon::now();
        $rows = [];
        foreach ($stIds as $stId) {
            $rows[] = [
                'bak_investigasi_id' => $investigasiId,
                'jenis' => self::JENIS_ST,
                'surat_id' => $stId,
                'created_at' => $now,
            ];
        }
        foreach ($spIds as $spId) {
            $rows[] = [
                'bak_investigasi_id' => $investigasiId,
                'jenis' => self::JENIS_SP,
                'surat_id' => $spId,
                'created_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('bak_investigasi_surat_sanksi')->insert($rows);
        }
    }

    /**
     * @param  array<string, mixed>  $g1
     */
    public static function applyG1Fields(int $investigasiId, array $g1): void
    {
        $statusCase = InvestigasiStatusCase::resolveFromG1($g1);
        $legacy = InvestigasiStatusCase::legacyFieldsFromStatusCase($statusCase);
        $withSanksi = InvestigasiStatusCase::requiresSanksiDetail($statusCase);

        $sanksiSt = $withSanksi && self::inputBool($g1, 'sanksi_st');
        $sanksiSp = $withSanksi && self::inputBool($g1, 'sanksi_sp');
        $sanksiGantiRugi = $withSanksi && self::inputBool($g1, 'sanksi_ganti_rugi');
        $sanksiLainnya = $withSanksi && self::inputBool($g1, 'sanksi_lainnya');

        $stIds = $sanksiSt
            ? self::normalizeSuratIds($g1['sanksi_surat_teguran_ids'] ?? null, $g1['sanksi_surat_teguran_id'] ?? null)
            : [];
        $spIds = $sanksiSp
            ? self::normalizeSuratIds($g1['sanksi_surat_peringatan_ids'] ?? null, $g1['sanksi_surat_peringatan_id'] ?? null)
            : [];

        self::syncSuratSanksiLinks($investigasiId, $stIds, $spIds);

        $cleanText = static function ($value): ?string {
            $trimmed = trim((string) ($value ?? ''));

            return $trimmed !== '' ? $trimmed : null;
        };

        DB::table('bak_investigasi')
            ->where('id', $investigasiId)
            ->update([
                'status_case' => $statusCase,
                'rekomendasi_alur' => $legacy['rekomendasi_alur'],
                'keputusan_sanksi' => $legacy['keputusan_sanksi'],
                'tindak_lanjut_awal' => $legacy['tindak_lanjut_awal'],
                'sanksi_st' => $sanksiSt,
                'sanksi_sp' => $sanksiSp,
                'sanksi_ganti_rugi' => $sanksiGantiRugi,
                'sanksi_lainnya' => $sanksiLainnya,
                'sanksi_surat_teguran_id' => $stIds[0] ?? null,
                'sanksi_surat_peringatan_id' => $spIds[0] ?? null,
                'sanksi_st_detail' => $sanksiSt && $stIds !== []
                    ? self::resolveSuratTeguranNomorList($stIds, $cleanText($g1['sanksi_st_detail'] ?? null))
                    : ($sanksiSt ? $cleanText($g1['sanksi_st_detail'] ?? null) : null),
                'sanksi_sp_detail' => $sanksiSp && $spIds !== []
                    ? self::resolveSuratPeringatanLabelList($spIds, $cleanText($g1['sanksi_sp_detail'] ?? null))
                    : ($sanksiSp ? $cleanText($g1['sanksi_sp_detail'] ?? null) : null),
                'sanksi_ganti_rugi_detail' => $sanksiGantiRugi ? $cleanText($g1['sanksi_ganti_rugi_detail'] ?? null) : null,
                'sanksi_lainnya_detail' => $sanksiLainnya ? $cleanText($g1['sanksi_lainnya_detail'] ?? null) : null,
                'catatan_tambahan' => $cleanText($g1['catatan_tambahan'] ?? null),
                'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $g1
     */
    public static function validateG1(array $g1, int $investigasiId = 0): ?string
    {
        $statusCase = InvestigasiStatusCase::resolveFromG1($g1);
        if ($statusCase === null) {
            return 'Pilih tindak lanjut awal (salah satu: Internal Disciplinary Tanpa/Dengan Sanksi, atau Komite Manajemen).';
        }

        if (InvestigasiStatusCase::requiresSanksiDetail($statusCase)) {
            $sanksiSt = self::inputBool($g1, 'sanksi_st');
            $sanksiSp = self::inputBool($g1, 'sanksi_sp');
            $sanksiGantiRugi = self::inputBool($g1, 'sanksi_ganti_rugi');
            $sanksiLainnya = self::inputBool($g1, 'sanksi_lainnya');

            $any = $sanksiSt || $sanksiSp || $sanksiGantiRugi || $sanksiLainnya;
            if (!$any) {
                return 'Pilih minimal satu jenis sanksi.';
            }

            if ($sanksiSt) {
                $stIds = self::normalizeSuratIds(
                    $g1['sanksi_surat_teguran_ids'] ?? null,
                    $g1['sanksi_surat_teguran_id'] ?? null
                );
                if ($stIds === []) {
                    return 'Pilih minimal satu surat teguran dari daftar.';
                }
                if (!self::allSuratTeguranExist($stIds)) {
                    return 'Satu atau lebih surat teguran tidak ditemukan atau tidak aktif.';
                }
                $usedSt = self::usedSuratIds(self::JENIS_ST, $investigasiId);
                $conflictSt = array_values(array_intersect($stIds, $usedSt));
                if ($conflictSt !== []) {
                    $label = self::resolveSuratTeguranNomorList($conflictSt, null);

                    return 'Surat teguran sudah digunakan di investigasi lain' . ($label ? ': ' . $label : '.');
                }
            }

            if ($sanksiSp) {
                $spIds = self::normalizeSuratIds(
                    $g1['sanksi_surat_peringatan_ids'] ?? null,
                    $g1['sanksi_surat_peringatan_id'] ?? null
                );
                if ($spIds === []) {
                    return 'Pilih minimal satu surat peringatan dari daftar.';
                }
                if (!self::allSuratPeringatanExist($spIds)) {
                    return 'Satu atau lebih surat peringatan tidak ditemukan atau tidak aktif.';
                }
                $usedSp = self::usedSuratIds(self::JENIS_SP, $investigasiId);
                $conflictSp = array_values(array_intersect($spIds, $usedSp));
                if ($conflictSp !== []) {
                    $label = self::resolveSuratPeringatanLabelList($conflictSp, null);

                    return 'Surat peringatan sudah digunakan di investigasi lain' . ($label ? ': ' . $label : '.');
                }
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $ids
     */
    private static function allSuratTeguranExist(array $ids): bool
    {
        if ($ids === [] || !Schema::hasTable('surat_teguran_disipliner')) {
            return false;
        }

        $count = (int) DB::table('surat_teguran_disipliner')
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereIn('status', ['draft', 'issued'])
            ->count();

        return $count === count($ids);
    }

    /**
     * @param  list<int>  $ids
     */
    private static function allSuratPeringatanExist(array $ids): bool
    {
        if ($ids === [] || !Schema::hasTable('surat_peringatan_disipliner')) {
            return false;
        }

        $count = (int) DB::table('surat_peringatan_disipliner')
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereIn('status', ['draft', 'issued'])
            ->count();

        return $count === count($ids);
    }

    /**
     * @param  list<int>  $ids
     */
    private static function resolveSuratTeguranNomorList(array $ids, ?string $fallback): ?string
    {
        if ($ids === [] || !Schema::hasTable('surat_teguran_disipliner')) {
            return trim((string) ($fallback ?? '')) ?: null;
        }

        $byId = DB::table('surat_teguran_disipliner')
            ->whereIn('id', $ids)
            ->pluck('nomor_surat', 'id');

        $labels = [];
        foreach ($ids as $id) {
            $nomor = trim((string) ($byId[$id] ?? ''));
            if ($nomor !== '') {
                $labels[] = $nomor;
            }
        }

        if ($labels !== []) {
            return implode(', ', $labels);
        }

        return trim((string) ($fallback ?? '')) ?: null;
    }

    /**
     * @param  list<int>  $ids
     */
    private static function resolveSuratPeringatanLabelList(array $ids, ?string $fallback): ?string
    {
        if ($ids === [] || !Schema::hasTable('surat_peringatan_disipliner')) {
            return trim((string) ($fallback ?? '')) ?: null;
        }

        $byId = DB::table('surat_peringatan_disipliner')
            ->whereIn('id', $ids)
            ->get(['id', 'nomor_surat', 'jenis_peringatan'])
            ->keyBy('id');

        $labels = [];
        foreach ($ids as $id) {
            $row = $byId->get($id);
            if ($row === null) {
                continue;
            }
            $jenis = trim((string) ($row->jenis_peringatan ?? ''));
            $nomor = trim((string) ($row->nomor_surat ?? ''));
            if ($nomor !== '' && $jenis !== '') {
                $labels[] = $jenis . ' — ' . $nomor;
            } elseif ($nomor !== '') {
                $labels[] = $nomor;
            }
        }

        if ($labels !== []) {
            return implode(', ', $labels);
        }

        return trim((string) ($fallback ?? '')) ?: null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function validateAnalisisInput(array $input): ?string
    {
        $built = InvestigasiAnalisisKasus::buildFromInput($input);

        return InvestigasiAnalisisKasus::validateStructure($built);
    }

    /**
     * @param  array<string, mixed>|object  $row
     * @return array<string, mixed>
     */
    public static function decodeAnalisisKasus($row): array
    {
        $raw = is_array($row) ? ($row['analisis_kasus'] ?? null) : ($row->analisis_kasus ?? null);
        if ($raw !== null && $raw !== '') {
            return InvestigasiAnalisisKasus::decodeFromStorage($raw);
        }

        if (self::hasLegacyAnalisisColumns()) {
            $legacy = self::legacyAnalisisFromFlatRow($row);
            $legacy['v'] = InvestigasiAnalisisKasus::VERSION;

            return InvestigasiAnalisisKasus::normalize($legacy);
        }

        return InvestigasiAnalisisKasus::empty();
    }

    public static function summarizePenyebabLabel($row): string
    {
        return InvestigasiAnalisisKasus::summarizePenyebabForDisplay(
            self::decodeAnalisisKasus($row)
        );
    }

    public static function summarizeDampakLabel($row): string
    {
        return InvestigasiAnalisisKasus::summarizeDampakForDisplay(
            self::decodeAnalisisKasus($row)
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function encodeAnalisisKasus(array $input): ?string
    {
        return InvestigasiAnalisisKasus::encode($input);
    }

    /**
     * @param  array<string, mixed>|object  $row
     * @return array<string, mixed>
     */
    private static function legacyAnalisisFromFlatRow($row): array
    {
        $get = static function ($key) use ($row) {
            if (is_array($row)) {
                return $row[$key] ?? null;
            }

            return $row->{$key} ?? null;
        };

        return [
            'penyebab' => [
                'hmf' => ['on' => self::inputBoolValue($get('penyebab_hmf')), 'note' => $get('penyebab_hmf_keterangan')],
                'sys' => ['on' => self::inputBoolValue($get('penyebab_sys')), 'note' => $get('penyebab_sys_keterangan')],
                'tec' => ['on' => self::inputBoolValue($get('penyebab_tec')), 'note' => $get('penyebab_tec_keterangan')],
                'ext' => ['on' => self::inputBoolValue($get('penyebab_ext')), 'note' => $get('penyebab_ext_keterangan')],
                'lainnya' => ['on' => self::inputBoolValue($get('penyebab_lainnya')), 'note' => $get('penyebab_lainnya_keterangan')],
            ],
            'dampak' => [
                'fin' => ['on' => self::inputBoolValue($get('dampak_fin')), 'aset' => self::inputBoolValue($get('dampak_fin_aset'))],
                'ops' => ['on' => self::inputBoolValue($get('dampak_ops')), 'delay_layanan' => self::inputBoolValue($get('dampak_ops_delay_layanan'))],
                'hse' => [
                    'on' => self::inputBoolValue($get('dampak_hse')),
                    'near_miss' => self::inputBoolValue($get('dampak_hse_near_miss')),
                    'injury' => self::inputBoolValue($get('dampak_hse_injury')),
                    'fatality' => self::inputBoolValue($get('dampak_hse_fatality')),
                ],
                'leg' => ['on' => self::inputBoolValue($get('dampak_leg')), 'risiko_sanksi' => self::inputBoolValue($get('dampak_leg_risiko_sanksi'))],
                'lainnya' => ['on' => self::inputBoolValue($get('dampak_lainnya')), 'note' => $get('dampak_lainnya_keterangan')],
                'catatan_khusus' => $get('dampak_catatan_khusus'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function inputBool(array $input, string $key): bool
    {
        return self::inputBoolValue($input[$key] ?? false);
    }

    private static function inputBoolValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private static function hasLegacyAnalisisColumns(): bool
    {
        return Schema::hasTable('bak_investigasi')
            && Schema::hasColumn('bak_investigasi', 'penyebab_hmf');
    }

    /** ENUM MySQL tidak menerima string kosong — hanya DSC-TS | KM | DSC-DS atau NULL. */
    private static function normalizeStatusCase($value): ?string
    {
        return InvestigasiStatusCase::normalize($value);
    }
}
