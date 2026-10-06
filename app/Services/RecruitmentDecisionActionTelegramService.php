<?php

namespace App\Services;

use Carbon\Carbon;
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
            \Log::warning('Decision action telegram skipped: recipient is not configured.', [
                'flow' => $flow,
                'recruitment_id' => $recruitment->id ?? null,
            ]);
            return;
        }

        try {
            $message = $buildMessage();
        } catch (\Throwable $e) {
            \Log::warning('Decision action telegram failed to build message.', [
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
            } catch (\Throwable $e) {
                \Log::warning('Decision action telegram failed.', [
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
        $lines = array_merge(
            $this->headerLines(
                GenerateMessageAtsEmail::decisionActionTitle('final_decision', $decision),
                'Pemberitahuan hasil keputusan persetujuan kandidat.'
            ),
            $this->candidateLines($recruitment)
        );

        if ($decision === 'reject') {
            $lines[] = 'Alasan Penolakan : ' . $this->escape($context['reject_reason'] ?? null);
        }

        if ($decision === 'keep') {
            $lines[] = '';
            $lines[] = '<i>Kandidat ditahan, pengingat akan dikirim kembali dalam 7 hari.</i>';
        }

        return implode("\n", array_merge($lines, $this->footerLines()));
    }

    private function buildSalaryDecisionMessage($recruitment, string $decision, array $context): string
    {
        $salaryOffer = DB::table('sallary_offer')
            ->where('new_recruitment_id', $recruitment->id ?? null)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        $lines = array_merge(
            $this->headerLines(
                GenerateMessageAtsEmail::decisionActionTitle('salary_decision', $decision),
                'Pemberitahuan hasil keputusan persetujuan penawaran gaji.'
            ),
            $this->candidateLines($recruitment),
            [
                '',
                'Ekspektasi Gaji : ' . $this->rupiah($recruitment->ekspetasi_gaji ?? null),
                'Penawaran HRD : ' . $this->rupiah($salaryOffer->sallary_offer_hrd ?? null),
            ]
        );

        if ($decision === 'approve') {
            $lines[] = 'Gaji Final : ' . $this->rupiah($salaryOffer->final_sallary ?? $salaryOffer->sallary_offer_hrd ?? null);
            $lines[] = '';
            $lines[] = '<i>Hiring letter dikirim ke kandidat.</i>';
        }

        if ($decision === 'negotiate') {
            $lines[] = 'Nominal Negosiasi : ' . $this->rupiah($context['negotiated_amount'] ?? $salaryOffer->sallary_offer_direktur ?? null);
            $lines[] = '';
            $lines[] = '<i>Penawaran dikembalikan ke HRD untuk dinegosiasikan.</i>';
        }

        if ($decision === 'reject') {
            $lines[] = 'Alasan Penolakan : ' . $this->escape($context['reject_reason'] ?? null);
            $lines[] = '';
            $lines[] = '<i>Penawaran dikembalikan ke HRD.</i>';
        }

        return implode("\n", array_merge($lines, $this->footerLines()));
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

    private function headerLines(string $title, string $subtitle): array
    {
        return [
            '<b>' . $this->escape($title) . '</b>',
            $this->escape($subtitle),
            '',
        ];
    }

    private function positionName($recruitment): ?string
    {
        return DB::table('personnel_requests')
            ->where('id', $recruitment->personnel_request_id ?? null)
            ->value('divisi_alias')
            ?: ($recruitment->posisi_dilamar ?? null);
    }

    private function candidateLines($recruitment): array
    {
        return [
            'Nama Kandidat : ' . $this->escape($recruitment->nama_lengkap ?? null),
            'Posisi : ' . $this->escape($this->positionName($recruitment)),
            'Email : ' . $this->escape($recruitment->email ?? null),
            'No. Telepon : ' . $this->escape($recruitment->no_telepon ?? null),
        ];
    }

    private function footerLines(): array
    {
        return [
            '',
            'Waktu : ' . $this->escape(Carbon::now()->format('d-m-Y H:i')),
            '<i>Recruitment System - PT Inti Surya Laboratorium</i>',
        ];
    }

    private function rupiah($amount): string
    {
        if ($amount === null || $amount === '' || !is_numeric($amount)) {
            return '-';
        }

        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }

    private function escape($value): string
    {
        $value = trim((string) ($value ?? ''));
        return htmlspecialchars($value !== '' ? $value : '-', ENT_QUOTES, 'UTF-8');
    }
}
