<?php

namespace App\Console\Commands;

use App\Services\RecruitmentStatusService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class RollbackOverdueAssessment extends Command
{
    protected $signature = 'assessmentrejection:rollback {--run-at= : Jam run_at di log yang ingin dikembalikan, format Y-m-d H:i:s}';

    protected $description = 'Kembalikan kandidat yang ditolak command assessmentrejection berdasarkan log';

    public function handle(): int
    {
        $run = $this->findRun();
        if (!$run) {
            $this->error('Run assessmentrejection tidak ditemukan di log.');
            return 1;
        }

        $now = Carbon::now('Asia/Jakarta');
        $restoredIds = [];
        $skipped = [];

        $this->log('info', 'Rollback assessmentrejection mulai', [
            'rollback_at' => $now->toDateTimeString(),
            'source_run_at' => $run['run_at'],
            'affected_ids' => $run['affected_ids'],
        ]);

        foreach ($run['affected_ids'] as $id) {
            $row = DB::table('new_recruitment')->where('id', $id)->first();
            if (!$row) {
                $skipped[] = ['id' => $id, 'reason' => 'Data new_recruitment tidak ditemukan.'];
                $this->log('warning', 'Rollback dilewati', ['id' => $id, 'reason' => 'Data new_recruitment tidak ditemukan.']);
                continue;
            }

            $reason = $this->cannotRestore($row, $run['run_at']);
            if ($reason) {
                $skipped[] = ['id' => $id, 'reason' => $reason];
                $this->log('warning', 'Rollback dilewati', ['id' => $id, 'reason' => $reason]);
                continue;
            }

            $history = RecruitmentStatusService::parseMetaHistory($row);
            array_pop($history);

            DB::table('new_recruitment')->where('id', $id)->update([
                'status' => 'assessment',
                'meta_history' => json_encode(array_values($history)),
                'is_rejected_kandidat' => false,
                'is_rejected_kandidat_by' => null,
                'is_rejected_kandidat_at' => null,
                'is_rejected_kandidat_reason' => null,
                'updated_at' => $now,
            ]);

            $restoredIds[] = $id;
            $this->log('info', 'Kandidat dikembalikan ke assessment', [
                'id' => $id,
                'source_run_at' => $run['run_at'],
            ]);
        }

        $this->log('info', 'Rollback assessmentrejection selesai', [
            'rollback_at' => $now->toDateTimeString(),
            'source_run_at' => $run['run_at'],
            'restored_ids' => $restoredIds,
            'restored_count' => count($restoredIds),
            'skipped' => $skipped,
        ]);

        $this->info('Rollback selesai. Dikembalikan: ' . count($restoredIds) . '. Dilewati: ' . count($skipped) . '.');

        return 0;
    }

    private function findRun(): ?array
    {
        $directory = storage_path('logs/assessment_rejection');
        if (!File::isDirectory($directory)) {
            return null;
        }

        $files = File::files($directory);
        usort($files, function ($left, $right) {
            return strcmp($right->getFilename(), $left->getFilename());
        });

        $wantedRunAt = trim((string) $this->option('run-at'));
        $matched = null;

        foreach ($files as $file) {
            $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                if (strpos($line, 'Command assessmentrejection selesai') === false) {
                    continue;
                }

                $jsonStart = strpos($line, '{');
                if ($jsonStart === false) {
                    continue;
                }

                $payload = json_decode(substr($line, $jsonStart), true);
                if (!is_array($payload) || empty($payload['run_at']) || !isset($payload['affected_ids'])) {
                    continue;
                }

                $run = [
                    'run_at' => (string) $payload['run_at'],
                    'affected_ids' => array_values(array_map('intval', (array) $payload['affected_ids'])),
                ];

                if ($wantedRunAt !== '' && $run['run_at'] !== $wantedRunAt) {
                    continue;
                }

                $matched = $run;
            }
        }

        return $matched;
    }

    private function cannotRestore($row, string $runAt): ?string
    {
        if ((string) ($row->status ?? '') !== 'rejected') {
            return 'Status saat ini bukan rejected.';
        }

        if ((int) ($row->is_rejected_kandidat ?? 0) !== 1 || (string) ($row->is_rejected_kandidat_by ?? '') !== 'sistem') {
            return 'Bukan penolakan sistem dari command assessmentrejection.';
        }

        $rejectedAt = $row->is_rejected_kandidat_at ? Carbon::parse($row->is_rejected_kandidat_at)->format('Y-m-d H:i:s') : '';
        if ($rejectedAt !== $runAt) {
            return 'Jam penolakan tidak sama dengan run_at di log.';
        }

        $history = RecruitmentStatusService::parseMetaHistory($row);
        $last = end($history);
        if (($last['status'] ?? null) !== 'rejected_system') {
            return 'Entri terakhir meta_history bukan rejected_system.';
        }

        return null;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel('assessment_rejection')->{$level}($message, $context);
    }
}
