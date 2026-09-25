<?php

namespace App\Services;

use App\Models\SalaryAdjustmentApprovalToken;
use App\Models\SalaryAdjustmentRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalaryAdjustmentApprovalService
{
    public function findActiveToken(string $token): ?SalaryAdjustmentApprovalToken
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return SalaryAdjustmentApprovalToken::where('token', $token)
            ->where('is_active', true)
            ->first();
    }

    public function findToken(string $token): ?SalaryAdjustmentApprovalToken
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return SalaryAdjustmentApprovalToken::where('token', $token)->first();
    }

    public function overview(string $token): array
    {
        $approvalToken = $this->findToken($token);
        if (!$approvalToken) {
            return ['result' => 'invalid', 'message' => 'Link persetujuan tidak valid atau sudah kadaluarsa.'];
        }

        if ($approvalToken->decision) {
            return $this->presentProcessedState($approvalToken);
        }

        if (!$approvalToken->is_active) {
            return ['result' => 'invalid', 'message' => 'Link persetujuan tidak valid atau sudah kadaluarsa.'];
        }

        $record = SalaryAdjustmentRequest::find($approvalToken->request_id);
        if (!$record || !$record->is_active) {
            return ['result' => 'invalid', 'message' => 'Permohonan tidak ditemukan.'];
        }

        $expectedStatus = $approvalToken->approver_role === SalaryAdjustmentEmailService::ROLE_IBU
            ? SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_IBU
            : SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK;

        if ($record->status !== $expectedStatus) {
            return [
                'result' => 'unavailable',
                'message' => 'Link persetujuan sudah tidak berlaku untuk tahap ini.',
            ];
        }

        return [
            'result' => 'ready',
            'approver_role' => $approvalToken->approver_role,
        ];
    }

    public function decide(string $token, string $decision, ?string $rejectReason = null): array
    {
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new \InvalidArgumentException('Keputusan tidak valid.');
        }

        if ($decision === 'reject' && trim((string) $rejectReason) === '') {
            throw new \InvalidArgumentException('Alasan penolakan wajib diisi.');
        }

        return DB::connection('mysql')->transaction(function () use ($token, $decision, $rejectReason) {
            $approvalToken = SalaryAdjustmentApprovalToken::where('token', $token)
                ->lockForUpdate()
                ->first();

            if (!$approvalToken) {
                return ['result' => 'invalid', 'message' => 'Link persetujuan tidak valid atau sudah kadaluarsa.'];
            }

            if ($approvalToken->decision) {
                return $this->presentProcessedState($approvalToken);
            }

            if (!$approvalToken->is_active) {
                return ['result' => 'invalid', 'message' => 'Link persetujuan tidak valid atau sudah kadaluarsa.'];
            }

            $record = SalaryAdjustmentRequest::where('id', $approvalToken->request_id)
                ->lockForUpdate()
                ->first();

            if (!$record || !$record->is_active) {
                return ['result' => 'invalid', 'message' => 'Permohonan tidak ditemukan.'];
            }

            $role = $approvalToken->approver_role;
            $expectedStatus = $role === SalaryAdjustmentEmailService::ROLE_IBU
                ? SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_IBU
                : SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK;

            if ($record->status !== $expectedStatus) {
                return [
                    'result' => 'unavailable',
                    'message' => 'Link persetujuan sudah tidak berlaku.',
                ];
            }

            $now = Carbon::now();
            $from = $record->status;
            $actorLabel = SalaryAdjustmentEmailService::actorLabel($role);

            if ($decision === 'reject') {
                $record->status = SalaryAdjustmentWorkflowService::STATUS_REJECTED;
                $record->rejected_stage = $expectedStatus;
                $record->reject_reason = trim((string) $rejectReason);
                $record->rejected_by = $actorLabel;
                $record->rejected_at = $now;

                if ($role === SalaryAdjustmentEmailService::ROLE_IBU) {
                    $record->ibu_rejected_by = $actorLabel;
                    $record->ibu_rejected_at = $now;
                } else {
                    $record->bapak_rejected_by = $actorLabel;
                    $record->bapak_rejected_at = $now;
                }

                SalaryAdjustmentLogService::log(
                    $record->id,
                    $from,
                    $record->status,
                    $role . '_reject',
                    null,
                    $actorLabel,
                    $record->reject_reason
                );
            } elseif ($role === SalaryAdjustmentEmailService::ROLE_IBU) {
                $record->status = SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK;
                $record->ibu_approved_by = $actorLabel;
                $record->ibu_approved_at = $now;

                SalaryAdjustmentLogService::log(
                    $record->id,
                    $from,
                    $record->status,
                    'ibu_approve',
                    null,
                    $actorLabel,
                    'Waiting Approval disetujui'
                );
            } else {
                $applyOrchestrator = new EmployeeAdjustmentApplyOrchestrator();
                $completionNotes = 'Waiting Approval Final disetujui';

                $record->status = SalaryAdjustmentWorkflowService::STATUS_COMPLETED;
                $record->bapak_approved_by = $actorLabel;
                $record->bapak_approved_at = $now;

                $applyNote = $applyOrchestrator->scheduleOrApplyAfterBapakApproval($record, $actorLabel);
                $completionNotes .= ' — ' . $applyNote;

                SalaryAdjustmentLogService::log(
                    $record->id,
                    $from,
                    $record->status,
                    'bapak_approve',
                    null,
                    $actorLabel,
                    $completionNotes
                );
            }

            $record->updated_by = $actorLabel;
            $record->save();

            $approvalToken->is_active = false;
            $approvalToken->decision = $decision;
            $approvalToken->reject_reason = $decision === 'reject' ? trim((string) $rejectReason) : null;
            $approvalToken->decided_at = $now;
            $approvalToken->save();

            if ($decision === 'approve' && $role === SalaryAdjustmentEmailService::ROLE_IBU) {
                (new SalaryAdjustmentEmailService())->sendForRole($record->fresh(), SalaryAdjustmentEmailService::ROLE_BAPAK, 'System');
            }

            return $this->presentDecisionResponse(
                $approvalToken,
                $decision,
                false,
                $now->toDateTimeString()
            );
        });
    }

    private function presentProcessedState(SalaryAdjustmentApprovalToken $approvalToken): array
    {
        return $this->presentDecisionResponse(
            $approvalToken,
            (string) $approvalToken->decision,
            true,
            optional($approvalToken->decided_at)->toDateTimeString()
        );
    }

    private function presentDecisionResponse(
        SalaryAdjustmentApprovalToken $approvalToken,
        string $decision,
        bool $alreadyProcessed,
        ?string $decidedAt = null
    ): array {
        $payload = [
            'result' => $decision,
            'already_processed' => $alreadyProcessed,
            'approver_role' => $approvalToken->approver_role,
            'decided_at' => $decidedAt,
        ];

        if ($decision === 'reject') {
            $payload['reject_reason'] = $approvalToken->reject_reason;
        }

        if (
            $decision === 'approve'
            && $approvalToken->approver_role === SalaryAdjustmentEmailService::ROLE_BAPAK
        ) {
            $payload['is_completed'] = true;
        }

        return $payload;
    }

    private function presentRequest(SalaryAdjustmentRequest $record): array
    {
        $employee = \App\Models\MasterKaryawan::find($record->employee_id);

        return [
            'no_document' => $record->no_document,
            'nama_lengkap' => $employee->nama_lengkap ?? '-',
            'jabatan' => $record->jabatan,
            'adjustment_gaji_pokok' => (float) ($record->adjustment_gaji_pokok ?? 0),
            'adjustment_tunjangan' => (float) ($record->adjustment_tunjangan ?? 0),
            'requested_gaji_pokok' => (float) $record->requested_gaji_pokok,
            'requested_tunjangan_kerja' => (float) $record->requested_tunjangan_kerja,
            'bulan_efektif' => $record->bulan_efektif,
            'status' => $record->status,
            'status_label' => SalaryAdjustmentWorkflowService::statusLabel($record->status),
        ];
    }
}
