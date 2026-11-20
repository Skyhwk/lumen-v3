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
            DB::table('bak_investigasi_pihak')->insert([
                'bak_investigasi_id' => $bakInvestigasiId,
                'urutan' => (int) ($row['urutan'] ?? ($index + 1)),
                'nama' => $row['nama'] ?? null,
                'divisi' => $row['divisi'] ?? null,
                'peran' => $row['peran'] ?? null,
                'keterangan' => $row['keterangan'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
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
            'sanksi_sp_detail',
            'sanksi_surat_peringatan_id',
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
     * @param  array<string, mixed>  $g1
     */
    public static function applyG1Fields(int $investigasiId, array $g1): void
    {
        $statusCase = InvestigasiStatusCase::resolveFromG1($g1);
        $legacy = InvestigasiStatusCase::legacyFieldsFromStatusCase($statusCase);
        $withSanksi = InvestigasiStatusCase::requiresSanksiDetail($statusCase);

        $stId = $withSanksi && !empty($g1['sanksi_st'])
            ? (int) ($g1['sanksi_surat_teguran_id'] ?? 0)
            : 0;
        $spId = $withSanksi && !empty($g1['sanksi_sp'])
            ? (int) ($g1['sanksi_surat_peringatan_id'] ?? 0)
            : 0;

        DB::table('bak_investigasi')
            ->where('id', $investigasiId)
            ->update([
                'status_case' => $statusCase,
                'rekomendasi_alur' => $legacy['rekomendasi_alur'],
                'keputusan_sanksi' => $legacy['keputusan_sanksi'],
                'tindak_lanjut_awal' => $legacy['tindak_lanjut_awal'],
                'sanksi_st' => $withSanksi && !empty($g1['sanksi_st']),
                'sanksi_sp' => $withSanksi && !empty($g1['sanksi_sp']),
                'sanksi_ganti_rugi' => $withSanksi && !empty($g1['sanksi_ganti_rugi']),
                'sanksi_lainnya' => $withSanksi && !empty($g1['sanksi_lainnya']),
                'sanksi_surat_teguran_id' => $stId > 0 ? $stId : null,
                'sanksi_surat_peringatan_id' => $spId > 0 ? $spId : null,
                'sanksi_st_detail' => $withSanksi && $stId > 0
                    ? self::resolveSuratTeguranNomor($stId, $g1['sanksi_st_detail'] ?? null)
                    : ($withSanksi ? ($g1['sanksi_st_detail'] ?? null) : null),
                'sanksi_sp_detail' => $withSanksi && $spId > 0
                    ? self::resolveSuratPeringatanLabel($spId, $g1['sanksi_sp_detail'] ?? null)
                    : ($withSanksi ? ($g1['sanksi_sp_detail'] ?? null) : null),
                'sanksi_ganti_rugi_detail' => $withSanksi ? ($g1['sanksi_ganti_rugi_detail'] ?? null) : null,
                'sanksi_lainnya_detail' => $withSanksi ? ($g1['sanksi_lainnya_detail'] ?? null) : null,
                'catatan_tambahan' => $g1['catatan_tambahan'] ?? null,
                'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $g1
     */
    public static function validateG1(array $g1): ?string
    {
        $statusCase = InvestigasiStatusCase::resolveFromG1($g1);
        if ($statusCase === null) {
            return 'Pilih tindak lanjut awal (salah satu: Internal Disciplinary Tanpa/Dengan Sanksi, atau Komite Manajemen).';
        }

        if (InvestigasiStatusCase::requiresSanksiDetail($statusCase)) {
            $any = !empty($g1['sanksi_st'])
                || !empty($g1['sanksi_sp'])
                || !empty($g1['sanksi_ganti_rugi'])
                || !empty($g1['sanksi_lainnya']);
            if (!$any) {
                return 'Pilih minimal satu jenis sanksi.';
            }

            if (!empty($g1['sanksi_st'])) {
                $stId = (int) ($g1['sanksi_surat_teguran_id'] ?? 0);
                if ($stId <= 0) {
                    return 'Pilih surat teguran dari daftar.';
                }
                if (!self::suratTeguranExists($stId)) {
                    return 'Surat teguran tidak ditemukan.';
                }
            }

            if (!empty($g1['sanksi_sp'])) {
                $spId = (int) ($g1['sanksi_surat_peringatan_id'] ?? 0);
                if ($spId <= 0) {
                    return 'Pilih surat peringatan dari daftar.';
                }
                if (!self::suratPeringatanExists($spId)) {
                    return 'Surat peringatan tidak ditemukan.';
                }
            }
        }

        return null;
    }

    private static function suratTeguranExists(int $id): bool
    {
        if ($id <= 0 || !Schema::hasTable('surat_teguran_disipliner')) {
            return false;
        }

        return DB::table('surat_teguran_disipliner')
            ->where('id', $id)
            ->where('is_active', true)
            ->exists();
    }

    private static function suratPeringatanExists(int $id): bool
    {
        if ($id <= 0 || !Schema::hasTable('surat_peringatan_disipliner')) {
            return false;
        }

        return DB::table('surat_peringatan_disipliner')
            ->where('id', $id)
            ->where('is_active', true)
            ->exists();
    }

    private static function resolveSuratTeguranNomor(int $id, ?string $fallback): ?string
    {
        if (!Schema::hasTable('surat_teguran_disipliner')) {
            return $fallback;
        }

        $nomor = DB::table('surat_teguran_disipliner')->where('id', $id)->value('nomor_surat');
        $nomor = trim((string) ($nomor ?? ''));

        return $nomor !== '' ? $nomor : (trim((string) ($fallback ?? '')) ?: null);
    }

    private static function resolveSuratPeringatanLabel(int $id, ?string $fallback): ?string
    {
        if (!Schema::hasTable('surat_peringatan_disipliner')) {
            return $fallback;
        }

        $row = DB::table('surat_peringatan_disipliner')
            ->where('id', $id)
            ->first(['nomor_surat', 'jenis_peringatan']);

        if ($row === null) {
            return trim((string) ($fallback ?? '')) ?: null;
        }

        $jenis = trim((string) ($row->jenis_peringatan ?? ''));
        $nomor = trim((string) ($row->nomor_surat ?? ''));
        if ($nomor !== '' && $jenis !== '') {
            return $jenis . ' — ' . $nomor;
        }

        return $nomor !== '' ? $nomor : (trim((string) ($fallback ?? '')) ?: null);
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
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return true;
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