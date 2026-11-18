<?php

namespace App\Services;

use App\Support\DisiplinerPdfHeader;
use App\Support\InvestigasiStatusCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data tampilan PDF Form Investigasi Awal (F1) — ISL-FIAW-10-T1.Rev0.
 */
final class InvestigasiAwalPdfPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function build(int $investigasiId): array
    {
        $row = DB::table('bak_investigasi')->where('id', $investigasiId)->first();
        if ($row === null) {
            throw new \RuntimeException('Investigasi tidak ditemukan.');
        }

        $pihak = Schema::hasTable('bak_investigasi_pihak')
            ? DB::table('bak_investigasi_pihak')
                ->where('bak_investigasi_id', $investigasiId)
                ->orderBy('urutan')
                ->get()
            : collect();

        $tindak = Schema::hasTable('bak_investigasi_tindak_lanjut')
            ? DB::table('bak_investigasi_tindak_lanjut')
                ->where('bak_investigasi_id', $investigasiId)
                ->orderBy('urutan')
                ->get()
            : collect();

        $pengesahan = Schema::hasTable('bak_investigasi_pengesahan')
            ? DB::table('bak_investigasi_pengesahan')
                ->where('bak_investigasi_id', $investigasiId)
                ->orderBy('urutan')
                ->get()
            : collect();

        $bakNomors = self::loadBakNomors($investigasiId, $row);
        [$noBakFirst, $noBakLast] = self::resolveNoBakEndpoints($bakNomors, $row);
        $analisis = BakInvestigasiService::decodeAnalisisKasus($row);
        $statusCase = InvestigasiStatusCase::resolveFromRow($row);
        $issued = strtolower(trim((string) ($row->status ?? ''))) === 'issued';

        return [
            'kodeDokumen' => trim((string) ($row->kode_dokumen ?? 'ISL-FIAW-10-T1.Rev0')) ?: 'ISL-FIAW-10-T1.Rev0',
            'docFooterLine' => 'ISL-FIAW-10-T1.Rev0; 04 Mei 2026',
            'logoPath' => DisiplinerPdfHeader::resolveLogoPath(),
            'caseCode' => trim((string) ($row->no_document ?? '')),
            'statusCase' => $statusCase,
            'statusCases' => self::statusCaseHeaderOptions($statusCase),
            'statusCaseFlags' => [
                'dscTs' => $statusCase === InvestigasiStatusCase::DSC_TANPA_SANKSI,
                'km' => $statusCase === InvestigasiStatusCase::KOMITE_MANAJEMEN,
                'dscDs' => $statusCase === InvestigasiStatusCase::DSC_DENGAN_SANKSI,
            ],
            'judulKejadian' => self::field($row->judul_kejadian),
            'tanggalKejadian' => self::formatHariTanggal($row->tanggal_kejadian ?? null),
            'pukulKejadian' => self::field($row->pukul_kejadian),
            'lokasiKejadian' => self::field($row->lokasi_kejadian),
            'tglBakPertama' => self::formatDate($row->tgl_bak_pertama ?? null, false),
            'tglBakTerakhir' => self::formatDate($row->tgl_bak_terakhir ?? null, false),
            'noBakPertama' => $noBakFirst,
            'noBakTerakhir' => $noBakLast,
            'noBakRange' => self::formatNoBakRange($bakNomors, $row),
            'jumlahBukti' => $row->jumlah_bukti !== null ? (string) (int) $row->jumlah_bukti : '',
            'jenisBukti' => self::field($row->jenis_bukti),
            'pihakRows' => self::padPihakRows(self::mapPihakRows($pihak), 4),
            'kronologi' => self::kronologiRows($row),
            'kronologiCatatan' => self::field($row->kronologi_catatan_khusus, true),
            'penyebabRows' => self::penyebabRows($analisis),
            'dampakMaster' => self::dampakMasterBlock($analisis),
            'dampakCatatan' => self::field($analisis['dampak']['catatan_khusus'] ?? null, true),
            'tindakRows' => self::padTindakRows(self::mapTindakRows($tindak), 2),
            'tindakLanjutAwalMaster' => self::tindakLanjutAwalMaster($statusCase),
            'tindakLanjutFlags' => [
                'internalHr' => in_array($statusCase, [
                    InvestigasiStatusCase::DSC_TANPA_SANKSI,
                    InvestigasiStatusCase::DSC_DENGAN_SANKSI,
                ], true),
                'komiteManajemen' => $statusCase === InvestigasiStatusCase::KOMITE_MANAJEMEN,
                'tanpaSanksi' => $statusCase === InvestigasiStatusCase::DSC_TANPA_SANKSI,
                'denganSanksi' => $statusCase === InvestigasiStatusCase::DSC_DENGAN_SANKSI,
            ],
            'g1' => self::g1Block($row, $statusCase),
            'pengesahanRows' => self::mapPengesahanSlots($pengesahan, $issued),
            'issued' => $issued,
        ];
    }

    /**
     * @return list<string>
     */
    private static function loadBakNomors(int $investigasiId, object $row): array
    {
        $list = [];
        if (Schema::hasTable('bak_investigasi_berita_acara') && Schema::hasTable('berita_acara_kejadian_disipliner')) {
            $nomors = DB::table('bak_investigasi_berita_acara as link')
                ->join('berita_acara_kejadian_disipliner as bak', 'bak.id', '=', 'link.berita_acara_id')
                ->where('link.bak_investigasi_id', $investigasiId)
                ->orderBy('link.id')
                ->pluck('bak.nomor_surat')
                ->map(static fn ($n) => trim((string) $n))
                ->filter(static fn ($n) => $n !== '')
                ->values()
                ->all();
            if ($nomors !== []) {
                return $nomors;
            }
        }

        $first = trim((string) ($row->no_bak_pertama ?? ''));
        $last = trim((string) ($row->no_bak_terakhir ?? ''));
        if ($first !== '') {
            $list[] = $first;
        }
        if ($last !== '' && $last !== $first) {
            $list[] = $last;
        }

        return $list;
    }

    /**
     * @param  list<string>  $bakNomors
     * @return array{0: string, 1: string}
     */
    private static function resolveNoBakEndpoints(array $bakNomors, object $row): array
    {
        $colFirst = trim((string) ($row->no_bak_pertama ?? ''));
        $colLast = trim((string) ($row->no_bak_terakhir ?? ''));

        if ($colFirst !== '' || $colLast !== '') {
            return [$colFirst, $colLast !== $colFirst ? $colLast : ''];
        }

        if ($bakNomors === []) {
            return ['', ''];
        }

        $first = $bakNomors[0];
        $last = count($bakNomors) > 1 ? $bakNomors[count($bakNomors) - 1] : '';

        return [$first, $last !== $first ? $last : ''];
    }

    /**
     * @return list<array{code: string, on: bool}>
     */
    private static function statusCaseHeaderOptions(?string $selected): array
    {
        return [
            ['code' => InvestigasiStatusCase::DSC_TANPA_SANKSI, 'on' => $selected === InvestigasiStatusCase::DSC_TANPA_SANKSI],
            ['code' => InvestigasiStatusCase::KOMITE_MANAJEMEN, 'on' => $selected === InvestigasiStatusCase::KOMITE_MANAJEMEN],
            ['code' => InvestigasiStatusCase::DSC_DENGAN_SANKSI, 'on' => $selected === InvestigasiStatusCase::DSC_DENGAN_SANKSI],
        ];
    }

    /**
     * @return list<array{label: string, on: bool}>
     */
    private static function tindakLanjutAwalMaster(?string $statusCase): array
    {
        return [
            ['label' => 'Internal HR Direv', 'on' => in_array($statusCase, [
                InvestigasiStatusCase::DSC_TANPA_SANKSI,
                InvestigasiStatusCase::DSC_DENGAN_SANKSI,
            ], true)],
            ['label' => 'Komite Manajemen', 'on' => $statusCase === InvestigasiStatusCase::KOMITE_MANAJEMEN],
            ['label' => 'Tanpa Sanksi', 'on' => $statusCase === InvestigasiStatusCase::DSC_TANPA_SANKSI],
            ['label' => 'Dengan Sanksi', 'on' => $statusCase === InvestigasiStatusCase::DSC_DENGAN_SANKSI],
        ];
    }

    private static function formatNoBakRange(array $bakNomors, object $row): string
    {
        [$first, $last] = self::resolveNoBakEndpoints($bakNomors, $row);
        if ($first !== '' && $last !== '') {
            return $first . ' s/d ' . $last;
        }

        return $first !== '' ? $first : $last;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, string>>
     */
    private static function padPihakRows(array $rows, int $min): array
    {
        while (count($rows) < $min) {
            $rows[] = ['no' => (string) (count($rows) + 1), 'nama' => '', 'divisi' => '', 'peran' => ''];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, string>>
     */
    private static function padTindakRows(array $rows, int $min): array
    {
        while (count($rows) < $min) {
            $rows[] = ['no' => (string) (count($rows) + 1), 'corrective' => '', 'preventive' => ''];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $analisis
     * @return array<string, mixed>
     */
    private static function dampakMasterBlock(array $analisis): array
    {
        $dampak = is_array($analisis['dampak'] ?? null) ? $analisis['dampak'] : [];
        $fin = is_array($dampak['fin'] ?? null) ? $dampak['fin'] : [];
        $ops = is_array($dampak['ops'] ?? null) ? $dampak['ops'] : [];
        $hse = is_array($dampak['hse'] ?? null) ? $dampak['hse'] : [];
        $leg = is_array($dampak['leg'] ?? null) ? $dampak['leg'] : [];
        $lain = is_array($dampak['lainnya'] ?? null) ? $dampak['lainnya'] : [];

        $finKerugian = !empty($fin['on']);
        $finAset = !empty($fin['aset']);
        $opsGangguan = !empty($ops['on']);
        $opsDelay = !empty($ops['delay_layanan']);
        $hseNear = !empty($hse['near_miss']);
        $hseInjury = !empty($hse['injury']);
        $hseFatality = !empty($hse['fatality']);
        $legPelanggaran = !empty($leg['on']);
        $legRisiko = !empty($leg['risiko_sanksi']);
        $lainnyaOn = !empty($lain['on']);

        return [
            'finOn' => $finKerugian || $finAset,
            'finKerugian' => $finKerugian,
            'finAset' => $finAset,
            'opsOn' => $opsGangguan || $opsDelay,
            'opsGangguan' => $opsGangguan,
            'opsDelay' => $opsDelay,
            'hseOn' => $hseNear || $hseInjury || $hseFatality || !empty($hse['on']),
            'hseNear' => $hseNear,
            'hseInjury' => $hseInjury,
            'hseFatality' => $hseFatality,
            'legOn' => $legPelanggaran || $legRisiko,
            'legPelanggaran' => $legPelanggaran,
            'legRisiko' => $legRisiko,
            'lainnyaOn' => $lainnyaOn,
            'lainnyaNote' => $lainnyaOn ? self::field($lain['note'] ?? null, true) : '',
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $pihak
     * @return list<array<string, string>>
     */
    private static function mapPihakRows($pihak): array
    {
        $peranMap = [
            'pelapor' => 'Pelapor',
            'terlapor' => 'Terlapor',
            'saksi' => 'Saksi',
            'lainnya' => 'Lainnya',
        ];

        $rows = [];
        foreach ($pihak as $index => $item) {
            $peran = strtolower(trim((string) ($item->peran ?? '')));
            $peranLabel = $peranMap[$peran] ?? ($peran !== '' ? ucfirst($peran) : '');
            $keterangan = trim((string) ($item->keterangan ?? ''));
            if ($peran === 'lainnya' && $keterangan !== '') {
                $peranLabel .= ' — ' . $keterangan;
            }

            $rows[] = [
                'no' => (string) ($index + 1),
                'nama' => self::field($item->nama),
                'divisi' => self::field($item->divisi),
                'peran' => $peranLabel,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, string>>
     */
    private static function kronologiRows(object $row): array
    {
        $map = [
            ['en' => 'What', 'id' => '(Apa)', 'label' => 'What (Apa)', 'key' => 'kronologi_what'],
            ['en' => 'Who', 'id' => '(Siapa)', 'label' => 'Who (Siapa)', 'key' => 'kronologi_who'],
            ['en' => 'When', 'id' => '(Kapan)', 'label' => 'When (Kapan)', 'key' => 'kronologi_when'],
            ['en' => 'Where', 'id' => '(Di Mana)', 'label' => 'Where (Di Mana)', 'key' => 'kronologi_where'],
            ['en' => 'Why', 'id' => '(Kenapa)', 'label' => 'Why (Kenapa)', 'key' => 'kronologi_why'],
            ['en' => 'How', 'id' => '(Bagaimana)', 'label' => 'How (Bagaimana)', 'key' => 'kronologi_how'],
        ];

        $out = [];
        foreach ($map as $item) {
            $out[] = [
                'en' => $item['en'],
                'id' => $item['id'],
                'label' => $item['label'],
                'value' => self::field($row->{$item['key']} ?? null, true),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $analisis
     * @return list<array<string, mixed>>
     */
    private static function penyebabRows(array $analisis): array
    {
        $defs = [
            ['key' => 'hmf', 'kode' => 'HMF', 'label' => 'Human Factor'],
            ['key' => 'sys', 'kode' => 'SYS', 'label' => 'System Failure'],
            ['key' => 'tec', 'kode' => 'TEC', 'label' => 'Technical / Equipment'],
            ['key' => 'ext', 'kode' => 'EXT', 'label' => 'External'],
            ['key' => 'lainnya', 'kode' => 'Lainnya', 'label' => 'Lainnya'],
        ];

        $penyebab = is_array($analisis['penyebab'] ?? null) ? $analisis['penyebab'] : [];
        $rows = [];
        foreach ($defs as $def) {
            $node = is_array($penyebab[$def['key']] ?? null) ? $penyebab[$def['key']] : [];
            $on = !empty($node['on']);
            $rows[] = [
                'kode' => $def['kode'],
                'label' => $def['label'],
                'on' => $on,
                'note' => $on ? self::field($node['note'] ?? null, true) : '',
            ];
        }

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $tindak
     * @return list<array<string, string>>
     */
    private static function mapTindakRows($tindak): array
    {
        $rows = [];
        foreach ($tindak as $index => $item) {
            $rows[] = [
                'no' => (string) ($index + 1),
                'corrective' => self::field($item->corrective_action, true),
                'preventive' => self::field($item->preventive_action, true),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function g1Block(object $row, ?string $statusCase): array
    {
        $withSanksi = InvestigasiStatusCase::requiresSanksiDetail($statusCase);

        return [
            'statusCase' => $statusCase,
            'statusCaseLabel' => InvestigasiStatusCase::label($statusCase),
            'stOn' => $withSanksi && !empty($row->sanksi_st),
            'stDetail' => $withSanksi && !empty($row->sanksi_st) ? self::field($row->sanksi_st_detail) : '',
            'spOn' => $withSanksi && !empty($row->sanksi_sp),
            'spDetail' => $withSanksi && !empty($row->sanksi_sp) ? self::field($row->sanksi_sp_detail) : '',
            'gantiRugiOn' => $withSanksi && !empty($row->sanksi_ganti_rugi),
            'gantiRugiDetail' => $withSanksi && !empty($row->sanksi_ganti_rugi) ? self::field($row->sanksi_ganti_rugi_detail) : '',
            'lainnyaOn' => $withSanksi && !empty($row->sanksi_lainnya),
            'lainnyaDetail' => $withSanksi && !empty($row->sanksi_lainnya) ? self::field($row->sanksi_lainnya_detail) : '',
            'catatan' => self::field($row->catatan_tambahan, true),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $pengesahan
     * @return list<array<string, mixed>>
     */
    private static function mapPengesahanSlots($pengesahan, bool $issued): array
    {
        $byUrutan = [];
        foreach ($pengesahan as $item) {
            $byUrutan[(int) ($item->urutan ?? 0)] = $item;
        }

        $defs = [
            1 => 'Disusun Oleh',
            2 => 'Disetujui Oleh',
            3 => 'Diketahui Oleh',
            4 => 'Diketahui Oleh',
        ];

        $rows = [];
        foreach ($defs as $urutan => $label) {
            $item = $byUrutan[$urutan] ?? null;
            $state = $item ? strtolower(trim((string) ($item->state ?? ''))) : '';
            $nama = $item ? self::field($item->nama) : '';
            $rows[] = [
                'no' => (string) $urutan,
                'label' => $label,
                'nama' => $nama,
                'jabatan' => $item ? self::field($item->jabatan) : '',
                'tanggal' => $item ? self::formatDate($item->tanggal ?? null, false) : '',
                'signed' => $issued && $state === 'approved' && $nama !== '',
            ];
        }

        return $rows;
    }

    private static function field($value, bool $multiline = false): string
    {
        $raw = trim(strip_tags((string) ($value ?? '')));
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($multiline) {
            $raw = preg_replace("/\r\n|\r|\n/", ' ', $raw) ?? $raw;
        }

        return $raw;
    }

    private static function formatHariTanggal($value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '';
        }

        $days = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];

        try {
            $dt = Carbon::parse($value);
            $hari = $days[$dt->dayOfWeek] ?? '';

            return ($hari !== '' ? $hari . ', ' : '') . $dt->format('d-m-Y');
        } catch (\Throwable $ex) {
            return self::field($value);
        }
    }

    private static function formatDate($value, bool $dashIfEmpty = true): string
    {
        if ($value === null || trim((string) $value) === '') {
            return $dashIfEmpty ? '-' : '';
        }

        try {
            return Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable $ex) {
            return self::field($value);
        }
    }
}
