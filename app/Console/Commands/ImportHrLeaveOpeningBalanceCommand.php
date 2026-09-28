<?php

namespace App\Console\Commands;

use App\Models\Hr\HrLeaveBalancePeriod;
use App\Models\MasterKaryawan;
use App\Services\Greatday\LeaveBalanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportHrLeaveOpeningBalanceCommand extends Command
{
    protected $signature = 'greatday:import-leave-opening-balance
                            {path : Path to Excel file}
                            {--dry-run : Validate only, no DB writes}';

    protected $description = 'Import saldo awal cuti (Cuti Diambil) dari sheet REKAP SISA CUTI';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (!is_file($path)) {
            $this->error("File not found: {$path}");

            return 1;
        }

        $service = app(LeaveBalanceService::class);
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('REKAP SISA CUTI 2026')
            ?? $spreadsheet->getSheet($spreadsheet->getSheetCount() - 1);

        $dryRun = (bool) $this->option('dry-run');
        $imported = 0;
        $skipped = 0;
        $warnings = 0;

        foreach ($sheet->getRowIterator(5) as $row) {
            $cells = [];
            foreach ($row->getCellIterator('A', 'N') as $cell) {
                $cells[] = $cell->getCalculatedValue();
            }

            $nik = isset($cells[1]) ? trim((string) $cells[1]) : '';
            if ($nik === '') {
                continue;
            }

            $employee = MasterKaryawan::query()
                ->where('nik_karyawan', $nik)
                ->where('is_active', true)
                ->first();

            if (!$employee) {
                $this->warn("Skip NIK tidak ditemukan: {$nik}");
                $skipped++;

                continue;
            }

            $openingUsed = (int) ($cells[11] ?? 0);
            $notes = isset($cells[13]) ? trim((string) $cells[13]) : '';
            $excelQuota = (int) ($cells[8] ?? 12);

            $periodStartRaw = $cells[9] ?? null;
            $periodEndRaw = $cells[10] ?? null;
            $periodStart = $this->parseExcelDate($periodStartRaw);
            $periodEnd = $this->parseExcelDate($periodEndRaw);

            if (!$periodStart || !$periodEnd) {
                [$from, $to] = $service->leaveYearBounds($employee);
                $periodStart = $from->toDateString();
                $periodEnd = $to->copy()->startOfDay()->toDateString();
            }

            $computedQuota = $service->computeQuotaDays(
                $employee,
                Carbon::parse($periodStart)->startOfDay(),
                Carbon::parse($periodEnd)->startOfDay()
            );

            if ($computedQuota !== $excelQuota) {
                $this->warn("Quota mismatch {$nik}: excel={$excelQuota} computed={$computedQuota}");
                $warnings++;
            }

            if ($dryRun) {
                $imported++;

                continue;
            }

            HrLeaveBalancePeriod::query()
                ->where('karyawan_id', $employee->id)
                ->update(['is_active' => false]);

            HrLeaveBalancePeriod::updateOrCreate(
                [
                    'karyawan_id' => $employee->id,
                    'period_start' => $periodStart,
                ],
                [
                    'period_end' => $periodEnd,
                    'quota_days' => $computedQuota,
                    'opening_used_days' => max(0, $openingUsed),
                    'opening_imported_at' => Carbon::now(),
                    'opening_source' => 'excel_rekap_2026',
                    'notes' => $notes !== '' ? $notes : null,
                    'is_active' => true,
                ]
            );

            $imported++;
        }

        $this->info("Selesai. imported={$imported} skipped={$skipped} warnings={$warnings}" . ($dryRun ? ' (dry-run)' : ''));

        return 0;
    }

    private function parseExcelDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }
        if (is_numeric($value)) {
            return Carbon::createFromTimestampUTC(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($value))->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
