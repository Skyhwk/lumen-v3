<?php

namespace App\Console\Commands;

use App\Models\NewRecruitment;
use App\Services\SendEmail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendPendingAssessmentInvitations extends Command
{
    protected $signature = 'pendingassessmentinvitations';

    protected $description = 'Kirim undangan assessment untuk kandidat yang memilih nanti atau berhenti di tengah assessment';

    public function handle(): int
    {
        $sent = 0;
        $failed = 0;
        $now = Carbon::now('Asia/Jakarta');

        NewRecruitment::query()
            ->with('personnelRequest')
            ->where('status', 'assessment')
            ->where('is_active', 1)
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunkById(100, function ($candidates) use ($now, &$sent, &$failed) {
                foreach ($candidates as $candidate) {
                    $choiceMissing = empty($candidate->assessment_delivery_choice)
                        && empty($candidate->assessment_invitation_sent_at)
                        && Carbon::parse($candidate->created_at)->addMinutes(30)->lte($now);
                    $pausedSession = $this->pausedSessionContext($candidate->id, $candidate->assessment_resume_reminder_sent_at, $now);
                    $resumeNeeded = $pausedSession !== null;

                    if (!$choiceMissing && !$resumeNeeded) {
                        continue;
                    }

                    try {
                        $position = $candidate->personnelRequest->divisi_alias
                            ?? $candidate->personnelRequest->posisi
                            ?? $candidate->posisi_dilamar
                            ?? 'Posisi yang dilamar';
                        $url = rtrim(env('PORTALV4', 'https://portal.intilab.com'), '/')
                            . '/public/recruitment/assessment/' . rawurlencode($candidate->token);
                        $body = view('Email.recruitment-assessment-invitation', [
                            'nama_lengkap' => $candidate->nama_lengkap,
                            'posisi_dilamar' => $position,
                            'assessment_url' => $url,
                            'resume_stage' => $pausedSession->category_name ?? null,
                        ])->render();

                        SendEmail::where('to', $candidate->email)
                            ->where('subject', 'Undangan Career Assessment - PT Inti Surya Laboratorium')
                            ->where('body', $body)
                            ->where('karyawan', 'Recruitment System')
                            ->noReply('PT Inti Surya Laboratorium')
                            ->replyToAtsHrd()
                            ->send();

                        DB::table('new_recruitment')->where('id', $candidate->id)->update([
                            'assessment_invitation_sent_at' => $now,
                            'assessment_resume_reminder_sent_at' => $resumeNeeded ? $now : null,
                            'updated_at' => $now,
                        ]);
                        $sent++;
                    } catch (\Throwable $exception) {
                        $failed++;
                        Log::warning('Pending assessment invitation failed', [
                            'recruitment_id' => $candidate->id,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Undangan assessment terkirim: {$sent}; gagal: {$failed}.");

        return $failed > 0 ? 1 : 0;
    }

    private function pausedSessionContext($recruitmentId, $lastReminderAt, Carbon $now)
    {
        $attempt = DB::table('assessment_attempts')
            ->where('recruitment_id', $recruitmentId)
            ->first();

        if (!$attempt) {
            return null;
        }

        $sessions = DB::table('assessment_sessions')
            ->where('assessment_attempt_id', $attempt->id)
            ->orderBy('session_order')
            ->get(['category_name', 'status', 'updated_at']);
        $nextSession = $sessions->firstWhere('status', 'in_progress')
            ?: $sessions->firstWhere('status', 'pending');
        $lastActivity = $sessions->max('updated_at');

        if (!$sessions->contains('status', 'completed') || !$nextSession || !$lastActivity) {
            return null;
        }

        $lastActivityAt = Carbon::parse($lastActivity);
        if ($lastActivityAt->addMinutes(30)->gt($now)) {
            return null;
        }

        if ($lastReminderAt && $lastActivityAt->lte(Carbon::parse($lastReminderAt))) {
            return null;
        }

        return $nextSession;
    }
}
