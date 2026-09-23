<?php

namespace App\Services;

class RecruitmentDecisionActionEmailService
{
    public function notifyFinalDecision($recruitment, string $decision): void
    {
        $this->send(
            $recruitment,
            'final_decision',
            $decision,
            trim((string) env('EMAIL_DIREKTUR_IBU', '')),
            array_filter([trim((string) env('EMAIL_DIREKTUR_BAPAK', ''))])
        );
    }

    public function notifySalaryDecision($recruitment, string $decision): void
    {
        $this->send(
            $recruitment,
            'salary_decision',
            $decision,
            trim((string) env('EMAIL_DIREKTUR_BAPAK', ''))
        );
    }

    private function send($recruitment, string $flow, string $decision, string $to, array $bcc = []): void
    {
        if ($to === '') {
            \Log::warning('Decision action confirmation email skipped: recipient is not configured.', [
                'flow' => $flow,
                'recruitment_id' => $recruitment->id ?? null,
            ]);
            return;
        }

        $bcc = array_values(array_filter(array_unique(array_map('trim', $bcc)), function (string $email) use ($to): bool {
            return $email !== '' && strcasecmp($email, $to) !== 0;
        }));

        try {
            $title = GenerateMessageAtsEmail::decisionActionTitle($flow, $decision);
            SendEmail::where('to', $to)
                ->where('bcc', $bcc)
                ->where('subject', $title . ' - ' . ($recruitment->nama_lengkap ?? 'Kandidat'))
                ->where('body', GenerateMessageAtsEmail::bodyEmailDecisionActionConfirmation($recruitment, $flow, $decision))
                ->where('karyawan', 'Recruitment System')
                ->noReply('PT Inti Surya Laboratorium')
                ->send();
        } catch (\Throwable $e) {
            \Log::warning('Decision action confirmation email failed.', [
                'flow' => $flow,
                'decision' => $decision,
                'recruitment_id' => $recruitment->id ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
