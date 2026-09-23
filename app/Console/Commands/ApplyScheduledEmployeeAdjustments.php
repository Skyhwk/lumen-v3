<?php

namespace App\Console\Commands;

use App\Services\EmployeeAdjustmentApplyOrchestrator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ApplyScheduledEmployeeAdjustments extends Command
{
    protected $signature = 'employee-adjustment:apply-scheduled
                            {--date= : Tanggal acuan apply (YYYY-MM-DD), default hari ini WIB}
                            {--retry-failed : Ikut coba ulang permohonan apply_status=failed}';

    protected $description = 'Apply perubahan master karyawan/gaji penyesuaian karyawan yang sudah jatuh tempo (cron 07:00 WIB)';

    public function handle(): int
    {
        $timezone = 'Asia/Jakarta';
        $dateOption = trim((string) $this->option('date'));
        $asOf = $dateOption !== ''
            ? Carbon::parse($dateOption, $timezone)->startOfDay()
            : Carbon::now($timezone)->startOfDay();

        $retryFailed = (bool) $this->option('retry-failed');

        $this->info('Memulai apply penyesuaian karyawan terjadwal — acuan: ' . $asOf->toDateString());

        Log::info('employee-adjustment:apply-scheduled mulai', [
            'as_of' => $asOf->toDateString(),
            'retry_failed' => $retryFailed,
        ]);

        $summary = (new EmployeeAdjustmentApplyOrchestrator())->applyDueRecords(
            $asOf,
            'cron',
            $retryFailed
        );

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Diproses', $summary['processed']],
                ['Berhasil apply', $summary['applied']],
                ['Gagal', $summary['failed']],
                ['Dilewati', $summary['skipped']],
            ]
        );

        foreach ($summary['details'] as $detail) {
            $status = ($detail['success'] ?? false) ? 'OK' : 'GAGAL';
            $this->line(sprintf(
                '#%d %s [%s] %s — %s',
                $detail['request_id'],
                $detail['no_document'] ?? '-',
                $detail['request_type'] ?? '-',
                $status,
                $detail['message'] ?? ''
            ));
        }

        Log::info('employee-adjustment:apply-scheduled selesai', $summary);

        return ($summary['failed'] ?? 0) > 0 ? 1 : 0;
    }
}
