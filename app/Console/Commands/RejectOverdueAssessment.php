<?php

namespace App\Console\Commands;

use App\Models\NewRecruitment;
use App\Services\RecruitmentStatusService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RejectOverdueAssessment extends Command
{
    protected $signature = 'assessmentrejection
                            {--dry-run : Tampilkan kandidat yang akan ditolak tanpa mengubah data}';

    protected $description = 'Reject otomatis kandidat assessment yang sudah dapat link tes tetapi tidak selesai dalam 6 hari';

    private const ACTOR = 'sistem';

    private const REASON = 'Tidak mengerjakan assessment dalam 6 hari sejak link assessment tercatat.';

    public function handle(): int
    {
        $now = Carbon::now('Asia/Jakarta');
        $dryRun = (bool) $this->option('dry-run');
        $affectedIds = [];
        $failed = [];
        $skipped = [];

        $this->log('info', 'Command assessmentrejection mulai', [
            'run_at' => $now->toDateTimeString(),
            'dry_run' => $dryRun,
        ]);

        if ($dryRun) {
            $this->comment('Dry run aktif: tidak ada kandidat yang akan diubah.');
        }

        NewRecruitment::query()
            ->where('status', 'assessment')
            ->where('is_active', 1)
            ->whereNotNull('personnel_request_id')
            ->where('personnel_request_id', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($candidates) use ($now, $dryRun, &$affectedIds, &$failed, &$skipped) {
                foreach ($candidates as $candidate) {
                    $decision = $this->decision($candidate, $now);
                    if ($decision['action'] === 'skip') {
                        if ($decision['log']) {
                            $skipped[] = [
                                'id' => (int) $candidate->id,
                                'reason' => $decision['reason'],
                            ];
                            $this->log('warning', 'Kandidat dilewati', [
                                'id' => (int) $candidate->id,
                                'nama_lengkap' => $candidate->nama_lengkap,
                                'reason' => $decision['reason'],
                                'assessment_at' => $decision['assessment_at'],
                            ]);
                        }
                        continue;
                    }

                    if ($dryRun) {
                        $affectedIds[] = (int) $candidate->id;
                        $this->log('info', 'Dry run: kandidat akan di-reject otomatis', [
                            'id' => (int) $candidate->id,
                            'nama_lengkap' => $candidate->nama_lengkap,
                            'assessment_at' => $decision['assessment_at'],
                        ]);
                        continue;
                    }

                    try {
                        DB::transaction(function () use ($candidate, $now, $decision) {
                            (new RecruitmentStatusService())->update(
                                (int) $candidate->id,
                                'rejected',
                                $now,
                                'rejected_system'
                            );

                            RecruitmentStatusService::markRejectedKandidat(
                                (int) $candidate->id,
                                self::ACTOR,
                                self::REASON,
                                $now
                            );
                        });

                        $affectedIds[] = (int) $candidate->id;
                        $this->log('info', 'Kandidat di-reject otomatis', [
                            'id' => (int) $candidate->id,
                            'nama_lengkap' => $candidate->nama_lengkap,
                            'assessment_at' => $decision['assessment_at'],
                            'is_rejected_kandidat_by' => self::ACTOR,
                            'is_rejected_kandidat_at' => $now->toDateTimeString(),
                        ]);
                    } catch (\Throwable $exception) {
                        $failed[] = [
                            'id' => (int) $candidate->id,
                            'message' => $exception->getMessage(),
                        ];
                        $this->log('error', 'Gagal reject kandidat', [
                            'id' => (int) $candidate->id,
                            'nama_lengkap' => $candidate->nama_lengkap,
                            'assessment_at' => $decision['assessment_at'],
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $this->log('info', 'Command assessmentrejection selesai', [
            'run_at' => $now->toDateTimeString(),
            'affected_ids' => $affectedIds,
            'affected_count' => count($affectedIds),
            'failed' => $failed,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ]);

        $summary = $dryRun
            ? 'Dry run assessment rejection selesai. Akan ditolak: '
            : 'Assessment rejection selesai. Terkena dampak: ';
        $this->info($summary . count($affectedIds) . '. Gagal: ' . count($failed) . '.');

        return count($failed) > 0 ? 1 : 0;
    }

    private function decision($candidate, Carbon $now): array
    {
        if ((int) ($candidate->is_rejected_kandidat ?? 0) === 1) {
            return $this->skip('Sudah ditolak sebelumnya.', null);
        }

        $assessmentAt = $this->historyAt($candidate, 'assessment');
        if (!$assessmentAt) {
            return $this->skip('meta_history tidak punya status assessment beserta at.', null);
        }

        if ($assessmentAt->isFuture()) {
            return $this->skip('at status assessment masih di masa depan.', $assessmentAt->toDateTimeString());
        }

        if ($assessmentAt->copy()->addDays(6)->gt($now)) {
            return [
                'action' => 'skip',
                'log' => false,
                'reason' => 'Belum 6 hari.',
                'assessment_at' => $assessmentAt->toDateTimeString(),
            ];
        }

        if (!$this->hasAssessmentLink($candidate)) {
            return $this->skip('Status assessment sudah lewat 6 hari, tetapi link tes belum tercatat terkirim atau dibuka.', $assessmentAt->toDateTimeString());
        }

        $completed = DB::table('assessment_attempts')
            ->where('recruitment_id', $candidate->id)
            ->where('status', 'completed')
            ->exists();
        if ($completed) {
            return $this->skip('Attempt assessment sudah completed, tetapi status kandidat masih assessment.', $assessmentAt->toDateTimeString());
        }

        return [
            'action' => 'reject',
            'log' => false,
            'reason' => null,
            'assessment_at' => $assessmentAt->toDateTimeString(),
        ];
    }

    private function skip(string $reason, ?string $assessmentAt): array
    {
        return [
            'action' => 'skip',
            'log' => true,
            'reason' => $reason,
            'assessment_at' => $assessmentAt,
        ];
    }

    private function hasAssessmentLink($candidate): bool
    {
        if (trim((string) ($candidate->token ?? '')) === '') {
            return false;
        }

        if (!empty($candidate->assessment_invitation_sent_at)) {
            return true;
        }

        if (($candidate->assessment_delivery_choice ?? '') === 'now') {
            return true;
        }

        return DB::table('assessment_attempts')
            ->where('recruitment_id', $candidate->id)
            ->exists();
    }

    private function historyAt($candidate, string $status): ?Carbon
    {
        $history = RecruitmentStatusService::parseMetaHistory($candidate);
        for ($index = count($history) - 1; $index >= 0; $index--) {
            if (($history[$index]['status'] ?? null) === $status && !empty($history[$index]['at'])) {
                return Carbon::parse($history[$index]['at'], 'Asia/Jakarta');
            }
        }

        return null;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel('assessment_rejection')->{$level}($message, $context);
    }
}
