<?php

namespace App\Services\Payroll;

use App\Models\Greatday\LeaveRequest;
use App\Models\Hr\HrRequest;
use App\Models\Hr\HrSpecialLeaveType;
use App\Models\MasterKaryawan;
use App\Services\Hr\AbsensiPeriodeService;
use App\Services\Hr\HrTableMode;
use App\Services\Hr\WorkflowStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class MaternityLeavePayrollService
{
    public const MATERNITY_CODE = 'maternity';

    public const DURATION_MONTHS = 3;

    public const PHASE_START_MONTH = 'start_month';

    public const PHASE_FULL_LEAVE = 'full_leave';

    public const PHASE_RETURN_MONTH = 'return_month';

    public static function isMaternitySpecialLeaveType($type): bool
    {
        if (!$type) {
            return false;
        }

        $code = strtolower(trim((string) ($type->code ?? '')));
        if ($code === self::MATERNITY_CODE) {
            return true;
        }

        $name = strtolower(trim((string) ($type->name ?? '')));
        if ($name === '') {
            return false;
        }

        return str_contains($name, 'melahirkan') && !str_contains($name, 'istri');
    }

    public static function isFemaleEmployee(?MasterKaryawan $employee): bool
    {
        if (!$employee) {
            return false;
        }

        $gender = strtolower(trim((string) ($employee->jenis_kelamin ?? '')));
        if ($gender === '') {
            return false;
        }

        return str_starts_with($gender, 'female')
            || str_starts_with($gender, 'perempuan')
            || str_starts_with($gender, 'wanita')
            || $gender === 'p'
            || $gender === 'w';
    }

    public static function calculateEndDate(string $startDate): string
    {
        return Carbon::parse($startDate)
            ->startOfDay()
            ->addMonthsNoOverflow(self::DURATION_MONTHS)
            ->toDateString();
    }

    /**
     * Klasifikasi fase Cuti Melahirkan pada periode payroll tertentu.
     *
     * Contoh non-SPV (1..akhir bulan), cuti 2026-01-15 s/d 2026-04-15 (masuk kerja 15 Apr):
     * - 2026-01 (01-01..01-31): start_month  -> tidak ada potongan apapun, gaji full
     * - 2026-02 (02-01..02-28): full_leave   -> seluruh komponen payroll = 0
     * - 2026-03 (03-01..03-31): full_leave   -> seluruh komponen payroll = 0
     * - 2026-04 (04-01..04-30): return_month -> perhitungan prorata untuk semua status karyawan
     *
     * Contoh mulai tgl 1 (2026-01-01 s/d 2026-04-01, masuk kerja 1 Apr):
     * - 2026-01, 2026-02, 2026-03: full_leave -> Rp 0
     * - 2026-04: null (kembali masuk mulai tgl 1 = normal full month)
     *
     * @return array{phase: string, start_date: string, end_date: string, period_start: string, period_end: string}|null
     */
    public static function classifyPeriod(
        string $leaveStartYmd,
        string $leaveEndYmd,
        string $periodeYm,
        ?string $grade = null
    ): ?array {
        if (!preg_match('/^\d{4}-\d{2}$/', $periodeYm)) {
            return null;
        }

        $start = Carbon::parse($leaveStartYmd)->startOfDay()->toDateString();
        $end = Carbon::parse($leaveEndYmd)->startOfDay()->toDateString();
        if ($end < $start) {
            return null;
        }

        $range = AbsensiPeriodeService::resolveRange($periodeYm, $grade);
        $periodStart = $range['start_ymd'];
        $periodEnd = $range['end_ymd'];

        // 1. Bulan penuh cuti (tidak masuk kerja sama sekali sepanjang periode)
        if ($start <= $periodStart && $end > $periodEnd) {
            return [
                'phase' => self::PHASE_FULL_LEAVE,
                'start_date' => $start,
                'end_date' => $end,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ];
        }

        // 2. Bulan pertama pengajuan (mulai cuti di tengah/dalam periode -> tetap gaji full, tanpa potongan)
        if ($start > $periodStart && $start <= $periodEnd) {
            return [
                'phase' => self::PHASE_START_MONTH,
                'start_date' => $start,
                'end_date' => $end,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ];
        }

        // 3. Bulan kembali bekerja (tanggal masuk = end_date jatuh di tengah periode -> prorata semua status)
        if ($end > $periodStart && $end <= $periodEnd) {
            return [
                'phase' => self::PHASE_RETURN_MONTH,
                'start_date' => $start,
                'end_date' => $end,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ];
        }

        return null;
    }

    /**
     * Ambil daftar Cuti Melahirkan yang sudah disetujui HRD untuk sekumpulan karyawan
     * dan beririsan dengan periode payroll tertentu.
     *
     * @param  list<int>  $karyawanIds
     * @return array<int, list<array{start_date: string, end_date: string}>>
     */
    public static function mapApprovedMaternityLeaves(array $karyawanIds, string $periodeYm): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $karyawanIds), fn ($id) => $id > 0)));
        if ($ids === [] || !preg_match('/^\d{4}-\d{2}$/', $periodeYm)) {
            return [];
        }

        // Rentang cukup lebar untuk mencakup periode Staff (1..31) maupun SPV (16..15 bulan berikutnya)
        $windowStart = Carbon::createFromFormat('Y-m', $periodeYm)->startOfMonth()->toDateString();
        $windowEnd = Carbon::createFromFormat('Y-m', $periodeYm)->addMonth()->endOfMonth()->toDateString();

        $byKaryawan = [];

        if (HrTableMode::usesLegacyHrTables()) {
            $maternityTypeIds = self::legacyMaternityTypeIds();
            if ($maternityTypeIds === []) {
                return [];
            }

            $rows = LeaveRequest::on('intilab_apps')
                ->whereIn('employee_id', $ids)
                ->where('is_active', true)
                ->where('status', 'Approved HRD')
                ->where('type', 'Special Leave')
                ->whereIn('special_leave_id', $maternityTypeIds)
                ->where('start_date', '<=', $windowEnd)
                ->where('end_date', '>=', $windowStart)
                ->get(['employee_id', 'start_date', 'end_date']);

            foreach ($rows as $row) {
                if (empty($row->start_date) || empty($row->end_date)) {
                    continue;
                }
                $kid = (int) $row->employee_id;
                $byKaryawan[$kid][] = [
                    'start_date' => Carbon::parse($row->start_date)->toDateString(),
                    'end_date' => Carbon::parse($row->end_date)->toDateString(),
                ];
            }

            return $byKaryawan;
        }

        $maternityTypeIds = self::hrMaternityTypeIds();
        if ($maternityTypeIds === []) {
            return [];
        }

        $requests = HrRequest::query()
            ->with('leaveDetail')
            ->whereIn('karyawan_id', $ids)
            ->where('request_type', HrRequest::TYPE_LEAVE)
            ->where('is_active', true)
            ->where('status', WorkflowStatus::APPROVED_HRD)
            ->whereHas('leaveDetail', function ($q) use ($maternityTypeIds, $windowStart, $windowEnd) {
                $q->where('leave_kind', 'special')
                    ->whereIn('special_leave_type_id', $maternityTypeIds)
                    ->where('start_date', '<=', $windowEnd)
                    ->where('end_date', '>=', $windowStart);
            })
            ->get();

        foreach ($requests as $req) {
            $detail = $req->leaveDetail;
            if (!$detail || empty($detail->start_date) || empty($detail->end_date)) {
                continue;
            }
            $kid = (int) $req->karyawan_id;
            $byKaryawan[$kid][] = [
                'start_date' => Carbon::parse($detail->start_date)->toDateString(),
                'end_date' => Carbon::parse($detail->end_date)->toDateString(),
            ];
        }

        return $byKaryawan;
    }

    /**
     * Cek apakah karyawan memiliki Cuti Melahirkan yang kembali bekerja (return_month)
     * pada periode payroll tertentu (digunakan untuk label Prorata di slip gaji).
     *
     * @return array{phase: string, start_date: string, end_date: string, period_start: string, period_end: string}|null
     */
    public static function findReturnMonthForEmployee(int $karyawanId, string $periodeYm, ?string $grade = null): ?array
    {
        if ($karyawanId <= 0 || !preg_match('/^\d{4}-\d{2}$/', $periodeYm)) {
            return null;
        }

        $map = self::mapApprovedMaternityLeaves([$karyawanId], $periodeYm);
        $leaves = $map[$karyawanId] ?? [];
        foreach ($leaves as $leave) {
            $classified = self::classifyPeriod($leave['start_date'], $leave['end_date'], $periodeYm, $grade);
            if ($classified && $classified['phase'] === self::PHASE_RETURN_MONTH) {
                return $classified;
            }
        }

        return null;
    }

    /**
     * Terapkan aturan Cuti Melahirkan pada baris preview KalkulasiPayrollController::showData.
     *
     * @param  iterable<object>  $rows
     */
    public static function applyToPreviewRows($rows, string $periodeYm): void
    {
        $candidateIds = [];
        foreach ($rows as $row) {
            if (!empty($row->payroll_id)) {
                continue;
            }
            $kid = (int) ($row->karyawan_id ?? $row->id_karyawan ?? 0);
            if ($kid > 0) {
                $candidateIds[] = $kid;
            }
        }

        $candidateIds = array_values(array_unique($candidateIds));
        if ($candidateIds === []) {
            return;
        }

        $leavesByKaryawan = self::mapApprovedMaternityLeaves($candidateIds, $periodeYm);
        if ($leavesByKaryawan === []) {
            return;
        }

        $gradesById = MasterKaryawan::query()
            ->whereIn('id', array_keys($leavesByKaryawan))
            ->pluck('grade', 'id')
            ->all();

        foreach ($rows as $row) {
            if (!empty($row->payroll_id)) {
                continue;
            }
            $kid = (int) ($row->karyawan_id ?? $row->id_karyawan ?? 0);
            $leaves = $leavesByKaryawan[$kid] ?? [];
            if ($leaves === []) {
                continue;
            }

            $grade = $gradesById[$kid] ?? null;
            $matched = self::pickPrimaryPhase($leaves, $periodeYm, $grade);
            if ($matched === null) {
                continue;
            }

            $phase = $matched['phase'];

            if ($phase === self::PHASE_FULL_LEAVE) {
                $row->gaji_pokok = 0;
                $row->tunjangan_kerja = 0;
                $row->bonus = 0;
                $row->incentive = 0;
                $row->pencadangan_upah = 0;
                $row->jamsostek = 0;
                $row->bpjs_kesehatan = 0;
                if (property_exists($row, 'nominal_potongan_kantor')) {
                    $row->nominal_potongan_kantor = 0;
                }
                if (property_exists($row, 'nominal_p_kantor')) {
                    $row->nominal_p_kantor = 0;
                }
                $row->loan = 0;
                $row->sanksi = 0;
                $row->potongan_absen = 0;
                $row->potongan_lainnya = 0;
                $row->pajak = 0;
                $row->take_home_pay = 0;
                $row->maternity_leave_phase = self::PHASE_FULL_LEAVE;
                if (empty($row->keterangan)) {
                    $row->keterangan = 'Cuti Melahirkan (Bulan Cuti Penuh - Rp 0)';
                }
                continue;
            }

            if ($phase === self::PHASE_START_MONTH) {
                // Bulan pertama pengajuan: tidak ada potongan absen/prorata apapun, tetap dapat gaji full
                $oldPotongan = (float) ($row->potongan_absen ?? 0);
                if ($oldPotongan > 0) {
                    $row->potongan_absen = 0;
                    $row->take_home_pay = round((float) ($row->take_home_pay ?? 0) + $oldPotongan, 0);
                }
                $row->maternity_leave_phase = self::PHASE_START_MONTH;
                if (empty($row->keterangan)) {
                    $row->keterangan = 'Cuti Melahirkan (Bulan Mulai Cuti - Gaji Penuh)';
                }
                continue;
            }

            if ($phase === self::PHASE_RETURN_MONTH) {
                // Bulan kembali masuk kerja: prorata berlaku untuk SEMUA status karyawan (Contract, Permanent, Special, dll.)
                $hariKerja = (float) ($row->hari_kerja ?? 0);
                if ($hariKerja <= 0) {
                    continue;
                }

                $idJabatan = isset($row->id_jabatan) ? (int) $row->id_jabatan : null;
                $leaveWorkDaysBeforeReturn = (float) self::countWorkDaysBeforeReturn(
                    $periodeYm,
                    $idJabatan,
                    $grade,
                    $matched['end_date']
                );

                $statusKaryawan = (string) ($row->status_karyawan ?? '');
                $isExemptFromDailyAbsence = in_array($statusKaryawan, ['Permanent', 'Contract', 'Special'], true);
                $masukKerja = (float) ($row->masuk_kerja ?? 0);

                if ($isExemptFromDailyAbsence) {
                    // Permanent/Contract/Special: prorata tepat sesuai hari kerja cuti sebelum tanggal kembali
                    $prorataTidakHadir = $leaveWorkDaysBeforeReturn;
                    if ($masukKerja <= 0) {
                        $row->tidak_hadir = $prorataTidakHadir;
                    }
                } elseif ($masukKerja > 0) {
                    // Training/Probation/dll dengan rekap: minimal hari cuti sebelum kembali, atau tidak_hadir aktual bila lebih besar
                    $prorataTidakHadir = max($leaveWorkDaysBeforeReturn, (float) ($row->tidak_hadir ?? 0));
                } else {
                    $prorataTidakHadir = $leaveWorkDaysBeforeReturn;
                    $row->tidak_hadir = $prorataTidakHadir;
                }

                $prorataTidakHadir = max(0.0, min($hariKerja, $prorataTidakHadir));
                $oldPotongan = (float) ($row->potongan_absen ?? 0);

                if ($prorataTidakHadir > 0) {
                    $newPotongan = PayrollTrainingSpecialLeaveService::calcPotonganAbsen(
                        (float) ($row->gaji_pokok ?? 0),
                        (float) ($row->tunjangan_kerja ?? 0),
                        $hariKerja,
                        $prorataTidakHadir
                    );
                } else {
                    $newPotongan = 0.0;
                }

                $row->potongan_absen = $newPotongan;
                $row->take_home_pay = round((float) ($row->take_home_pay ?? 0) + ($oldPotongan - $newPotongan), 0);
                $row->maternity_leave_phase = self::PHASE_RETURN_MONTH;
                $row->maternity_return_date = $matched['end_date'];

                if (empty($row->keterangan)) {
                    $tglMasuk = Carbon::parse($matched['end_date'])->locale('id')->translatedFormat('d F Y');
                    $row->keterangan = "Prorata masuk kerja setelah Cuti Melahirkan (mulai {$tglMasuk})";
                }
            }
        }
    }

    /**
     * Hitung jumlah hari kerja dalam periode payroll yang jatuh sebelum tanggal kembali bekerja ($returnDateYmd).
     */
    public static function countWorkDaysBeforeReturn(
        string $periodeYm,
        ?int $idJabatan,
        ?string $grade,
        string $returnDateYmd
    ): int {
        $eligible = PayrollWorkingDaysService::eligibleWorkDateIndex($periodeYm, $idJabatan, $grade);
        $count = 0;

        foreach (array_keys($eligible) as $ymd) {
            if ($ymd < $returnDateYmd) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<array{start_date: string, end_date: string}>  $leaves
     * @return array{phase: string, start_date: string, end_date: string, period_start: string, period_end: string}|null
     */
    private static function pickPrimaryPhase(array $leaves, string $periodeYm, ?string $grade): ?array
    {
        $priority = [
            self::PHASE_FULL_LEAVE => 3,
            self::PHASE_RETURN_MONTH => 2,
            self::PHASE_START_MONTH => 1,
        ];

        $best = null;
        $bestScore = 0;

        foreach ($leaves as $leave) {
            $classified = self::classifyPeriod($leave['start_date'], $leave['end_date'], $periodeYm, $grade);
            if ($classified === null) {
                continue;
            }
            $score = $priority[$classified['phase']] ?? 0;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $classified;
            }
        }

        return $best;
    }

    /**
     * @return list<int>
     */
    public static function hrMaternityTypeIds(): array
    {
        return HrSpecialLeaveType::query()
            ->where('is_active', true)
            ->get(['id', 'code', 'name'])
            ->filter(fn ($row) => self::isMaternitySpecialLeaveType($row))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function legacyMaternityTypeIds(): array
    {
        try {
            return DB::connection('intilab_apps')
                ->table('special_leave_types')
                ->where('is_active', 1)
                ->get(['id', 'name'])
                ->filter(fn ($row) => self::isMaternitySpecialLeaveType($row))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
