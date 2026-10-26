<?php

namespace App\Services\Hr;

use App\Models\Hr\HrLeaveBalanceLedger;
use App\Models\Hr\HrLeaveBalancePeriod;
use App\Models\MasterKaryawan;
use App\Services\Greatday\LeaveAlpaAttendanceScope;
use App\Services\Greatday\LeaveBalanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HrLeaveBalanceLedgerService
{
    public function netLedgerDaysForPeriod(HrLeaveBalancePeriod $period): int
    {
        return (int) HrLeaveBalanceLedger::query()
            ->where('balance_period_id', $period->id)
            ->where('is_void', false)
            ->sum('days_delta');
    }

    public function alpaDaysForPeriod(HrLeaveBalancePeriod $period): int
    {
        return (int) HrLeaveBalanceLedger::query()
            ->where('balance_period_id', $period->id)
            ->where('entry_type', HrLeaveBalanceLedger::TYPE_ALPA)
            ->where('is_void', false)
            ->sum('days_delta');
    }

    public function adjustmentNetDaysForPeriod(HrLeaveBalancePeriod $period): int
    {
        return (int) HrLeaveBalanceLedger::query()
            ->where('balance_period_id', $period->id)
            ->where('entry_type', HrLeaveBalanceLedger::TYPE_ADJUSTMENT)
            ->where('is_void', false)
            ->sum('days_delta');
    }

    /**
     * Net ledger + alpa untuk tampilan saldo (alpa diabaikan jika grade tidak wajib absen).
     *
     * @return array{ledger_net: int, alpa_days: int, ledger_used_days: int}
     */
    public function totalsForSummary(MasterKaryawan $employee, HrLeaveBalancePeriod $period): array
    {
        $ledgerNet = $this->netLedgerDaysForPeriod($period);
        $alpaDays = $this->alpaDaysForPeriod($period);

        if (LeaveAlpaAttendanceScope::isExemptFromAlpaLedger($employee)) {
            $ledgerNet -= $alpaDays;
            $alpaDays = 0;
        }

        return [
            'ledger_net' => $ledgerNet,
            'alpa_days' => max(0, $alpaDays),
            'ledger_used_days' => max(0, $ledgerNet),
        ];
    }

    /**
     * Batalkan entri alpa otomatis untuk karyawan yang dikecualikan (bersihkan sync lama).
     */
    public function voidAutomaticAlpaForExemptEmployees(?int $karyawanId = null): int
    {
        $query = MasterKaryawan::query()->where('is_active', true);
        if ($karyawanId !== null && $karyawanId > 0) {
            $query->where('id', $karyawanId);
        }

        $reason = 'Pengecualian alpa — grade tidak wajib absen mesin/Greatday';
        $now = Carbon::now();
        $voided = 0;

        foreach ($query->get() as $employee) {
            if (!LeaveAlpaAttendanceScope::isExemptFromAlpaLedger($employee)) {
                continue;
            }

            $voided += HrLeaveBalanceLedger::query()
                ->where('karyawan_id', $employee->id)
                ->where('entry_type', HrLeaveBalanceLedger::TYPE_ALPA)
                ->where('source', HrLeaveBalanceLedger::SOURCE_ATTENDANCE)
                ->where('is_void', false)
                ->update([
                    'is_void' => true,
                    'voided_at' => $now,
                    'voided_by_name' => 'system:leave_alpa_exempt',
                    'void_reason' => $reason,
                ]);
        }

        return $voided;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ledgerRowsForPeriod(HrLeaveBalancePeriod $period): array
    {
        return HrLeaveBalanceLedger::query()
            ->where('balance_period_id', $period->id)
            ->where('is_void', false)
            ->orderByDesc('reference_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (HrLeaveBalanceLedger $row) {
                return [
                    'id' => $row->id,
                    'entry_type' => $row->entry_type,
                    'days_delta' => (int) $row->days_delta,
                    'reference_date' => $row->reference_date ? $row->reference_date->format('Y-m-d') : null,
                    'notes' => $row->notes,
                    'source' => $row->source,
                    'created_at' => $row->created_at ? $row->created_at->format('Y-m-d H:i') : null,
                    'created_by_name' => $row->created_by_name,
                ];
            })
            ->all();
    }

    /**
     * Batalkan entri alpa otomatis untuk satu hari jika aturan absensi/izin berubah.
     */
    public function voidAutomaticAlpaDay(
        MasterKaryawan $employee,
        string $referenceDateYmd,
        string $reason = 'Alpa dibatalkan — ada absen masuk/keluar atau izin/cuti disetujui'
    ): bool {
        $externalRef = 'alpa:' . $employee->id . ':' . Carbon::parse($referenceDateYmd)->format('Y-m-d');
        $existing = HrLeaveBalanceLedger::query()->where('external_ref', $externalRef)->first();
        if (!$existing || $existing->is_void) {
            return false;
        }
        if ($existing->entry_type !== HrLeaveBalanceLedger::TYPE_ALPA
            || $existing->source !== HrLeaveBalanceLedger::SOURCE_ATTENDANCE) {
            return false;
        }

        $existing->is_void = true;
        $existing->voided_at = Carbon::now();
        $existing->voided_by_name = 'system:attendance';
        $existing->void_reason = $reason;
        $existing->save();

        return true;
    }

    public function recordAlpaDay(
        MasterKaryawan $employee,
        string $referenceDateYmd,
        ?string $notes = null
    ): ?HrLeaveBalanceLedger {
        if (LeaveAlpaAttendanceScope::isExemptFromAlpaLedger($employee)) {
            return null;
        }

        $deduct = max(1, (int) config('greatday.leave_alpa_days_delta', 1));
        $period = app(LeaveBalanceService::class)->ensureActivePeriod($employee);
        $ref = Carbon::parse($referenceDateYmd)->startOfDay();
        $periodStart = Carbon::parse($period->period_start)->startOfDay();
        $periodEnd = Carbon::parse($period->period_end)->startOfDay();

        if ($ref->lt($periodStart) || $ref->gt($periodEnd)) {
            return null;
        }

        $cutover = Carbon::parse(config('greatday.leave_balance_cutover_at', '2026-04-01'))->startOfDay();
        if ($ref->lt($cutover)) {
            return null;
        }

        $externalRef = 'alpa:' . $employee->id . ':' . $ref->format('Y-m-d');

        $existing = HrLeaveBalanceLedger::query()->where('external_ref', $externalRef)->first();
        if ($existing) {
            return $existing->is_void ? null : $existing;
        }

        $now = Carbon::now();

        return HrLeaveBalanceLedger::create([
            'karyawan_id' => $employee->id,
            'balance_period_id' => $period->id,
            'entry_type' => HrLeaveBalanceLedger::TYPE_ALPA,
            'days_delta' => $deduct,
            'reference_date' => $ref->format('Y-m-d'),
            'source' => HrLeaveBalanceLedger::SOURCE_ATTENDANCE,
            'external_ref' => $externalRef,
            'notes' => $notes ?: 'Alpa — potong saldo cuti tahunan',
            'created_by_name' => 'system:attendance',
            'created_at' => $now,
        ]);
    }

    /**
     * Batalkan alpa dan buat permohonan cuti/izin Approved HRD (description = keterangan koreksi).
     *
     * @return array{voided: HrLeaveBalanceLedger, hr_request: \App\Models\Hr\HrRequest|null, target: string}
     */
    public function reclassifyAlpaEntry(
        int $ledgerId,
        string $targetType,
        string $notes,
        ?MasterKaryawan $actor = null,
        ?string $actorName = null,
        string $permissionKind = 'event'
    ): array {
        $target = strtolower(trim($targetType));
        if (!in_array($target, ['izin', 'cuti'], true)) {
            throw new InvalidArgumentException('Jenis koreksi harus izin atau cuti.');
        }

        $notes = trim($notes);
        if ($notes === '') {
            throw new InvalidArgumentException('Keterangan koreksi wajib diisi.');
        }

        $row = HrLeaveBalanceLedger::query()->find($ledgerId);
        if (!$row || $row->is_void) {
            throw new InvalidArgumentException('Entri alpa tidak ditemukan atau sudah dibatalkan.');
        }
        if ($row->entry_type !== HrLeaveBalanceLedger::TYPE_ALPA) {
            throw new InvalidArgumentException('Hanya entri alpa yang dapat dikoreksi.');
        }

        $employee = MasterKaryawan::query()->find((int) $row->karyawan_id);
        if (!$employee) {
            throw new InvalidArgumentException('Karyawan tidak ditemukan.');
        }

        $refYmd = $row->reference_date ? $row->reference_date->format('Y-m-d') : null;
        if ($refYmd === null) {
            throw new InvalidArgumentException('Tanggal referensi alpa tidak valid.');
        }

        $days = (int) $row->days_delta;
        if ($days <= 0) {
            throw new InvalidArgumentException('Entri alpa tidak valid.');
        }

        $leaveService = app(LeaveBalanceService::class);
        $actorLabel = $actorName ?: ($actor ? $actor->nama_lengkap : 'HRD');
        $voidReason = sprintf(
            'HRD reklasifikasi alpa → %s (%s): %s',
            $target,
            $refYmd,
            $notes
        );

        return DB::transaction(function () use (
            $row,
            $employee,
            $target,
            $notes,
            $actor,
            $actorLabel,
            $voidReason,
            $refYmd,
            $leaveService,
            $permissionKind
        ) {
            $voided = $this->voidEntry((int) $row->id, $voidReason, $actorLabel);

            $hrRequest = null;
            if ($target === 'cuti') {
                if ($leaveService->hasApprovedPermissionOnDate($employee, $refYmd)) {
                    throw new InvalidArgumentException(
                        'Tanggal tersebut sudah ada izin Approved HRD. Pilih koreksi ke izin, bukan cuti.'
                    );
                }
                if (!$leaveService->hasApprovedAnnualLeaveOnDate($employee, $refYmd)) {
                    $hrRequest = app(AlpaReclassifyHrRequestService::class)->createApproved(
                        $employee,
                        'cuti',
                        $refYmd,
                        $notes,
                        $actor,
                        $actorLabel
                    );
                }
            } else {
                if ($leaveService->hasApprovedAnnualLeaveOnDate($employee, $refYmd)) {
                    throw new InvalidArgumentException(
                        'Tanggal tersebut sudah ada cuti Approved HRD. Pilih koreksi ke cuti, bukan izin.'
                    );
                }
                if (!$leaveService->hasApprovedPermissionOnDate($employee, $refYmd)) {
                    $hrRequest = app(AlpaReclassifyHrRequestService::class)->createApproved(
                        $employee,
                        'izin',
                        $refYmd,
                        $notes,
                        $actor,
                        $actorLabel,
                        $permissionKind
                    );
                }
            }

            return [
                'voided' => $voided,
                'hr_request' => $hrRequest,
                'target' => $target,
            ];
        });
    }

    public function recordManualAdjustment(
        MasterKaryawan $employee,
        int $daysDelta,
        string $notes,
        ?MasterKaryawan $actor = null,
        ?string $actorName = null,
        ?string $referenceDateYmd = null
    ): HrLeaveBalanceLedger {
        if ($daysDelta === 0) {
            throw new InvalidArgumentException('Koreksi hari tidak boleh nol.');
        }

        $notes = trim($notes);
        if ($notes === '') {
            throw new InvalidArgumentException('Keterangan koreksi wajib diisi.');
        }

        $period = app(LeaveBalanceService::class)->ensureActivePeriod($employee);
        $now = Carbon::now();
        $name = $actorName ?: ($actor ? $actor->nama_lengkap : 'HRD');

        $referenceDate = $referenceDateYmd ?: $now->toDateString();

        return HrLeaveBalanceLedger::create([
            'karyawan_id' => $employee->id,
            'balance_period_id' => $period->id,
            'entry_type' => HrLeaveBalanceLedger::TYPE_ADJUSTMENT,
            'days_delta' => $daysDelta,
            'reference_date' => $referenceDate,
            'source' => HrLeaveBalanceLedger::SOURCE_HRD,
            'external_ref' => null,
            'notes' => $notes,
            'created_by_karyawan_id' => $actor ? $actor->id : null,
            'created_by_name' => $name,
            'created_at' => $now,
        ]);
    }

    public function voidEntry(int $ledgerId, string $reason, ?string $voidedByName = null): HrLeaveBalanceLedger
    {
        $row = HrLeaveBalanceLedger::query()->find($ledgerId);
        if (!$row) {
            throw new InvalidArgumentException('Entri ledger tidak ditemukan.');
        }
        if ($row->is_void) {
            return $row;
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Alasan pembatalan wajib diisi.');
        }

        $row->is_void = true;
        $row->voided_at = Carbon::now();
        $row->voided_by_name = $voidedByName ?: 'HRD';
        $row->void_reason = $reason;
        $row->save();

        return $row->fresh();
    }
}
