<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RecruitmentDecisionActionTelegramService
{
    /**
     * $context: reject_reason
     */
    public function notifyFinalDecision($recruitment, string $decision, array $context = []): void
    {
        $this->send(
            $recruitment,
            'final_decision',
            $decision,
            [env('TELEGRAM_DIREKTUR_IBU'), env('TELEGRAM_DIREKTUR_BAPAK')],
            function () use ($recruitment, $decision, $context) {
                return $this->buildFinalDecisionMessage($recruitment, $decision, $context);
            }
        );
    }

    /**
     * $context: reject_reason, negotiated_amount
     */
    public function notifySalaryDecision($recruitment, string $decision, array $context = []): void
    {
        $this->send(
            $recruitment,
            'salary_decision',
            $decision,
            [env('TELEGRAM_DIREKTUR_BAPAK')],
            function () use ($recruitment, $decision, $context) {
                return $this->buildSalaryDecisionMessage($recruitment, $decision, $context);
            }
        );
    }

    /**
     * $context: approved_by, sallary_offer_user
     */
    public function notifyCandidateApprovalRequest($recruitment, array $context = []): void
    {
        $this->send(
            $recruitment,
            'candidate_approval_request',
            'request',
            [env('TELEGRAM_DIREKTUR_IBU')],
            function () use ($recruitment, $context) {
                return $this->buildCandidateApprovalRequestMessage($recruitment, $context);
            }
        );
    }

    /**
     * $context: approved_by
     */
    public function notifySalaryApprovalRequest($recruitment, array $context = []): void
    {
        $this->send(
            $recruitment,
            'salary_approval_request',
            'request',
            [env('TELEGRAM_DIREKTUR_BAPAK')],
            function () use ($recruitment, $context) {
                return $this->buildSalaryApprovalRequestMessage($recruitment, $context);
            }
        );
    }

    private function send($recruitment, string $flow, string $decision, array $chatIds, callable $buildMessage): void
    {
        $chatIds = array_values(array_unique(array_filter(array_map(function ($id) {
            return trim((string) $id);
        }, $chatIds))));

        if (empty($chatIds)) {
            \Log::channel('telegram')->warning('Decision action telegram skipped: recipient is not configured.', [
                'flow' => $flow,
                'recruitment_id' => $recruitment->id ?? null,
            ]);
            return;
        }

        try {
            $message = $buildMessage();
        } catch (\Throwable $e) {
            \Log::channel('telegram')->warning('Decision action telegram failed to build message.', [
                'flow' => $flow,
                'decision' => $decision,
                'recruitment_id' => $recruitment->id ?? null,
                'message' => $e->getMessage(),
            ]);
            return;
        }

        foreach ($chatIds as $chatId) {
            try {
                // SendTelegram keeps a static instance, so reset button to avoid leaking state from a previous send.
                SendTelegram::text($message)->button([])->to($chatId)->send();

                \Log::channel('telegram')->info('Decision action telegram sent.', [
                    'flow' => $flow,
                    'decision' => $decision,
                    'chat_id' => $chatId,
                    'recruitment_id' => $recruitment->id ?? null,
                    'text' => $message,
                ]);
            } catch (\Throwable $e) {
                \Log::channel('telegram')->warning('Decision action telegram failed.', [
                    'flow' => $flow,
                    'decision' => $decision,
                    'chat_id' => $chatId,
                    'recruitment_id' => $recruitment->id ?? null,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildFinalDecisionMessage($recruitment, string $decision, array $context): string
    {
        $results = [
            'approve' => 'disetujui',
            'reject' => 'ditolak',
            'keep' => 'di-keep',
        ];

        $lines = [
            'Yth. Bapak/Ibu,',
            'Hasil persetujuan kandidat'
                . ' <b>' . $this->escape($recruitment->nama_lengkap ?? null) . '</b>'
                . ' untuk posisi <b>' . $this->escape($this->positionName($recruitment)) . '</b>'
                . ' telah berhasil <b>' . ($results[$decision] ?? 'Diproses') . '</b>. ',
        ];

        $lines[] = 'Rincian keputusan telah dikirimkan melalui email Bapak/Ibu.';
        $lines[] = 'Terima kasih atas waktu dan perhatian Bapak/Ibu.';

        return implode("\n", $lines);
    }

    private function buildSalaryDecisionMessage($recruitment, string $decision, array $context): string
    {
        $results = [
            'approve' => 'disetujui',
            'reject' => 'ditolak',
            'negotiate' => 'dinegosiasikan',
        ];

        return implode("\n", [
            'Yth. Bapak/Ibu,',
            'Hasil persetujuan offering salary kandidat'
                . ' <b>' . $this->escape($recruitment->nama_lengkap ?? null) . '</b>'
                . ' untuk posisi <b>' . $this->escape($this->positionName($recruitment)) . '</b>'
                . ' telah berhasil <b>' . ($results[$decision] ?? 'diproses') . '.</b>' .
            'Rincian keputusan telah dikirimkan melalui email Bapak/Ibu.',
            'Terima kasih atas waktu dan perhatian Bapak/Ibu.',
        ]);
    }

    private function buildCandidateApprovalRequestMessage($recruitment, array $context): string
    {
        return $this->buildApprovalRequestMessage($recruitment, 'persetujuan kandidat', 'detail kandidat');
    }

    private function buildSalaryApprovalRequestMessage($recruitment, array $context): string
    {
        return $this->buildApprovalRequestMessage($recruitment, 'persetujuan offering salary kandidat', 'detail penawaran');
    }

    private function buildApprovalRequestMessage($recruitment, string $subject, string $detail): string
    {
        return implode("\n", [
            'Yth. Bapak/Ibu,',
            'Pengajuan ' . $subject
                . ' <b>' . $this->escape($recruitment->nama_lengkap ?? null) . '</b>'
                . ' untuk posisi <b>' . $this->escape($this->positionName($recruitment)) . '</b>'
                . ' telah dikirimkan melalui email.',
            "Mohon kesediaan Bapak/Ibu untuk memeriksa email tersebut guna meninjau {$detail} dan memberikan keputusan.",
            'Terima kasih atas waktu dan perhatian Bapak/Ibu.',
        ]);
    }

    private function positionName($recruitment): ?string
    {
        return DB::table('personnel_requests')
            ->where('id', $recruitment->personnel_request_id ?? null)
            ->value('divisi_alias')
            ?: ($recruitment->posisi_dilamar ?? null);
    }

    private function escape($value): string
    {
        $value = trim((string) ($value ?? ''));
        return htmlspecialchars($value !== '' ? $value : '-', ENT_QUOTES, 'UTF-8');
    }
}
