<?php

namespace App\Console\Commands;

use App\Models\NewRecruitment;
use App\Services\GenerateMessageAtsEmail;
use App\Services\RecruitmentStatusService;
use App\Services\SendEmail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendCandidateActionReminders extends Command
{
    protected $signature = 'recruitment:send-candidate-action-reminders {--test-email= : Kirim dua email dummy hanya ke alamat ini}';

    protected $description = 'Kirim reminder assessment dan kelengkapan data kandidat';

    public function handle()
    {
        $testEmail = trim((string) $this->option('test-email'));
        if ($testEmail !== '') {
            return $this->sendTestEmails($testEmail);
        }

        $assessmentSent = $this->sendAssessmentReminders();
        $profileSent = $this->sendProfileReminders();
        $failed = $assessmentSent['failed'] + $profileSent['failed'];

        $this->info(
            "Reminder assessment terkirim: {$assessmentSent['sent']}; "
            . "reminder kelengkapan data terkirim: {$profileSent['sent']}; gagal: {$failed}."
        );

        return $failed > 0 ? 1 : 0;
    }

    private function sendTestEmails(string $email): int
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Alamat email test tidak valid.');
            return 1;
        }

        $token = 'dummy-token-assessment';
        $baseUrl = 'http://127.0.0.1:8000';
        $assessmentUrl = $baseUrl . '/public/recruitment/assessment/' . $token;
        $profileUrl = $baseUrl . '/public/recruitment/complete-profile/' . $token;
        $assessmentBody = view('Email.recruitment-assessment-invitation', [
            'nama_lengkap' => 'Harold (Dummy Test)',
            'posisi_dilamar' => 'System Analyst',
            'assessment_url' => $assessmentUrl,
        ])->render();

        SendEmail::where('to', $email)
            ->where('subject', '[TEST] Reminder Career Assessment - PT Inti Surya Laboratorium')
            ->where('body', $assessmentBody)
            ->where('karyawan', 'Recruitment System Test')
            ->noReply('PT Inti Surya Laboratorium')
            ->replyToAtsHrd()
            ->send();

        $profileBody = GenerateMessageAtsEmail::bodyEmailCompleteProfileCandidate((object) [
            'id' => 0,
            'nama_lengkap' => 'Harold (Dummy Test)',
            'jenis_kelamin' => 'Male',
            'posisi_di_lamar' => 'System Analyst',
            'nama_jabatan' => 'System Analyst',
            'link_complete_profile' => $profileUrl,
        ]);

        SendEmail::where('to', $email)
            ->where('subject', '[TEST] Reminder Kelengkapan Data Diri - PT Inti Surya Laboratorium')
            ->where('body', $profileBody)
            ->where('karyawan', 'Recruitment System Test')
            ->noReply('PT Inti Surya Laboratorium')
            ->replyToAtsHrd()
            ->send();

        $this->info("Dua email dummy berhasil dikirim ke {$email}.");
        return 0;
    }

    private function sendAssessmentReminders(): array
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
                    $assessmentAt = $this->historyAt($candidate, 'assessment');
                    if (!$assessmentAt || $assessmentAt->isFuture() || (int) $assessmentAt->diffInDays($now) !== 3) {
                        continue;
                    }

                    if ($this->hasHistoryStatus($candidate, 'assessment_reminder_sent')) {
                        continue;
                    }

                    $completed = DB::table('assessment_attempts')
                        ->where('recruitment_id', $candidate->id)
                        ->where('status', 'completed')
                        ->exists();
                    if ($completed) {
                        continue;
                    }

                    try {
                        $assessmentUrl = rtrim(env('PORTALV4', 'https://portal.intilab.com'), '/')
                            . '/public/recruitment/assessment/' . rawurlencode($candidate->token);
                        $position = $candidate->personnelRequest->divisi_alias
                            ?? $candidate->personnelRequest->posisi
                            ?? $candidate->posisi_dilamar
                            ?? 'Posisi yang dilamar';
                        $body = view('Email.recruitment-assessment-invitation', [
                            'nama_lengkap' => $candidate->nama_lengkap,
                            'posisi_dilamar' => $position,
                            'assessment_url' => $assessmentUrl,
                        ])->render();

                        SendEmail::where('to', $candidate->email)
                            ->where('subject', 'Reminder Career Assessment - PT Inti Surya Laboratorium')
                            ->where('body', $body)
                            ->where('karyawan', 'Recruitment System')
                            ->noReply('PT Inti Surya Laboratorium')
                            ->replyToAtsHrd()
                            ->send();

                        (new RecruitmentStatusService())->update(
                            $candidate->id,
                            'assessment',
                            Carbon::now('Asia/Jakarta'),
                            'assessment_reminder_sent',
                            ['assessment_at' => $assessmentAt->toDateTimeString()]
                        );
                        $sent++;
                    } catch (\Throwable $exception) {
                        $failed++;
                        Log::warning('Candidate assessment reminder failed', [
                            'recruitment_id' => $candidate->id,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        return compact('sent', 'failed');
    }

    private function sendProfileReminders(): array
    {
        $sent = 0;
        $failed = 0;
        $now = Carbon::now('Asia/Jakarta');

        NewRecruitment::query()
            ->with('personnelRequest')
            ->where('status', 'profile_completion')
            ->where('is_active', 1)
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunkById(100, function ($candidates) use ($now, &$sent, &$failed) {
                foreach ($candidates as $candidate) {
                    if (RecruitmentStatusService::hasCompletedProfile($candidate)) {
                        continue;
                    }

                    $profileRequestedAt = $this->historyAt($candidate, 'profile_completion')
                        ?: (!empty($candidate->approved_interview_hrd_at) ? Carbon::parse($candidate->approved_interview_hrd_at) : null);
                    if (!$profileRequestedAt || $profileRequestedAt->isFuture()) {
                        continue;
                    }

                    $reminderDay = (int) $profileRequestedAt->diffInDays($now);
                    if ($reminderDay < 1 || $reminderDay > 3) {
                        continue;
                    }

                    $historyStatus = 'profile_completion_reminder_day_' . $reminderDay;
                    if ($this->hasHistoryStatus($candidate, $historyStatus)) {
                        continue;
                    }

                    try {
                        $profileUrl = rtrim(env('PORTALV4', 'https://portal.intilab.com'), '/')
                            . '/public/recruitment/complete-profile/' . rawurlencode($candidate->token);
                        $position = $candidate->personnelRequest->divisi_alias
                            ?? $candidate->personnelRequest->posisi
                            ?? $candidate->posisi_dilamar
                            ?? 'Posisi yang dilamar';
                        $body = GenerateMessageAtsEmail::bodyEmailCompleteProfileCandidate((object) [
                            'id' => $candidate->id,
                            'nama_lengkap' => $candidate->nama_lengkap,
                            'jenis_kelamin' => $candidate->jenis_kelamin,
                            'posisi_di_lamar' => $position,
                            'nama_jabatan' => $position,
                            'link_complete_profile' => $profileUrl,
                        ]);

                        SendEmail::where('to', $candidate->email)
                            ->where('subject', 'Reminder Kelengkapan Data Diri - PT Inti Surya Laboratorium')
                            ->where('body', $body)
                            ->where('karyawan', 'Recruitment System')
                            ->noReply('PT Inti Surya Laboratorium')
                            ->replyToAtsHrd()
                            ->send();

                        (new RecruitmentStatusService())->update(
                            $candidate->id,
                            'profile_completion',
                            Carbon::now('Asia/Jakarta'),
                            $historyStatus,
                            ['profile_requested_at' => $profileRequestedAt->toDateTimeString()]
                        );
                        $sent++;
                    } catch (\Throwable $exception) {
                        $failed++;
                        Log::warning('Candidate profile completion reminder failed', [
                            'recruitment_id' => $candidate->id,
                            'reminder_day' => $reminderDay,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        return compact('sent', 'failed');
    }

    private function historyAt($candidate, string $status): ?Carbon
    {
        $history = RecruitmentStatusService::parseMetaHistory($candidate);
        for ($index = count($history) - 1; $index >= 0; $index--) {
            if (($history[$index]['status'] ?? null) === $status && !empty($history[$index]['at'])) {
                return Carbon::parse($history[$index]['at']);
            }
        }

        return null;
    }

    private function hasHistoryStatus($candidate, string $status): bool
    {
        return collect(RecruitmentStatusService::parseMetaHistory($candidate))
            ->contains(fn ($entry) => ($entry['status'] ?? null) === $status);
    }
}
