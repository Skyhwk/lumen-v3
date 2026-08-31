<?php

namespace App\Console\Commands;

use App\Models\NewRecruitment;
use App\Models\PersonnelRequest;
use App\Models\RecruitmentInterview;
use App\Services\GenerateMessageAtsEmail;
use App\Services\RecruitmentStatusService;
use App\Services\SendEmail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendKeptManagementDecisionReminders extends Command
{
    protected $signature = 'recruitment:send-kept-management-reminders';

    protected $description = 'Kirim ulang email Management Decision tujuh hari setelah kandidat di-keep';

    public function handle()
    {
        $targetEmail = trim((string) env('EMAIL_DIREKTUR_IBU'));
        if ($targetEmail === '') {
            $this->error('EMAIL_DIREKTUR_IBU belum dikonfigurasi.');
            return 1;
        }

        $sent = 0;
        $failed = 0;

        NewRecruitment::query()
            ->where('status', 'management_decision')
            ->where('is_keep', 1)
            ->orderBy('id')
            ->chunkById(100, function ($candidates) use ($targetEmail, &$sent, &$failed) {
                foreach ($candidates as $candidate) {
                    $keptAt = $this->keptAt($candidate);
                    if (!$keptAt || $keptAt->copy()->addDays(7)->isFuture()) {
                        continue;
                    }

                    try {
                        $personnelRequest = PersonnelRequest::with(['detailDivisi', 'detailPosisi', 'detailCabang'])
                            ->find($candidate->personnel_request_id);
                        $interview = RecruitmentInterview::where('new_recruitment_id', $candidate->id)
                            ->where('stage', 'user')
                            ->where('is_active', 1)
                            ->orderByDesc('id')
                            ->first();

                        if (!$personnelRequest || !$interview) {
                            throw new \RuntimeException('Data personnel request atau interview user tidak ditemukan.');
                        }

                        $body = GenerateMessageAtsEmail::bodyEmailHasilInterviewUser(
                            $candidate,
                            $personnelRequest,
                            $interview,
                            'approve'
                        );

                        SendEmail::where('to', $targetEmail)
                            ->where('subject', 'Reminder Permohonan Persetujuan Kandidat - ' . $candidate->nama_lengkap)
                            ->where('body', $body)
                            ->noReply()
                            ->send();

                        DB::transaction(function () use ($candidate, $keptAt) {
                            (new RecruitmentStatusService())->update(
                                $candidate->id,
                                'management_decision',
                                Carbon::now(),
                                'management_decision_keep_reminded',
                                ['kept_at' => $keptAt->toDateTimeString()]
                            );
                            DB::table('new_recruitment')->where('id', $candidate->id)->update(['is_keep' => 0]);
                        });
                        $sent++;
                    } catch (\Throwable $exception) {
                        $failed++;
                        Log::warning('Management decision keep reminder failed', [
                            'recruitment_id' => $candidate->id,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Reminder Management Decision terkirim: {$sent}; gagal: {$failed}.");
        return $failed > 0 ? 1 : 0;
    }

    private function keptAt($candidate)
    {
        $history = RecruitmentStatusService::parseMetaHistory($candidate);
        for ($index = count($history) - 1; $index >= 0; $index--) {
            if (($history[$index]['status'] ?? null) === 'management_decision_keep_reminded') {
                return null;
            }
            if (($history[$index]['status'] ?? null) === 'management_decision_kept') {
                return !empty($history[$index]['at']) ? Carbon::parse($history[$index]['at']) : null;
            }
        }

        return null;
    }
}
