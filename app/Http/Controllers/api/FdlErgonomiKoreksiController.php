<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DataLapanganErgonomi;
use App\Models\ErgonomiHeader;
use App\Services\RebaFormatter;
use App\Services\RlwFormatter;
use App\Services\RosaFormatter;
use App\Services\RulaFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FdlErgonomiKoreksiController extends Controller
{
    /**
     * Hanya untuk menampilkan tombol perbaikan data di portal (whitelist env).
     */
    public function canEdit(Request $request)
    {
        return response()->json([
            'allowed' => $this->isKoreksiPersonilAllowed(),
        ], 200);
    }

    public function prefill(Request $request)
    {
        if (!$this->isKoreksiPersonilAllowed()) {
            return response()->json(['message' => 'Anda tidak memiliki akses koreksi ergonomi.'], 403);
        }

        $id = $request->input('id') ?: $request->input('id_lapangan_sumber');
        if ($id === null || $id === '') {
            return response()->json(['message' => 'id wajib diisi.'], 422);
        }

        $row = DataLapanganErgonomi::with('detail')->find($id);
        if (!$row) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $decodeJson = function ($value) {
            if ($value === null || $value === '') {
                return null;
            }
            if (is_array($value)) {
                return $value;
            }
            $decoded = json_decode(html_entity_decode((string) $value), true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        };

        return response()->json([
            'message' => 'Successful.',
            'data' => $row,
            'pengukuran' => $decodeJson($row->pengukuran),
            'sebelum_kerja' => $decodeJson($row->sebelum_kerja),
            'setelah_kerja' => $decodeJson($row->setelah_kerja),
            'method' => (int) $row->method,
        ], 200);
    }

    /**
     * Simpan perbaikan data dari form portal (row baru, tidak auto-approve).
     */
    public function storePortal(Request $request)
    {
        if (!$this->isKoreksiPersonilAllowed()) {
            return response()->json(['message' => 'Anda tidak memiliki akses koreksi ergonomi.', 'success' => false], 403);
        }

        $idSumber = $request->input('id_lapangan_sumber')
            ?: $request->input('id_datalapangan')
            ?: $request->input('id');

        if ($idSumber === null || $idSumber === '') {
            return response()->json(['message' => 'id_lapangan_sumber wajib diisi.', 'success' => false], 422);
        }

        $old = DataLapanganErgonomi::find($idSumber);
        if (!$old) {
            return response()->json(['message' => 'Data lapangan sumber tidak ditemukan.', 'success' => false], 404);
        }

        $method = (int) $old->method;
        $portalMethods = [1, 2, 3, 4, 5, 7, 8];
        if (!in_array($method, $portalMethods, true)) {
            return response()->json([
                'message' => 'Perbaikan data portal belum didukung untuk method ' . $method . '.',
                'success' => false,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $fields = $this->buildPortalKoreksiFields($method, $request);

            $new = $old->replicate();
            $new->is_approve = 0;
            $new->approved_by = null;
            $new->approved_at = null;
            $new->created_by = $this->karyawan;
            $new->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $new->updated_by = null;
            $new->updated_at = null;

            foreach ($fields as $column => $value) {
                if ($value !== null) {
                    $new->{$column} = $value;
                }
            }

            $new->save();
            $this->finalizeNewKoreksiRecord($new, $old);
            $this->supersedeOldRecord($old, $new);

            DB::commit();

            return response()->json([
                'message' => 'Koreksi berhasil disimpan. Menunggu pengecekan dan approve.',
                'success' => true,
                'status' => 200,
                'data' => [
                    'id' => $new->id,
                    'id_lapangan_sumber' => $old->id,
                    'no_sampel' => $new->no_sampel,
                    'method' => $new->method,
                    'is_approve' => $new->is_approve,
                ],
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
                'status' => 500,
            ], 500);
        }
    }

    private function buildPortalKoreksiFields(int $method, Request $request): array
    {
        $meta = $this->lapanganMetaFromRequest($request);

        switch ($method) {
            case 1:
                $sebelumRaw = $this->decodeKoreksiJson($request->input('sebelum_kerja'));
                $setelahRaw = $this->decodeKoreksiJson($request->input('setelah_kerja'));
                $pengukuranNbm = [
                    'sebelum' => $this->prosesSkorNbmKoreksi(is_array($sebelumRaw) ? $sebelumRaw : []),
                    'setelah' => $this->prosesSkorNbmKoreksi(is_array($setelahRaw) ? $setelahRaw : []),
                ];
                return array_merge([
                    'pengukuran' => json_encode($pengukuranNbm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'sebelum_kerja' => $this->normalizeJsonColumn($request->input('sebelum_kerja')),
                    'setelah_kerja' => $this->normalizeJsonColumn($request->input('setelah_kerja')),
                ], $meta);
            case 2:
                $formatted = (new RebaFormatter())->formatRebaLegacyData($request->all());
                return array_merge(['pengukuran' => json_encode($formatted, JSON_UNESCAPED_SLASHES)], $meta);
            case 3:
                $formatted = (new RulaFormatter())->formatLegacyData($request->all());
                return array_merge(['pengukuran' => json_encode($formatted, JSON_UNESCAPED_SLASHES)], $meta);
            case 4:
                $formatted = RosaFormatter::formatRosaLegacyData($request->all());
                return array_merge(['pengukuran' => json_encode($formatted, JSON_UNESCAPED_SLASHES)], $meta);
            case 5:
                $formatted = RlwFormatter::format($request->all(), []);
                unset($formatted['id_datalapangan'], $formatted['no_sampel'], $formatted['method']);
                $fields = array_merge([
                    'pengukuran' => json_encode($formatted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ], $meta);
                if ($request->exists('berat_beban')) {
                    $fields['berat_beban'] = $request->input('berat_beban');
                }
                $frek = $request->input('frek_jml_angkatan');
                if ($frek === null || $frek === '') {
                    $frek = $request->input('frekuensi_jumlah_angkatan');
                }
                if ($frek !== null && $frek !== '') {
                    $fields['frekuensi_jumlah_angkatan'] = str_replace(',', '.', (string) $frek);
                }
                if ($request->filled('kopling_tangan')) {
                    $fields['kopling_tangan'] = $request->input('kopling_tangan');
                }
                if ($request->exists('jarak_vertikal')) {
                    $fields['jarak_vertikal'] = $request->input('jarak_vertikal');
                }
                if ($request->exists('durasi_jam_kerja')) {
                    $fields['durasi_jam_kerja'] = $request->input('durasi_jam_kerja');
                }
                return $fields;
            case 7:
                $payload = $request->except([
                    'id_lapangan_sumber',
                    'id_datalapangan',
                    'id',
                    'method',
                    'no_sampel',
                    'no_sample',
                    'pekerja',
                    'divisi',
                    'usia',
                    'year',
                    'month',
                    'kelamin',
                    'waktu_bekerja',
                    'aktivitas',
                    'aktivitas_ukur',
                ]);
                $payload = $this->finalizeGotrakKoreksiPayload($payload);
                return array_merge([
                    'pengukuran' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ], $meta);
            case 8:
                $payload = $request->except([
                    'id_lapangan_sumber',
                    'id_datalapangan',
                    'id',
                    'method',
                    'no_sampel',
                    'no_sample',
                    'pekerja',
                    'divisi',
                    'usia',
                    'year',
                    'month',
                    'kelamin',
                    'waktu_bekerja',
                    'aktivitas',
                    'aktivitas_ukur',
                ]);
                $payload = $this->finalizeMethod8KoreksiPayload($payload);
                return array_merge(['pengukuran' => json_encode($payload, JSON_UNESCAPED_SLASHES)], $meta);
            default:
                return [];
        }
    }

    private function lapanganMetaFromRequest(Request $request): array
    {
        $meta = [];

        if ($request->filled('pekerja')) {
            $meta['nama_pekerja'] = $request->input('pekerja');
        }
        if ($request->filled('divisi')) {
            $meta['divisi'] = $request->input('divisi');
        }
        if ($request->filled('usia')) {
            $meta['usia'] = $request->input('usia');
        }
        if ($request->filled('kelamin')) {
            $meta['jenis_kelamin'] = $request->input('kelamin');
        }
        if ($request->filled('waktu_bekerja')) {
            $meta['waktu_bekerja'] = $request->input('waktu_bekerja');
        }
        if ($request->filled('aktivitas')) {
            $meta['aktivitas'] = $request->input('aktivitas');
        }
        if ($request->filled('aktivitas_ukur')) {
            $meta['aktivitas_ukur'] = $request->input('aktivitas_ukur');
        }
        if ($request->filled('year') && $request->filled('month')) {
            $meta['lama_kerja'] = json_encode($request->input('year') . ' Tahun' . ', ' . $request->input('month') . ' Bulan');
        }

        return $meta;
    }

    private function normalizeJsonColumn($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        return json_encode($value, JSON_UNESCAPED_SLASHES);
    }

    private function decodeKoreksiJson($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /** Selaras FdlMethodNbmController::prosesSkor — input key bagian: "skor-deskripsi". */
    private function prosesSkorNbmKoreksi(array $data): array
    {
        $bagianKiri = [
            'bahu_kiri', 'leher_atas', 'pinggul', 'lengan_atas_kiri', 'siku_kiri', 'lengan_bawah_kiri',
            'pergelangan_tangan_kiri', 'tangan_kiri', 'paha_kiri', 'lutut_kiri', 'betis_kiri',
            'pergelangan_kaki_kiri', 'kaki_kiri',
        ];
        $bagianKanan = [
            'bahu_kanan', 'tengkuk', 'punggung', 'pinggang', 'pantat', 'lengan_atas_kanan', 'siku_kanan',
            'lengan_bawah_kanan', 'pergelangan_tangan_kanan', 'tangan_kanan', 'paha_kanan', 'lutut_kanan',
            'betis_kanan', 'pergelangan_kaki_kanan', 'kaki_kanan',
        ];

        $skorKiri = 0;
        $skorKanan = 0;
        $result = [];

        foreach ($data as $bagian => $nilai) {
            if (!is_string($nilai) || strpos($nilai, '-') === false) {
                continue;
            }
            [$skor, $keterangan] = explode('-', $nilai, 2);
            $skor = (int) trim($skor);
            $bagianKey = strtolower(str_replace(' ', '_', (string) $bagian));
            $result['skor_' . $bagianKey] = $skor;

            if (in_array($bagianKey, $bagianKiri, true)) {
                $skorKiri += $skor;
            } elseif (in_array($bagianKey, $bagianKanan, true)) {
                $skorKanan += $skor;
            }
        }

        $totalSkor = $skorKiri + $skorKanan;
        if ($totalSkor <= 20) {
            $tingkat = 0;
            $kategori = 'Rendah';
            $tindakan = 'Belum diperlukan adanya tindakan perbaikan';
        } elseif ($totalSkor <= 41) {
            $tingkat = 1;
            $kategori = 'Sedang';
            $tindakan = 'Mungkin diperlukan tindakan dikemudian hari';
        } elseif ($totalSkor <= 62) {
            $tingkat = 2;
            $kategori = 'Tinggi';
            $tindakan = 'Diperlukan tindakan segera';
        } elseif ($totalSkor <= 84) {
            $tingkat = 3;
            $kategori = 'Sangat Tinggi';
            $tindakan = 'Diperlukan tindakan menyeluruh sesegera mungkin';
        } else {
            $tingkat = null;
            $kategori = 'Tidak Diketahui';
            $tindakan = '-';
        }

        return array_merge($result, [
            'skor_kiri' => $skorKiri,
            'skor_kanan' => $skorKanan,
            'total_skor' => $totalSkor,
            'tingkat_risiko' => $tingkat,
            'kategori_risiko' => $kategori,
            'tindakan_perbaikan' => $tindakan,
        ]);
    }

    /** Selaras FdlMethodGotrakController::store — tanpa Skor_Postur_Tubuh. */
    private function finalizeGotrakKoreksiPayload(array $payload): array
    {
        unset($payload['Skor_Postur_Tubuh']);

        $keluhan = $payload['Keluhan_Bagian_Tubuh'] ?? [];
        if (is_array($keluhan)) {
            foreach ($keluhan as $bagian => $entry) {
                if ($bagian === 'cedera' || $entry === 'Tidak' || !is_array($entry)) {
                    continue;
                }
                if (isset($entry['Seberapa_Parah'], $entry['Seberapa_Sering'])) {
                    $keluhan[$bagian] = [
                        'Seberapa_Parah' => $entry['Seberapa_Parah'],
                        'Seberapa_Sering' => $entry['Seberapa_Sering'],
                        'Poin' => $this->hitungRisikoKeluhanGotrak(
                            (string) $entry['Seberapa_Parah'],
                            (string) $entry['Seberapa_Sering']
                        ),
                    ];
                }
            }
        }

        return [
            'Identitas_Umum' => $payload['Identitas_Umum'] ?? [],
            'Keluhan_Bagian_Tubuh' => is_array($keluhan) ? $keluhan : [],
        ];
    }

    private function hitungRisikoKeluhanGotrak(string $seberapaParah, string $seberapaSering): int
    {
        $nilaiParah = 0;
        if ($seberapaParah === 'Tidak ada masalah') {
            $nilaiParah = 1;
        } elseif ($seberapaParah === 'Tidak nyaman') {
            $nilaiParah = 2;
        } elseif ($seberapaParah === 'Sakit') {
            $nilaiParah = 3;
        } elseif ($seberapaParah === 'Sakit parah') {
            $nilaiParah = 4;
        }

        $nilaiSering = 0;
        if ($seberapaSering === 'Tidak pernah') {
            $nilaiSering = 1;
        } elseif ($seberapaSering === 'Terkadang') {
            $nilaiSering = 2;
        } elseif ($seberapaSering === 'Sering') {
            $nilaiSering = 3;
        } elseif ($seberapaSering === 'Selalu') {
            $nilaiSering = 4;
        }

        return $nilaiParah * $nilaiSering;
    }

    /**
     * Portal kirim multipart/form-data → angka jadi string. Selaraskan dengan mobile store:
     * hitung ulang skor & paksa integer untuk field poin.
     */
    private function finalizeMethod8KoreksiPayload(array $payload): array
    {
        $atas = $payload['Tubuh_Bagian_Atas'] ?? null;
        $bawah = $payload['Tubuh_Bagian_Bawah'] ?? null;

        $totalAtas = is_array($atas) ? $this->calculateTotalDurasiBahayaErgonomi($atas) : 0;
        $totalBawah = 0;
        if (is_array($bawah)) {
            $totalBawah = $this->calculateTotalDurasiBahayaErgonomi($bawah);
        }

        $payload['Jumlah_Skor_Postur'] = (int) ($totalAtas + $totalBawah);

        if (isset($payload['Manual_Handling']) && is_array($payload['Manual_Handling'])) {
            $this->recalculateManualHandlingScores($payload['Manual_Handling']);
        }

        return $payload;
    }

    /** @see FdlMethodBahayaErgonomiController::calculateTotalDurasi */
    private function calculateTotalDurasiBahayaErgonomi($data): int
    {
        $totalDurasi = 0;

        if (!is_array($data)) {
            return 0;
        }

        foreach ($data as $values) {
            if (isset($values['Faktor Kontrol'])) {
                if (stripos($values['Faktor Kontrol'], 'Tidak') !== false) {
                    continue;
                }
                if (preg_match('/(\d+)/', $values['Faktor Kontrol'], $match)) {
                    $totalDurasi += (int) $match[1];
                }
                continue;
            }

            if (!is_array($values)) {
                continue;
            }

            foreach ($values as $details) {
                if (!is_array($details)) {
                    continue;
                }

                if (isset($details['Durasi Gerakan']) && $details['Durasi Gerakan'] !== 'Tidak') {
                    $durasi = explode(';', (string) $details['Durasi Gerakan'])[0];
                    if (is_numeric($durasi)) {
                        $totalDurasi += (int) $durasi;
                    }
                }

                $penambahanWaktuYa =
                    (isset($details['Overtime Status']) && $details['Overtime Status'] === 'Ya')
                    || (isset($details['Penambahan Waktu']) && $details['Penambahan Waktu'] === 'Ya');

                if ($penambahanWaktuYa && isset($details['Overtime']) && $details['Overtime'] !== '') {
                    $totalDurasi += (int) round((float) $details['Overtime']);
                }
            }
        }

        return $totalDurasi;
    }

    /** @see FdlMethodBahayaErgonomiController::store hitung manual handling */
    private function recalculateManualHandlingScores(array &$manualHandling): void
    {
        if ($manualHandling === [] || $manualHandling === 'Tidak') {
            return;
        }

        $totalSkor1 = 0;
        if (isset($manualHandling['Posisi Angkat Beban'], $manualHandling['Estimasi Berat Benda'])) {
            $totalSkor1 = $this->hitungRisikoBebanMethod8(
                (string) $manualHandling['Posisi Angkat Beban'],
                (string) $manualHandling['Estimasi Berat Benda']
            );
        }

        $totalSkor2 = 0;
        $cleanFaktorResiko = [];
        if (isset($manualHandling['Faktor Resiko']) && is_array($manualHandling['Faktor Resiko'])) {
            foreach ($manualHandling['Faktor Resiko'] as $faktor => $nilai) {
                if ($faktor === 'Total Poin 2') {
                    continue;
                }
                $entrySkor = $this->sumFaktorResikoEntryMethod8($nilai);
                if ($entrySkor <= 0) {
                    continue;
                }
                $cleanFaktorResiko[(string) $faktor] = $nilai;
                $totalSkor2 += $entrySkor;
            }
        }

        $manualHandling['Total Poin 1'] = (int) $totalSkor1;
        $cleanFaktorResiko['Total Poin 2'] = (int) $totalSkor2;
        $manualHandling['Faktor Resiko'] = $cleanFaktorResiko;
        $manualHandling['Total Poin Akhir'] = (int) ($totalSkor1 + $totalSkor2);
    }

    private function sumFaktorResikoEntryMethod8($nilai): int
    {
        $total = 0;
        if (is_array($nilai)) {
            if ($nilai === []) {
                return 0;
            }
            foreach ($nilai as $subNilai) {
                if (is_string($subNilai) && $subNilai !== 'Tidak') {
                    $skor = explode('-', $subNilai)[0];
                    if (is_numeric($skor)) {
                        $total += (int) $skor;
                    }
                }
            }
            return $total;
        }
        if (is_string($nilai) && $nilai !== 'Tidak') {
            $skor = explode('-', $nilai)[0];
            if (is_numeric($skor)) {
                return (int) $skor;
            }
        }

        return 0;
    }

    private function hitungRisikoBebanMethod8(string $posisi, string $berat): int
    {
        if ($posisi === 'Pengangkatan dengan jarak dekat') {
            if ($berat === 'Berat benda >23Kg') {
                return 5;
            }
            if ($berat === 'Berat benda Sekitar 7 - 23 Kg') {
                return 3;
            }
            return 0;
        }
        if ($posisi === 'Pengangkatan dengan jarak sedang') {
            if ($berat === 'Berat benda >16Kg') {
                return 6;
            }
            if ($berat === 'Berat benda Sekitar 5 - 16 Kg') {
                return 3;
            }
            return 0;
        }
        if ($posisi === 'Pengangkatan dengan jarak jauh') {
            if ($berat === 'Berat benda >13Kg') {
                return 6;
            }
            if ($berat === 'Berat benda Sekitar 4.5 - 13 Kg') {
                return 3;
            }
            return 0;
        }

        return 0;
    }

    private function isKoreksiPersonilAllowed(): bool
    {
        $raw = (string) config('app.fdl_ergonomi_koreksi_personil', '');
        if (trim($raw) === '') {
            return false;
        }

        $allowed = array_filter(array_map(function ($item) {
            return mb_strtolower(trim($item));
        }, explode(',', $raw)));

        if ($allowed === []) {
            return false;
        }

        $candidates = array_filter([
            mb_strtolower(trim((string) $this->karyawan)),
            $this->user_id !== null ? (string) $this->user_id : null,
        ]);

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    private function finalizeNewKoreksiRecord(DataLapanganErgonomi $new, DataLapanganErgonomi $old): void
    {
        $new->is_approve = 0;
        $new->approved_by = null;
        $new->approved_at = null;

        if (Schema::hasColumn('data_lapangan_ergonomi', 'koreksi_dari_id')) {
            $new->koreksi_dari_id = $old->id;
        }

        if (Schema::hasColumn('data_lapangan_ergonomi', 'is_active')) {
            $new->is_active = 1;
        }

        $new->updated_by = $this->karyawan;
        $new->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $new->save();
    }

    private function supersedeOldRecord(DataLapanganErgonomi $old, DataLapanganErgonomi $new): void
    {
        if (Schema::hasColumn('data_lapangan_ergonomi', 'is_active')) {
            $old->is_active = 0;
        }
        if (Schema::hasColumn('data_lapangan_ergonomi', 'replaced_by_id')) {
            $old->replaced_by_id = $new->id;
        }

        $old->is_approve = 0;
        $old->approved_by = null;
        $old->approved_at = null;
        $old->updated_by = $this->karyawan;
        $old->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $old->save();

        $headerPayload = [];
        if (Schema::hasColumn('ergonomi_header', 'is_active')) {
            $headerPayload['is_active'] = 0;
        }
        if (Schema::hasColumn('ergonomi_header', 'is_approve')) {
            $headerPayload['is_approve'] = 0;
        }
        if ($headerPayload !== []) {
            ErgonomiHeader::where('id_lapangan', $old->id)->update($headerPayload);
        }
    }
}
