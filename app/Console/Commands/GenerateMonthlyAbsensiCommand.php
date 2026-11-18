<?php

namespace App\Console\Commands;

use App\Services\Hr\MonthlyAbsensiGenerateOrchestrator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyAbsensiCommand extends Command
{
    protected $signature = 'absensi:generate-monthly
                            {--bulan= : Periode YYYY-MM (override semua grade; default per grade dari --as-of)}
                            {--as-of= : Tanggal acuan YYYY-MM-DD untuk hitung bulan target (default: hari ini WIB)}
                            {--grade= : Batasi STAFF atau SUPERVISOR saja}
                            {--force : Generate ulang meski rekap aktif sudah ada}
                            {--dry-run : Simulasi — hitung rekap tanpa menulis database}';

    protected $description = 'Generate rekap absensi bulanan STAFF (bulan kalender) & SUPERVISOR (16–15); cron staff tgl 1, SPV tgl 16';

    public function handle(MonthlyAbsensiGenerateOrchestrator $orchestrator): int
    {
        $timezone = 'Asia/Jakarta';
        $asOfOption = trim((string) $this->option('as-of'));
        $bulanOption = trim((string) $this->option('bulan'));

        $bulanOverride = null;
        if ($bulanOption !== '') {
            if (!preg_match('/^\d{4}-\d{2}$/', $bulanOption)) {
                $this->error('Format --bulan harus YYYY-MM.');

                return 1;
            }
            $bulanOverride = $bulanOption;
        }

        $gradeOption = trim((string) $this->option('grade'));
        if ($gradeOption !== '' && !in_array(strtoupper($gradeOption), ['STAFF', 'SUPERVISOR', 'SPV'], true)) {
            $this->error('Opsi --grade harus STAFF atau SUPERVISOR.');

            return 1;
        }

        $asOfYmd = $asOfOption !== ''
            ? Carbon::parse($asOfOption, $timezone)->toDateString()
            : Carbon::now($timezone)->toDateString();

        $skipExisting = !(bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if ($bulanOverride !== null) {
            $this->info('Generate absensi bulanan — periode override: ' . $bulanOverride . ' (semua grade, WIB)');
        } else {
            $this->info(sprintf(
                'Generate absensi bulanan — STAFF: %s | SUPERVISOR: %s (as-of %s WIB)',
                $orchestrator->resolveTargetBulanYm($asOfYmd),
                \App\Services\Hr\AbsensiPeriodeService::resolveTargetBulanYmSupervisor($asOfYmd),
                $asOfYmd
            ));
        }
        if ($dryRun) {
            $this->warn('Mode DRY-RUN: tidak ada insert/update ke rekap_masuk_kerja atau absensi.');
        }

        $startedAt = microtime(true);

        Log::info('absensi:generate-monthly mulai', [
            'bulan_override' => $bulanOverride,
            'grade_filter' => $gradeOption !== '' ? strtoupper($gradeOption) : null,
            'as_of' => $asOfYmd,
            'skip_existing' => $skipExisting,
            'dry_run' => $dryRun,
            'started_at' => Carbon::now($timezone)->toDateTimeString(),
        ]);

        try {
            $summary = $orchestrator->generateForStaffSupervisor(
                $bulanOverride,
                $skipExisting,
                $dryRun,
                $asOfYmd,
                $gradeOption !== '' ? $gradeOption : null
            );
        } catch (\Throwable $e) {
            $durationSeconds = round(microtime(true) - $startedAt, 2);
            $this->error($e->getMessage());
            Log::error('absensi:generate-monthly gagal total', [
                'error' => $e->getMessage(),
                'bulan_override' => $bulanOverride,
                'as_of' => $asOfYmd,
                'duration_seconds' => $durationSeconds,
                'duration_human' => $this->formatDurationHuman($durationSeconds),
            ]);

            return 1;
        }

        $durationSeconds = round(microtime(true) - $startedAt, 2);

        $metricRows = [
            ['Diproses', $summary['processed']],
        ];
        if ($dryRun) {
            $metricRows[] = ['Akan di-generate (simulasi)', $summary['would_generate'] ?? 0];
        } else {
            $metricRows[] = ['Berhasil generate', $summary['generated']];
        }
        $metricRows[] = ['Dilewati (sudah ada)', $summary['skipped']];
        $metricRows[] = ['Gagal', $summary['failed']];
        $metricRows[] = ['Durasi', $this->formatDurationHuman($durationSeconds)];

        $this->table(['Metrik', 'Nilai'], $metricRows);

        if ($dryRun) {
            $previewRows = array_values(array_filter(
                $summary['details'],
                static fn (array $detail): bool => ($detail['status'] ?? '') === 'would_generate'
            ));
            if ($previewRows !== []) {
                $this->newLine();
                $this->info('Preview rekap (hari kerja vs masuk):');
                $this->table(
                    ['ID', 'NIK', 'Nama', 'Hari kerja', 'Masuk', 'Tidak hadir'],
                    array_map(static function (array $detail): array {
                        return [
                            $detail['karyawan_id'],
                            $detail['nik'] ?? '-',
                            $detail['nama'] ?? '-',
                            $detail['hari_kerja'] ?? '-',
                            $detail['masuk_kerja'] ?? '-',
                            $detail['tidak_hadir'] ?? '-',
                        ];
                    }, $previewRows)
                );
            }
        }

        foreach ($summary['details'] as $detail) {
            $status = $detail['status'] ?? '';
            if ($status === 'generated' || $status === 'would_generate') {
                continue;
            }
            $this->line(sprintf(
                '#%d %s — [%s] %s',
                $detail['karyawan_id'],
                $detail['nik'] ?? '-',
                $status,
                $detail['message'] ?? ''
            ));
        }

        Log::info('absensi:generate-monthly selesai', array_merge($summary, [
            'bulan_override' => $bulanOverride,
            'as_of' => $asOfYmd,
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
