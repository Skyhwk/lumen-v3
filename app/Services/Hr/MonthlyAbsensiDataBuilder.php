<?php

namespace App\Services\Hr;

use App\Models\Absensi;
use App\Models\MasterKaryawan;
use App\Models\ShiftKaryawan;
use App\Services\Hr\HrAttendanceDayExcuseLabels;

class MonthlyAbsensiDataBuilder
{
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
     * @return array<int, array<string, mixed>>
     */
    public function buildForKaryawan(int $karyawanId, string $bulanYm): array
    {
        $karyawan = MasterKaryawan::select('id', 'nik_karyawan', 'nama_lengkap')->find($karyawanId);
        if (!$karyawan) {
            return [];
        }

        $nilai = explode('-', $bulanYm);
        if (count($nilai) < 2) {
            return [];
        }

        $year = $nilai[0];
        $month = $nilai[1];
        $lastDay = (int) date('t', mktime(0, 0, 0, (int) $month, 1, (int) $year));

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

        return $data;
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
            'shift' => 'OFF',
        ];
    }

    /**
     * @param \Illuminate\Support\Collection $rowsToday
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
