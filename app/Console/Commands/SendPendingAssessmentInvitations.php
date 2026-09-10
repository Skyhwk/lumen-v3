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
    protected $signature = 'recruitment:send-pending-assessment-invitations';

    protected $description = 'Kirim undangan assessment untuk kandidat yang memilih nanti atau berhenti di tengah assessment';

    public function handle(): int
    {
        $result = app(\App\Services\PendingAssessmentInvitationService::class)->send();
        $this->info("Undangan assessment terkirim: {$result['sent']}; gagal: {$result['failed']}.");
        return $result['failed'] > 0 ? 1 : 0;
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
