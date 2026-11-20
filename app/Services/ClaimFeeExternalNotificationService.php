<?php

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Models\ClaimFeeExternal;
use App\Models\MasterKaryawan;
use App\Support\ClaimFeeExternal\ClaimFeeExternalWorkflow;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClaimFeeExternalNotificationService
{
    public function notifyApprovalPending(ClaimFeeExternal $claim, int $approverKaryawanId): void
    {
        if ($approverKaryawanId <= 0) {
            return;
        }

        $stage = $this->currentStageCode($claim);
        $stageLabel = $stage ? ClaimFeeExternalWorkflow::stageLabel($stage) : 'Atasan';

        $this->push([$approverKaryawanId], [
            'title' => 'Persetujuan Claim Fee External',
            'body' => "Pengajuan No Order {$claim->no_order} menunggu persetujuan Anda ({$stageLabel}).",
            'url' => $this->urlTab('persetujuan', $claim),
            'type' => 'claim_fee_external',
            'claim_id' => (int) $claim->id,
            'no_order' => $claim->no_order,
            'event' => 'CFE_APPROVAL_PENDING',
            'tab' => 'persetujuan',
        ]);
    }

    public function notifySubmitter(string $eventCode, ClaimFeeExternal $claim, array $context = []): void
    {
        $userId = (int) ($claim->sales_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        [$title, $body, $tab] = $this->copyForEvent($eventCode, $claim, $context);

        $this->push([$userId], [
            'title' => $title,
            'body' => $body,
            'url' => $this->urlTab($tab, $claim),
            'type' => 'claim_fee_external',
            'claim_id' => (int) $claim->id,
            'no_order' => $claim->no_order,
            'event' => $eventCode,
            'sales_workflow_status' => $claim->sales_workflow_status,
            'finance_status' => $claim->finance_status,
            'tab' => $tab,
        ]);
    }

    private function copyForEvent(string $eventCode, ClaimFeeExternal $claim, array $context): array
    {
        $order = $claim->no_order ?? '-';

        switch ($eventCode) {
            case 'CFE_SUBMITTED':
                return [
                    'Claim Fee External Terkirim',
                    'Pengajuan No Order ' . $order . ' telah dikirim. ' . ($context['next_text'] ?? 'Menunggu persetujuan atasan.'),
                    'pengajuan',
                ];
            case 'CFE_STAGE_APPROVED':
                return [
                    'Claim Disetujui (' . ($context['stage_label'] ?? 'Atasan') . ')',
                    'Tahap ' . ($context['stage_label'] ?? '') . ' disetujui. ' . ($context['next_text'] ?? ''),
                    'menunggu-finance',
                ];
            case 'CFE_SALES_APPROVED':
                return [
                    'Disetujui Sales',
                    "Pengajuan No Order {$order} telah disetujui seluruh rantai sales. Menunggu proses Finance.",
                    'menunggu-finance',
                ];
            case 'CFE_SALES_REJECTED':
                return [
                    'Claim Ditolak (Sales)',
                    'Pengajuan ditolak. ' . (!empty($context['remarks']) ? 'Alasan: ' . Str::limit($context['remarks'], 200) : ''),
                    'pengajuan',
                ];
            case 'CFE_FINANCE_WAITING_TAX':
                return [
                    'Masuk Proses Pajak',
                    "Claim No Order {$order} menunggu verifikasi pajak.",
                    'menunggu-finance',
                ];
            case 'CFE_FINANCE_TAX_IN_PROGRESS':
                return [
                    'Diproses Pajak',
                    "Claim No Order {$order} sedang diproses tim pajak.",
                    'menunggu-finance',
                ];
            case 'CFE_FINANCE_READY_TO_PAY':
                return [
                    'Siap Bayar',
                    "Claim No Order {$order} siap proses pembayaran.",
                    'menunggu-finance',
                ];
            case 'CFE_FINANCE_PAID':
                return [
                    'Sudah Ditransfer',
                    "Pembayaran claim No Order {$order} telah ditransfer.",
                    'menunggu-finance',
                ];
            case 'CFE_FINANCE_REJECTED':
                return [
                    'Ditolak Finance',
                    'Claim ditolak oleh Finance. ' . (!empty($context['remarks']) ? 'Alasan: ' . Str::limit($context['remarks'], 200) : 'Ajukan claim baru jika diperlukan.'),
                    'pengajuan',
                ];
            default:
                return [
                    'Claim Fee External',
                    "Ada pembaruan pada pengajuan No Order {$order}.",
                    'pengajuan',
                ];
        }
    }

  private function currentStageCode(ClaimFeeExternal $claim): ?string
    {
        $stages = $this->stagesArray($claim);
        $index = (int) ($claim->current_stage_index ?? 0);

        return $stages[$index] ?? null;
    }

    /** @return list<string> */
    private function stagesArray(ClaimFeeExternal $claim): array
    {
        $raw = $claim->required_sales_stages;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        return is_array($raw) ? array_values($raw) : [];
    }

    private function urlTab(string $tab, ClaimFeeExternal $claim): string
    {
        return '/sales/claim-fee-external?tab=' . $tab . '&detail=' . (int) $claim->id;
    }

    private function push(array $userIds, array $data): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return;
        }

        try {
            $users = MasterKaryawan::whereIn('id', $userIds)
                ->where('is_active', 1)
                ->get(['id']);

            if ($users->isEmpty()) {
                return;
            }

            dispatch(new SendNotificationJob([
                'data' => [
                    'title' => (string) ($data['title'] ?? 'Claim Fee External'),
                    'message' => (string) ($data['body'] ?? ''),
                    'url' => (string) ($data['url'] ?? '/sales/claim-fee-external'),
                ],
                'users' => $users,
            ]));
        } catch (\Throwable $e) {
            Log::warning('ClaimFeeExternal notification failed', [
                'users' => $userIds,
                'event' => $data['event'] ?? null,
                'message' => $e->getMessage(),
            ]);
        }
    }
}