<?php

namespace App\Console\Commands;

use App\Services\Hr\MonthlyAbsensiGenerateOrchestrator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyAbsensiCommand extends Command
{
    protected $signature = 'absensi:generate-monthly
                            {--bulan= : Periode YYYY-MM (default: bulan sebelumnya menurut WIB)}
                            {--as-of= : Tanggal acuan YYYY-MM-DD untuk hitung bulan target (default: hari ini WIB)}
                            {--force : Generate ulang meski rekap aktif sudah ada}';

    protected $description = 'Generate rekap absensi bulanan otomatis untuk karyawan STAFF & SUPERVISOR (cron: tgl 1 jam 10:00 WIB, periode = bulan sebelumnya)';

    public function handle(MonthlyAbsensiGenerateOrchestrator $orchestrator): int
    {
        $timezone = 'Asia/Jakarta';
        $asOfOption = trim((string) $this->option('as-of'));
        $bulanOption = trim((string) $this->option('bulan'));

        if ($bulanOption !== '') {
            if (!preg_match('/^\d{4}-\d{2}$/', $bulanOption)) {
                $this->error('Format --bulan harus YYYY-MM.');

                return 1;
            }
            $bulanTarget = $bulanOption;
        } else {
            $asOfYmd = $asOfOption !== ''
                ? Carbon::parse($asOfOption, $timezone)->toDateString()
                : Carbon::now($timezone)->toDateString();
            $bulanTarget = $orchestrator->resolveTargetBulanYm($asOfYmd);
        }

        $skipExisting = !(bool) $this->option('force');

        $this->info('Generate absensi bulanan — periode: ' . $bulanTarget . ' (WIB)');

        $startedAt = microtime(true);

        Log::info('absensi:generate-monthly mulai', [
            'bulan' => $bulanTarget,
            'skip_existing' => $skipExisting,
            'started_at' => Carbon::now($timezone)->toDateTimeString(),
        ]);

        try {
            $summary = $orchestrator->generateForStaffSupervisor($bulanTarget, $skipExisting);
        } catch (\Throwable $e) {
            $durationSeconds = round(microtime(true) - $startedAt, 2);
            $this->error($e->getMessage());
            Log::error('absensi:generate-monthly gagal total', [
                'error' => $e->getMessage(),
                'bulan' => $bulanTarget,
                'duration_seconds' => $durationSeconds,
                'duration_human' => $this->formatDurationHuman($durationSeconds),
            ]);

            return 1;
        }

        $durationSeconds = round(microtime(true) - $startedAt, 2);

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Diproses', $summary['processed']],
                ['Berhasil generate', $summary['generated']],
                ['Dilewati (sudah ada)', $summary['skipped']],
                ['Gagal', $summary['failed']],
                ['Durasi', $this->formatDurationHuman($durationSeconds)],
            ]
        );

        foreach ($summary['details'] as $detail) {
            if (($detail['status'] ?? '') === 'generated') {
                continue;
            }
            $this->line(sprintf(
                '#%d %s — [%s] %s',
                $detail['karyawan_id'],
                $detail['nik'] ?? '-',
                $detail['status'] ?? '-',
                $detail['message'] ?? ''
            ));
        }

        Log::info('absensi:generate-monthly selesai', array_merge($summary, [
            'bulan' => $bulanTarget,
            'duration_seconds' => $durationSeconds,
            'duration_human' => $this->formatDurationHuman($durationSeconds),
            'finished_at' => Carbon::now($timezone)->toDateTimeString(),
        ]));

        return ($summary['failed'] ?? 0) > 0 ? 1 : 0;
    }

    private function formatDurationHuman(float $seconds): string
    {
        if ($seconds < 1) {
            return round($seconds * 1000) . ' ms';
        }
        if ($seconds < 60) {
            return round($seconds, 2) . ' detik';
        }

        $minutes = (int) floor($seconds / 60);
        $remainder = round($seconds - ($minutes * 60));

        return $minutes . ' menit ' . $remainder . ' detik';
    }
}
