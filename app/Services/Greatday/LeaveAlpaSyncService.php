<?php

namespace App\Services\Greatday;

use App\Http\Controllers\api\AbsensiController;
use App\Models\MasterKaryawan;
use App\Services\Hr\HrLeaveBalanceLedgerService;
use Carbon\Carbon;
use Symfony\Component\Console\Output\OutputInterface;

class LeaveAlpaSyncService
{
    private const CHUNK_SIZE = 40;

    /**
     * @return array{created: int, skipped: int, employees: int}
     */
    public function syncRange(Carbon $from, Carbon $to, ?int $karyawanId = null, ?OutputInterface $output = null): array
    {
        $created = 0;
        $skipped = 0;
        $employeesProcessed = 0;

        $eligibleIds = [];
        $employeeById = [];

        $query = MasterKaryawan::query()->where('is_active', true);
        if ($karyawanId) {
            $query->where('id', $karyawanId);
        }

        $ledger = app(HrLeaveBalanceLedgerService::class);
        $voidedExempt = $ledger->voidAutomaticAlpaForExemptEmployees($karyawanId);
        if ($voidedExempt > 0) {
            $this->log($output, sprintf('  Void %d entri alpa (grade dikecualikan).', $voidedExempt));
        }

        $balance = app(LeaveBalanceService::class);
        foreach ($query->get() as $employee) {
            if (!$balance->isEligibleForAnnualLeave($employee)) {
                continue;
            }
            if (LeaveAlpaAttendanceScope::isExemptFromAlpaLedger($employee)) {
                continue;
            }
            $eligibleIds[] = (int) $employee->id;
            $employeeById[(int) $employee->id] = $employee;
        }

        $employeesProcessed = count($eligibleIds);
        if ($employeesProcessed === 0) {
            $this->log($output, 'Tidak ada karyawan eligible (Contract/Permanent/Khusus).');

            return ['created' => 0, 'skipped' => 0, 'employees' => 0];
        }

        /** @var AbsensiController $absensi */
        $absensi = app(AbsensiController::class);
        $today = Carbon::now('Asia/Jakarta')->toDateString();

        $monthCursor = $from->copy()->startOfMonth();
        $endMonth = $to->copy()->startOfMonth();
        $months = [];
        while ($monthCursor->lte($endMonth)) {
            $months[] = $monthCursor->format('Y-m');
            $monthCursor->addMonth();
        }

        $this->log($output, sprintf(
            'Sync alpa: %d karyawan, %d bulan (%s — %s)…',
            $employeesProcessed,
            count($months),
            $from->toDateString(),
            $to->toDateString()
        ));

        foreach ($months as $month) {
            $this->log($output, "  Bulan {$month}…", false);

            foreach (array_chunk($eligibleIds, self::CHUNK_SIZE) as $chunk) {
                $records = $absensi->getMonthlyAttendanceBulk($chunk, $month);

                foreach ($records as $row) {
                    if (!$this->isAlpaRecord($row, $today)) {
                        continue;
                    }

                    $ymd = (string) ($row['tanggal'] ?? '');
                    $day = Carbon::parse($ymd)->startOfDay();
                    if ($day->lt($from) || $day->gt($to)) {
                        continue;
                    }

                    $empId = (int) ($row['karyawan_id'] ?? 0);
                    $employee = $employeeById[$empId] ?? null;
                    if (!$employee || LeaveAlpaAttendanceScope::isExemptFromAlpaLedger($employee)) {
                        continue;
                    }

                    $entry = $ledger->recordAlpaDay($employee, $ymd);
                    if (!$entry) {
                        continue;
                    }
                    if ($entry->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            }

            $this->log($output, ' selesai.');
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'employees' => $employeesProcessed,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isAlpaRecord(array $row, string $today): bool
    {
        $tanggal = $row['tanggal'] ?? '';
        if ($tanggal === '' || $tanggal > $today) {
            return false;
        }

        $shift = strtoupper(trim((string) ($row['shift'] ?? '')));
        $hari = (string) ($row['hari'] ?? '');
        $isWeekend = in_array($hari, ['Sabtu', 'Minggu'], true);

        if ($shift === 'LIBUR' || $shift === 'OFF') {
            return false;
        }

        if ($isWeekend && empty($row['masuk'])) {
            return false;
        }

        if (!empty($row['masuk'])) {
            return false;
        }

        return true;
    }

    private function log(?OutputInterface $output, string $message, bool $newline = true): void
    {
        if ($output === null) {
            return;
        }
        if ($newline) {
            $output->writeln($message);
        } else {
            $output->write($message);
        }
    }
}
