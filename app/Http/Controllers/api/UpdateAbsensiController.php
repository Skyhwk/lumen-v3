<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use \App\Models\{Absensi, RekapMasukKerja, RekapLiburKalender, ShiftKaryawan, MasterKaryawan};
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Hr\HrAttendanceDayExcuseLabels;


class UpdateAbsensiController extends Controller
{
    // Tested - Clear 
    public function hari($tanggal)
    {
        $hari = date("D", strtotime($tanggal));

        switch ($hari) {
            case 'Sun':
                $hari_ini = "Minggu";
                break;

            case 'Mon':
                $hari_ini = "Senin";
                break;

            case 'Tue':
                $hari_ini = "Selasa";
                break;

            case 'Wed':
                $hari_ini = "Rabu";
                break;

            case 'Thu':
                $hari_ini = "Kamis";
                break;

            case 'Fri':
                $hari_ini = "Jumat";
                break;

            case 'Sat':
                $hari_ini = "Sabtu";
                break;

            default:
                $hari_ini = "Tidak di ketahui";
                break;
        }

        return $hari_ini;

    }
    // Need test from front-end
    public function updateJadwal(Request $request)
    {
        DB::beginTransaction();
        try {
            $masukJam = $this->normalizeJamForDb($request->masuk);
            $keluarJam = $this->normalizeJamForDb($request->keluar);

            if ($masukJam !== '') {
                if ($request->id_masuk != '') {
                    Absensi::where('id', '!=', $request->id_masuk)
                        ->where('karyawan_id', $request->id)
                        ->where('tanggal', $request->tgl_masuk)
                        ->where('status', 'Masuk')->delete();

                    Absensi::where('id', $request->id_masuk)->update([
                        'kode_kartu' => NULL,
                        'jam' => $masukJam,
                    ]);
                } else {
                    Absensi::insert([
                        'karyawan_id' => $request->id,
                        'tanggal' => $request->tgl,
                        'hari' => self::hari($request->tgl),
                        'jam' => $masukJam,
                        'status' => 'Masuk',
                    ]);
                }
            }
            if ($keluarJam !== '') {
                if ($request->id_keluar != '') {
                    Absensi::where('id', '!=', $request->id_keluar)
                        ->where('karyawan_id', $request->id)
                        ->where('tanggal', $request->tgl_keluar)
                        ->where('status', 'Keluar')->delete();

                    Absensi::where('id', $request->id_keluar)->update([
                        'kode_kartu' => NULL,
                        'jam' => $keluarJam,
                    ]);
                } else {
                    $tanggal = $request->tgl;
                    if ($request->shift == 'SHSECURITY2' || $request->shift == '24jam') {
                        $tanggal = DATE('Y-m-d', strtotime($request->tgl . '+1day'));
                    }
                    Absensi::insert([
                        'karyawan_id' => $request->id,
                        'tanggal' => $tanggal,
                        'hari' => self::hari($tanggal),
                        'jam' => $keluarJam,
                        'status' => 'Keluar',
                    ]);
                }
            }
            DB::commit();
            return response()->json([
                'message' => 'Berhasil Update Absensi.!'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    // Tested - Clear
    public function generateJadwal(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->absensi) {
                $this->applyAbsensiGenerateItems(
                    $request->absensi,
                    (int) $request->id_karyawan,
                    (string) $request->bulan
                );
            }
            $bulan = explode("-", $request->bulan);
            $cek = RekapMasukKerja::where('karyawan_id', $request->id_karyawan)
                ->where('tahun', $bulan[0])
                ->where('bulan', $request->bulan)
                ->where('is_active', true)
                ->first();
            if ($cek) {
                RekapMasukKerja::where('karyawan_id', $request->id_karyawan)
                    ->where('tahun', $bulan[0])
                    ->where('bulan', $request->bulan)
                    ->where('is_active', true)
                    ->update([
                        'rejected_by' => $this->karyawan,
                        'rejected_at' => date('Y-m-d H:i:s'),
                        'is_active' => false
                    ]);
            }

            $karyawan_id = $request->id_karyawan;
            $hari_kerja = RekapLiburKalender::where('tahun', $bulan[0])
                ->where('is_active', true)
                ->first();

            if( !$hari_kerja ) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Kalender hari kerja belum di set untuk tahun ' . $bulan[0] . '.'
                ], 500);
            }
            $tgl_kerja = '';
            $masuk_kerja = [];

            

            foreach (json_decode($hari_kerja->tanggal) as $key => $value) {
                if ($key == $bulan[0] . '-' . $bulan[1]) {
                    $tgl_kerja = $value;
                }
            }

            // Rubah Kode menjadi for each Selesaikan Rabu
            // $datas = json_encode($request->data, JSON_PRETTY_PRINT);
            // $decoded = json_decode($datas, true);
            // dd(json_decode($request->data, true));
            // dd($data->keluar);
            foreach ($request->data as $data) {
                $row = is_array($data) ? $data : (array) $data;
                $masuk = $row['masuk'] ?? '';
                $keluar = $row['keluar'] ?? '';
                $shift = $row['shift'] ?? '';
                $tanggal = $row['tanggal'] ?? '';
                if ($keluar !== '' && $masuk !== '') {
                    if (in_array($shift, ['SHOB', 'SHOB2', 'SHSECURITY', 'SHSECURITY2', '24jam'], true)) {
                        $masuk_kerja[] = $tanggal;
                    } elseif ($shift != 'off') {
                        $masuk_kerja[] = $tanggal;
                    }
                }
            }

            // foreach ($request->tanggal as $key => $value) {
            //     if ($request->masuk[$key] != '' && $request->keluar[$key] != '') {
            //         if (in_array($request->shift[$key], ['SHOB', 'SHOB2', 'SHSECURITY', 'SHSECURITY2', '24jam'])) {
            //             array_push($masuk_kerja, $request->tanggal[$key]);
            //         } else if ($request->shift[$key] != 'off') {
            //             if (in_array($request->tanggal[$key], $tgl_kerja)) {
            //                 array_push($masuk_kerja, $request->tanggal[$key]);
            //             }
            //         }
            //     }
            // }

            RekapMasukKerja::insert([
                'karyawan_id' => $request->id_karyawan,
                'tahun' => $bulan[0],
                'bulan' => $request->bulan,
                'tanggal' => json_encode($masuk_kerja),
                'added_by' => $this->karyawan,
                'added_at' => date('Y-m-d H:i:s')
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Berhasil Generate Absensi.!'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function SelectUserbyDivisi(Request $request)
    {
        $reqBulan = $request->bulan;
        $data = MasterKaryawan::with([
            'jabatan',
            'divisi',
            'rekap' => function ($query) use ($reqBulan) {
                $query->where('bulan', $reqBulan)
                    ->where('is_active', true)
                    ->select('id', 'karyawan_id', 'bulan');
            },
        ])
            ->whereIn('id_cabang', $this->privilageCabang)
            ->whereRaw('CASE WHEN is_active = 0 THEN CAST(NOW() as DATE) <= DATE_ADD(effective_date, INTERVAL 1 month) ELSE is_active = 1 END');

        if ($request->id_jabatan) {
            $data->where('id_jabatan', $request->id_jabatan);
        } elseif ($request->departement && $request->departement !== 'all') {
            $data->where('id_department', $request->departement);
        }

        return datatables()->of($data)->make(true);
    }

    public function generateJadwalBackup(Request $request)
    {
        DB::beginTransaction();
        try {
            $bulan = explode("-", $request->bulan);
            $cek = RekapMasukKerja::where('karyawan_id', $request->id_karyawan)
                ->where('tahun', $bulan[0])
                ->where('bulan', $request->bulan)
                ->where('is_active', true)
                ->first();
            if ($cek) {
                RekapMasukKerja::where('karyawan_id', $request->id_karyawan)
                    ->where('tahun', $bulan[0])
                    ->where('bulan', $request->bulan)
                    ->where('is_active', true)
                    ->update([
                        'rejected_by' => $this->karyawan,
                        'rejected_at' => date('Y-m-d H:i:s'),
                        'is_active' => false
                    ]);
            }

            $karyawan_id = $request->id_karyawan;
            $bulan = explode("-", $request->bulan);
            $hari_kerja = RekapLiburKalender::where('tahun', $bulan[0])
                ->where('is_active', true)
                ->first();
            $tgl_kerja = '';
            $masuk_kerja = [];

            foreach (json_decode($hari_kerja->tanggal) as $key => $value) {
                if ($key == $bulan[0] . '-' . $bulan[1]) {
                    $tgl_kerja = $value;
                }
            }

            foreach ($request->tanggal as $key => $value) {
                if ($request->masuk[$key] != '' && $request->keluar[$key] != '') {
                    if (in_array($request->shift[$key], ['SHOB', 'SHOB2', 'SHSECURITY', 'SHSECURITY2', '24jam'])) {
                        array_push($masuk_kerja, $request->tanggal[$key]);
                    } else if ($request->shift[$key] != 'off') {
                        if (in_array($request->tanggal[$key], $tgl_kerja)) {
                            array_push($masuk_kerja, $request->tanggal[$key]);
                        }
                    }
                }
            }

            RekapMasukKerja::insert([
                'karyawan_id' => $request->id_karyawan,
                'tahun' => $bulan[0],
                'bulan' => $request->bulan,
                'tanggal' => json_encode($masuk_kerja),
                'added_by' => $this->karyawan,
                'added_at' => date('Y-m-d H:i:s')
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Berhasil Generate Absensi.!'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function indexAbsen(Request $request)
    {
        try {
            date_default_timezone_set('Asia/Jakarta');

            $karyawanId = (int) $request->id_karyawan;
            $karyawan = MasterKaryawan::select('id', 'nik_karyawan', 'nama_lengkap')->find($karyawanId);
            if (!$karyawan) {
                return response()->json(['message' => 'Karyawan tidak ditemukan'], 404);
            }

            $nilai = explode('-', (string) $request->tgl);
            $year = $nilai[0];
            $month = $nilai[1];
            $lastDay = cal_days_in_month(CAL_GREGORIAN, (int) $month, (int) $year);

            $startDate = sprintf('%s-%s-01', $year, $month);
            $endDate = sprintf('%s-%s-%02d', $year, $month, $lastDay);
            $rangeEnd = date('Y-m-d', strtotime($endDate . ' +1 day'));

            $shiftByDate = ShiftKaryawan::query()
                ->select('tanggal', 'shift')
                ->where('karyawan_id', $karyawanId)
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->get()
                ->keyBy(fn ($row) => (string) $row->tanggal);

            $absensiByDate = Absensi::query()
                ->select('id', 'karyawan_id', 'tanggal', 'jam', 'kode_kartu')
                ->where('karyawan_id', $karyawanId)
                ->whereBetween('tanggal', [$startDate, $rangeEnd])
                ->orderBy('tanggal')
                ->orderBy('jam')
                ->get()
                ->groupBy(fn ($row) => (string) $row->tanggal);

            $namaLabel = $karyawan->nik_karyawan . ' - ' . $karyawan->nama_lengkap;
            $excuseLabels = HrAttendanceDayExcuseLabels::forSingleKaryawan($karyawanId, $startDate, $endDate);
            $data = [];

            for ($i = 1; $i <= $lastDay; $i++) {
                $tanggal = sprintf('%s-%s-%02d', $year, $month, $i);
                $rowsToday = $absensiByDate->get($tanggal, collect());
                $shiftRow = $shiftByDate->get($tanggal);
                $shift = $shiftRow ? (string) $shiftRow->shift : null;

                if ($shift === 'off') {
                    $data[] = $this->mergeExcuseLabel(
                        $this->composeOffDayRow($namaLabel, $karyawanId, $tanggal),
                        $excuseLabels,
                        $tanggal
                    );

                    continue;
                }

                $plus = date('Y-m-d', strtotime($tanggal . ' +1 day'));
                $rowsNext = $absensiByDate->get($plus, collect());

                if ($shift === '24jam') {
                    [$masukRow, $keluarRow] = $this->resolve24JamPunches($rowsToday, $rowsNext);
                    $data[] = $this->mergeExcuseLabel(
                        $this->composeDayRow($namaLabel, $karyawanId, $tanggal, $masukRow, $keluarRow, '24JAM'),
                        $excuseLabels,
                        $tanggal
                    );
                } elseif ($shift === 'SHSECURITY2') {
                    [$masukRow, $keluarRow] = $this->resolveSecurity2Punches($rowsToday, $rowsNext);
                    $data[] = $this->mergeExcuseLabel(
                        $this->composeDayRow($namaLabel, $karyawanId, $tanggal, $masukRow, $keluarRow, 'SHSECURITY2'),
                        $excuseLabels,
                        $tanggal
                    );
                } else {
                    $shiftLabel = $shift ?: 'SHREGULAR';
                    [$masukRow, $keluarRow] = $this->resolveRegularPunches($rowsToday);
                    $data[] = $this->mergeExcuseLabel(
                        $this->composeDayRow($namaLabel, $karyawanId, $tanggal, $masukRow, $keluarRow, $shiftLabel),
                        $excuseLabels,
                        $tanggal
                    );
                }
            }

            return response()->json(['data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function mergeExcuseLabel(array $row, array $excuseLabels, string $tanggal): array
    {
        $label = $excuseLabels[$tanggal] ?? '';
        $row['keterangan'] = $label;
        $row['hr_excused'] = $label !== '';

        return $row;
    }

    private function composeOffDayRow(string $namaLabel, int $karyawanId, string $tanggal): array
    {
        return [
            'nama' => $namaLabel,
            'karyawan_id' => $karyawanId,
            'tanggal' => $tanggal,
            'hari' => self::hari($tanggal),
            'masuk' => '',
            'keluar' => '',
            'tgl_masuk' => '',
            'tgl_keluar' => '',
            'kode_kartu_masuk' => '',
            'kode_kartu_keluar' => '',
            'id_masuk' => '',
            'id_keluar' => '',
            'selisih' => '',
            'jam_kerja' => '',
            'shift' => 'OFF',
        ];
    }

    /**
     * @param \Illuminate\Support\Collection $rowsToday
     * @param \Illuminate\Support\Collection $rowsNext
     * @return array{0: object|null, 1: object|null}
     */
    private function resolveRegularPunches($rowsToday): array
    {
        $masuk = $rowsToday
            ->filter(fn ($row) => (string) $row->jam <= '14:00:00')
            ->sortBy('jam')
            ->first();
        $keluar = $rowsToday
            ->filter(fn ($row) => (string) $row->jam > '14:00:00')
            ->sortByDesc('jam')
            ->first();

        return [$masuk, $keluar];
    }

    /**
     * @param \Illuminate\Support\Collection $rowsToday
     * @param \Illuminate\Support\Collection $rowsNext
     * @return array{0: object|null, 1: object|null}
     */
    private function resolve24JamPunches($rowsToday, $rowsNext): array
    {
        $minIdToday = $rowsToday->min('id');
        $masuk = null;
        if ($minIdToday !== null) {
            $candidate = $rowsToday->firstWhere('id', $minIdToday);
            if ($candidate && (string) $candidate->jam > '00:00:00') {
                $masuk = $candidate;
            }
        }

        $maxIdNext = $rowsNext->max('id');
        $keluar = null;
        if ($maxIdNext !== null) {
            $candidate = $rowsNext->firstWhere('id', $maxIdNext);
            if ($candidate && (string) $candidate->jam < '14:00:00') {
                $keluar = $candidate;
            }
        }

        return [$masuk, $keluar];
    }

    /**
     * @param \Illuminate\Support\Collection $rowsToday
     * @param \Illuminate\Support\Collection $rowsNext
     * @return array{0: object|null, 1: object|null}
     */
    private function resolveSecurity2Punches($rowsToday, $rowsNext): array
    {
        $maxJam = $rowsToday->max('jam');
        $masuk = $maxJam !== null
            ? $rowsToday->first(fn ($row) => (string) $row->jam === (string) $maxJam)
            : null;

        $minIdNext = $rowsNext->min('id');
        $keluar = null;
        if ($minIdNext !== null) {
            $candidate = $rowsNext->firstWhere('id', $minIdNext);
            if ($candidate && (string) $candidate->jam < '14:00:00') {
                $keluar = $candidate;
            }
        }

        return [$masuk, $keluar];
    }

    private function composeDayRow(
        string $namaLabel,
        int $karyawanId,
        string $tanggal,
        $masukRow,
        $keluarRow,
        string $shiftLabel
    ): array {
        $jamMasuk = $masukRow ? (string) $masukRow->jam : '';
        $jamKeluar = $keluarRow ? (string) $keluarRow->jam : '';

        return [
            'nama' => $namaLabel,
            'karyawan_id' => $karyawanId,
            'tanggal' => $tanggal,
            'hari' => self::hari($tanggal),
            'masuk' => $this->formatJamHm($jamMasuk),
            'keluar' => $this->formatJamHm($jamKeluar),
            'tgl_masuk' => $masukRow ? (string) $masukRow->tanggal : '',
            'tgl_keluar' => $keluarRow ? (string) $keluarRow->tanggal : '',
            'id_masuk' => $masukRow ? $masukRow->id : '',
            'id_keluar' => $keluarRow ? $keluarRow->id : '',
            'selisih' => $this->formatSelisihMasuk($tanggal, $jamMasuk),
            'jam_kerja' => $this->formatJamKerja($masukRow, $keluarRow, $jamMasuk, $jamKeluar),
            'kode_kartu_masuk' => $masukRow ? $masukRow->kode_kartu : '',
            'kode_kartu_keluar' => $keluarRow ? $keluarRow->kode_kartu : '',
            'shift' => $shiftLabel,
        ];
    }

    private function formatSelisihMasuk(string $tanggal, string $jamMasuk): string
    {
        if ($jamMasuk === '' || $jamMasuk === '-') {
            return '';
        }
        if ($jamMasuk < '08:00:00') {
            $selisih = date_diff(date_create($tanggal . ' ' . $jamMasuk), date_create($tanggal . ' 08:00:00'));

            return '+' . (int) ((($selisih->h * 3600) + ($selisih->i * 60) + $selisih->s) / 60) . 'm';
        }
        if ($jamMasuk > '08:00:00' && $jamMasuk !== '00:00:00') {
            $selisih = date_diff(date_create($tanggal . ' 08:00:00'), date_create($tanggal . ' ' . $jamMasuk));

            return '-' . (int) ((($selisih->h * 3600) + ($selisih->i * 60) + $selisih->s) / 60) . 'm';
        }

        return '';
    }

    private function formatJamKerja($masukRow, $keluarRow, string $jamMasuk, string $jamKeluar): string
    {
        if ($jamMasuk === '' || $jamMasuk === '00:00:00' || $jamKeluar === '' || $jamKeluar === '-') {
            return '';
        }
        if (!$masukRow || !$keluarRow) {
            return '';
        }

        $kerja = date_diff(
            date_create($masukRow->tanggal . ' ' . $jamMasuk),
            date_create($keluarRow->tanggal . ' ' . $jamKeluar)
        );

        return $kerja->h . 'h ' . $kerja->i . 'm';
    }

    private function castAbsensiItem($value): object
    {
        if (is_object($value)) {
            return $value;
        }

        if (is_array($value)) {
            return (object) $value;
        }

        return (object) json_decode(json_encode($value), true);
    }

    /**
     * Persist perubahan absensi generate: batch hapus duplikat, update, bulk insert.
     */
    private function applyAbsensiGenerateItems($absensiItems, int $karyawanId, string $bulanYm): void
    {
        $items = [];
        foreach ($absensiItems as $value) {
            $items[] = $this->castAbsensiItem($value);
        }

        if ($items === []) {
            return;
        }

        $start = $bulanYm . '-01';
        $end = date('Y-m-t', strtotime($start));
        $existing = Absensi::where('karyawan_id', $karyawanId)
            ->whereBetween('tanggal', [$start, date('Y-m-d', strtotime($end . ' +1 day'))])
            ->get(['id', 'tanggal', 'status']);

        $insertRows = [];
        $updates = [];
        $keepBySlot = [];

        foreach ($items as $dataAbsen) {
            $kid = (int) ($dataAbsen->id ?? $karyawanId);
            $masukJam = $this->normalizeJamForDb($dataAbsen->masuk ?? '');
            $keluarJam = $this->normalizeJamForDb($dataAbsen->keluar ?? '');

            if ($masukJam !== '') {
                if (!empty($dataAbsen->id_masuk)) {
                    $idMasuk = (int) $dataAbsen->id_masuk;
                    $updates[$idMasuk] = $masukJam;
                    $keepBySlot['Masuk|' . $dataAbsen->tgl_masuk] = $idMasuk;
                } else {
                    $insertRows[] = [
                        'karyawan_id' => $kid,
                        'tanggal' => $dataAbsen->tgl,
                        'hari' => self::hari($dataAbsen->tgl),
                        'jam' => $masukJam,
                        'status' => 'Masuk',
                    ];
                }
            }

            if ($keluarJam !== '') {
                if (!empty($dataAbsen->id_keluar)) {
                    $idKeluar = (int) $dataAbsen->id_keluar;
                    $updates[$idKeluar] = $keluarJam;
                    $keepBySlot['Keluar|' . $dataAbsen->tgl_keluar] = $idKeluar;
                } else {
                    $tanggal = $dataAbsen->tgl;
                    if ($dataAbsen->shift == 'SHSECURITY2' || $dataAbsen->shift == '24jam') {
                        $tanggal = date('Y-m-d', strtotime($dataAbsen->tgl . ' +1 day'));
                    }
                    $insertRows[] = [
                        'karyawan_id' => $kid,
                        'tanggal' => $tanggal,
                        'hari' => self::hari($tanggal),
                        'jam' => $keluarJam,
                        'status' => 'Keluar',
                    ];
                }
            }
        }

        $deleteIds = [];
        foreach ($existing as $row) {
            $key = $row->status . '|' . $row->tanggal;
            if (isset($keepBySlot[$key]) && (int) $row->id !== $keepBySlot[$key]) {
                $deleteIds[] = $row->id;
            }
        }

        if ($deleteIds !== []) {
            Absensi::whereIn('id', array_values(array_unique($deleteIds)))->delete();
        }

        foreach ($updates as $id => $jam) {
            Absensi::where('id', $id)->update([
                'kode_kartu' => null,
                'jam' => $jam,
            ]);
        }

        foreach (array_chunk($insertRows, 100) as $chunk) {
            if ($chunk !== []) {
                Absensi::insert($chunk);
            }
        }
    }

    /** Tampilan UI: HH:mm (tanpa detik). */
    private function formatJamHm(?string $jam): string
    {
        if ($jam === null || $jam === '' || $jam === '-') {
            return '';
        }

        $jam = trim($jam);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $jam, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return $jam;
    }

    /** Simpan ke DB: HH:mm:ss (input UI biasanya HH:mm). */
    private function normalizeJamForDb(?string $jam): string
    {
        if ($jam === null) {
            return '';
        }

        $jam = trim((string) $jam);
        if ($jam === '') {
            return '';
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $jam)) {
            return $this->formatJamHm($jam) . ':00';
        }

        if (preg_match('/^(\d{1,2}:\d{2}):\d{2}$/', $jam, $m)) {
            return $this->formatJamHm($m[1]) . ':00';
        }

        return $jam;
    }
}
