<?php

namespace App\Services\Hr;

use App\Models\Absensi;
use App\Models\MasterKaryawan;
use App\Models\ShiftKaryawan;
use App\Services\Hr\HrAttendanceDayExcuseLabels;

class MonthlyAbsensiDataBuilder
{
    public static function isRestDayShift(?string $shift): bool
    {
        $normalized = strtolower(trim((string) $shift));

        return in_array($normalized, ['off', 'libur'], true);
    }

    public static function hariIndonesia(string $tanggal): string
    {
        $hari = date('D', strtotime($tanggal));

        switch ($hari) {
            case 'Sun':
                return 'Minggu';
            case 'Mon':
                return 'Senin';
            case 'Tue':
                return 'Selasa';
            case 'Wed':
                return 'Rabu';
            case 'Thu':
                return 'Kamis';
            case 'Fri':
                return 'Jumat';
            case 'Sat':
                return 'Sabtu';
            default:
                return 'Tidak di ketahui';
        }
    }

    /**
     * @param  bool  $usePayrollPeriod  false = tampilan absensi bulan kalender (1–akhir bulan) untuk semua grade
     * @return array<int, array<string, mixed>>
     */
    public function buildForKaryawan(int $karyawanId, string $bulanYm, bool $usePayrollPeriod = true): array
    {
        $karyawan = MasterKaryawan::select('id', 'nik_karyawan', 'nama_lengkap', 'grade')->find($karyawanId);
        if (!$karyawan) {
            return [];
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $bulanYm)) {
            return [];
        }

        $gradeForRange = $usePayrollPeriod
            ? ($karyawan->grade ?? null)
            : AbsensiPeriodeService::GRADE_STAFF;

        try {
            $range = AbsensiPeriodeService::resolveRange($bulanYm, $gradeForRange);
        } catch (\InvalidArgumentException $e) {
            return [];
        }

        $startDate = $range['start_ymd'];
        $endDate = $range['end_ymd'];
        $rangeEnd = date('Y-m-d', strtotime($endDate . ' +1 day'));

        $shiftByDate = ShiftKaryawan::query()
            ->select('tanggal', 'shift', 'time_in', 'time_out')
            ->where('karyawan_id', $karyawanId)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->get()
            ->keyBy(fn ($row) => (string) $row->tanggal);

        $absensiByDate = Absensi::query()
            ->select('id', 'karyawan_id', 'tanggal', 'jam', 'kode_kartu', 'kode_mesin')
            ->where('karyawan_id', $karyawanId)
            ->whereBetween('tanggal', [$startDate, $rangeEnd])
            ->orderBy('tanggal')
            ->orderBy('jam')
            ->get()
            ->groupBy(fn ($row) => (string) $row->tanggal);

        $namaLabel = $karyawan->nik_karyawan . ' - ' . $karyawan->nama_lengkap;
        $excuseLabels = HrAttendanceDayExcuseLabels::forSingleKaryawan($karyawanId, $startDate, $endDate);
        $data = [];

        for ($tanggal = $startDate; $tanggal <= $endDate; $tanggal = date('Y-m-d', strtotime($tanggal . ' +1 day'))) {
            $rowsToday = $absensiByDate->get($tanggal, collect());
            $shiftRow = $shiftByDate->get($tanggal);
            $shift = $shiftRow ? (string) $shiftRow->shift : null;

            if (self::isRestDayShift($shift)) {
                $data[] = $this->mergeExcuseLabel(
                    $this->composeOffDayRow($namaLabel, $karyawanId, $tanggal, $shift),
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
                $checkin = $this->shiftCheckinTime($shiftRow);
                [$masukRow, $keluarRow] = $this->resolveThresholdPunches($rowsToday, $checkin);
                $data[] = $this->mergeExcuseLabel(
                    $this->composeDayRow($namaLabel, $karyawanId, $tanggal, $masukRow, $keluarRow, $shiftLabel),
                    $excuseLabels,
                    $tanggal
                );
            }
        }

        return $data;
    }

    private function mergeExcuseLabel(array $row, array $excuseLabels, string $tanggal): array
    {
        $label = $excuseLabels[$tanggal] ?? '';
        $row['keterangan'] = $label;
        $row['hr_excused'] = $label !== '';

        return $row;
    }

    private function composeOffDayRow(string $namaLabel, int $karyawanId, string $tanggal, ?string $shift = null): array
    {
        $shiftLabel = self::isRestDayShift($shift)
            ? strtoupper(trim((string) $shift))
            : 'OFF';

        return [
            'nama' => $namaLabel,
            'karyawan_id' => $karyawanId,
            'tanggal' => $tanggal,
            'hari' => self::hariIndonesia($tanggal),
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
            'shift' => $shiftLabel,
            'is_rest_day' => true,
        ];
    }

    /**
     * Sama dengan AbsensiController::compareshift / extractMasukKeluarByThreshold:
     * masuk = MIN(jam) jika <= time_in + 4 jam; keluar = MAX(jam) jika > threshold.
     *
     * @param \Illuminate\Support\Collection $rowsToday
     * @return array{0: object|null, 1: object|null}
     */
    private function resolveThresholdPunches($rowsToday, string $checkin): array
    {
        if ($rowsToday->isEmpty()) {
            return [null, null];
        }

        $threshold = date('H:i:s', strtotime($checkin . ' +4 hours'));
        $minJam = (string) $rowsToday->min('jam');
        $maxJam = (string) $rowsToday->max('jam');

        $masuk = null;
        if ($minJam !== '' && $minJam <= $threshold) {
            $masuk = $rowsToday->first(fn ($row) => (string) $row->jam === $minJam);
        }

        $keluar = null;
        if ($maxJam !== '' && $maxJam > $threshold) {
            $keluar = $rowsToday->first(fn ($row) => (string) $row->jam === $maxJam);
        }

        return [$masuk, $keluar];
    }

    private function shiftCheckinTime($shiftRow): string
    {
        if ($shiftRow && !empty($shiftRow->time_in)) {
            $time = trim((string) $shiftRow->time_in);
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
                return strlen($time) === 5 ? $time . ':00' : $time;
            }
        }

        return '08:00:00';
    }

    /**
     * @param \Illuminate\Support\Collection $rowsToday
     * @param \Illuminate\Support\Collection $rowsNext
     * @return array{0: object|null, 1: object|null}
     */
    private function resolve24JamPunches($rowsToday, $rowsNext): array
    {
        $minJamToday = $rowsToday->min('jam');
        $masuk = null;
        if ($minJamToday !== null && (string) $minJamToday > '00:00:00') {
            $masuk = $rowsToday->first(fn ($row) => (string) $row->jam === (string) $minJamToday);
        }

        $keluar = ShiftAbsensiPunchRules::pickOvernightKeluarRow($rowsNext);

        return [$masuk, $keluar];
    }

    /**
     * @param \Illuminate\Support\Collection $rowsToday
     * @param \Illuminate\Support\Collection $rowsNext
     * @return array{0: object|null, 1: object|null}
     */
    private function resolveSecurity2Punches($rowsToday, $rowsNext): array
    {
        $masuk = ShiftAbsensiPunchRules::pickSecurity2MasukRow($rowsToday);
        $keluar = ShiftAbsensiPunchRules::pickOvernightKeluarRow($rowsNext);

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
            'hari' => self::hariIndonesia($tanggal),
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
}
