<?php

namespace App\Console\Commands;

use App\Models\NewRecruitment;
use App\Services\CandidateOfferingDecisionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoApproveCandidateOffering extends Command
{
    protected $signature = 'recruitment:auto-approve-candidate-offering
                            {--dry-run : Tampilkan kandidat yang akan di-approve tanpa mengubah data}';

    protected $description = 'Auto-approve offering salary kandidat yang tidak merespon sampai H+2 pukul 23:00 WIB';

    public function handle(): int
    {
        $now = Carbon::now('Asia/Jakarta');
        $dryRun = (bool) $this->option('dry-run');
        $approved = 0;
        $failed = 0;

        $this->info('Cek offering kandidat yang lewat tenggat H+2 pukul 23:00 WIB...');

        NewRecruitment::query()
            ->whereIn('status', ['salary_offer', 'internal_sallary_offer'])
            ->orderBy('id')
            ->chunkById(100, function ($candidates) use ($now, $dryRun, &$approved, &$failed) {
                foreach ($candidates as $candidate) {
                    if (!CandidateOfferingDecisionService::isWaitingCandidateOffering($candidate)
                        || !CandidateOfferingDecisionService::isDeadlinePassed($candidate, $now)
                    ) {
                        continue;
                    }

                    if ($dryRun) {
                        $approved++;
                        $this->line("Dry run: #{$candidate->id} {$candidate->nama_lengkap}");
                        continue;
                    }

                    try {
                        DB::transaction(function () use ($candidate, $now) {
                            $locked = DB::table('new_recruitment')
                                ->where('id', $candidate->id)
                                ->lockForUpdate()
                                ->first();
                            if (!$locked) {
                                return;
                            }

                            CandidateOfferingDecisionService::approveIfExpired($locked, $now);
                        });
                        $approved++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('Auto-approve candidate offering failed', [
                            'recruitment_id' => $candidate->id,
                            'message' => $e->getMessage(),
                        ]);
                        $this->error("Gagal #{$candidate->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Selesai. Auto-approve: {$approved}; gagal: {$failed}.");

        return $failed > 0 ? 1 : 0;
    }
}
