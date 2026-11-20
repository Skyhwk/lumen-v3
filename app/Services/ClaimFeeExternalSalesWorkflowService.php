<?php

namespace App\Services;

use App\Models\ClaimFeeExternal;
use App\Models\ClaimFeeExternalApprovalLog;
use App\Models\MasterKaryawan;
use App\Support\ClaimFeeExternal\ClaimFeeExternalWorkflow;
use App\Services\Greatday\DynamicApprovalChainService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class ClaimFeeExternalSalesWorkflowService
{
    public function workflowEnabled(): bool
    {
        return Schema::hasColumn('claim_fee_external', 'sales_workflow_status');
    }

    /** @return list<string> */
    public function buildRequiredStages(MasterKaryawan $seller): array
    {
        $ids = app(DynamicApprovalChainService::class)->orderedApproverIds($seller);
        $stages = [];
        $seen = [];

        foreach ($ids as $karyawanId) {
            $grade = MasterKaryawan::query()
                ->where('id', (int) $karyawanId)
                ->where('is_active', true)
                ->value('grade');

            $stage = ClaimFeeExternalWorkflow::gradeToStage((string) $grade);
            if ($stage === null || isset($seen[$stage])) {
                continue;
            }
            $seen[$stage] = true;
            $stages[] = $stage;
        }

        return $stages;
    }

    public function resolveApproverForStage(ClaimFeeExternal $claim, string $stage): ?int
    {
        $seller = MasterKaryawan::query()
            ->where('id', (int) $claim->sales_id)
            ->where('is_active', true)
            ->first();

        if (!$seller) {
            return null;
        }

        foreach (app(DynamicApprovalChainService::class)->orderedApproverIds($seller) as $karyawanId) {
            $grade = MasterKaryawan::query()->where('id', (int) $karyawanId)->value('grade');
            if (ClaimFeeExternalWorkflow::gradeToStage((string) $grade) === $stage) {
                return (int) $karyawanId;
            }
        }

        return null;
    }

    public function currentStageCode(ClaimFeeExternal $claim): ?string
    {
        $stages = $this->stagesArray($claim);
        $index = (int) ($claim->current_stage_index ?? 0);

        return $stages[$index] ?? null;
    }

    public function initializeWorkflow(ClaimFeeExternal $claim): void
    {
        if (!$this->workflowEnabled()) {
            return;
        }

        $seller = MasterKaryawan::query()->find((int) $claim->sales_id);
        if (!$seller) {
            return;
        }

        $stages = $this->buildRequiredStages($seller);
        $claim->required_sales_stages = $stages;
        $claim->current_stage_index = 0;
        $claim->submitted_at = Carbon::now()->format('Y-m-d H:i:s');

        if ($stages === []) {
            $claim->sales_workflow_status = ClaimFeeExternalWorkflow::SALES_APPROVED;
            $claim->sales_approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $claim->finance_status = ClaimFeeExternalWorkflow::FINANCE_WAITING_TAX;
            $claim->finance_locked_at = $claim->sales_approved_at;
        } else {
            $claim->sales_workflow_status = ClaimFeeExternalWorkflow::pendingStatusForStage($stages[0]);
        }

        app(ClaimFeeExternalStatusSyncService::class)->sync($claim);
        $this->syncPendingApprover($claim);
    }

    public function syncPendingApprover(ClaimFeeExternal $claim): void
    {
        if (!$this->workflowEnabled() || !Schema::hasColumn('claim_fee_external', 'pending_approver_karyawan_id')) {
            return;
        }

        $pending = [
            ClaimFeeExternalWorkflow::SALES_PENDING_SPV,
            ClaimFeeExternalWorkflow::SALES_PENDING_MANAGER,
            ClaimFeeExternalWorkflow::SALES_PENDING_SENIOR_MANAGER,
        ];

        if (!in_array($claim->sales_workflow_status ?? '', $pending, true)) {
            $claim->pending_approver_karyawan_id = null;

            return;
        }

        $stage = $this->currentStageCode($claim);
        $claim->pending_approver_karyawan_id = $stage
            ? $this->resolveApproverForStage($claim, $stage)
            : null;
    }

    public function canEdit(ClaimFeeExternal $claim): bool
    {
        if (!$this->workflowEnabled()) {
            return true;
        }

        if (in_array($claim->finance_status, ClaimFeeExternalWorkflow::financeLockedStatuses(), true)) {
            return false;
        }

        if (($claim->sales_workflow_status ?? '') === ClaimFeeExternalWorkflow::SALES_REJECTED) {
            return true;
        }

        $stages = $this->stagesArray($claim);
        if ($stages === []) {
            return ($claim->sales_workflow_status ?? '') !== ClaimFeeExternalWorkflow::SALES_APPROVED;
        }

        $firstStage = $stages[0];

        $hasFirstApproval = ClaimFeeExternalApprovalLog::query()
            ->where('claim_fee_external_id', $claim->id)
            ->where('stage', $firstStage)
            ->where('action', ClaimFeeExternalApprovalLog::ACTION_APPROVED)
            ->exists();

        return !$hasFirstApproval;
    }

    public function canApprove(ClaimFeeExternal $claim, int $viewerKaryawanId): bool
    {
        if (!$this->workflowEnabled()) {
            return false;
        }

        $pending = [
            ClaimFeeExternalWorkflow::SALES_PENDING_SPV,
            ClaimFeeExternalWorkflow::SALES_PENDING_MANAGER,
            ClaimFeeExternalWorkflow::SALES_PENDING_SENIOR_MANAGER,
        ];

        if (!in_array($claim->sales_workflow_status ?? '', $pending, true)) {
            return false;
        }

        $stage = $this->currentStageCode($claim);
        if ($stage === null) {
            return false;
        }

        $expected = $this->resolveApproverForStage($claim, $stage);

        return $expected !== null && $expected === $viewerKaryawanId;
    }

    public function approveSales(ClaimFeeExternal $claim, MasterKaryawan $approver): ClaimFeeExternal
    {
        if (!$this->canApprove($claim, (int) $approver->id)) {
            throw new \RuntimeException('Anda bukan verifikator pada tahap ini.');
        }

        $stage = $this->currentStageCode($claim);
        $expected = $this->resolveApproverForStage($claim, (string) $stage);
        $now = Carbon::now()->format('Y-m-d H:i:s');

        ClaimFeeExternalApprovalLog::query()->create([
            'claim_fee_external_id' => $claim->id,
            'stage' => $stage,
            'action' => ClaimFeeExternalApprovalLog::ACTION_APPROVED,
            'actor_karyawan_id' => (int) $approver->id,
            'expected_karyawan_id' => $expected,
            'remarks' => null,
            'created_at' => $now,
        ]);

        $stages = $this->stagesArray($claim);
        $nextIndex = (int) $claim->current_stage_index + 1;

        if ($nextIndex >= count($stages)) {
            $claim->sales_workflow_status = ClaimFeeExternalWorkflow::SALES_APPROVED;
            $claim->sales_approved_at = $now;
            $claim->finance_status = ClaimFeeExternalWorkflow::FINANCE_WAITING_TAX;
            $claim->finance_locked_at = $now;
            $claim->current_stage_index = $nextIndex;
        } else {
            $claim->current_stage_index = $nextIndex;
            $claim->sales_workflow_status = ClaimFeeExternalWorkflow::pendingStatusForStage($stages[$nextIndex]);
        }

        app(ClaimFeeExternalStatusSyncService::class)->sync($claim);
        $this->syncPendingApprover($claim);
        $claim->save();

        $notif = app(ClaimFeeExternalNotificationService::class);
        $stageLabel = ClaimFeeExternalWorkflow::stageLabel((string) $stage);

        if ($claim->sales_workflow_status === ClaimFeeExternalWorkflow::SALES_APPROVED) {
            $notif->notifySubmitter('CFE_SALES_APPROVED', $claim);
            $notif->notifySubmitter('CFE_FINANCE_WAITING_TAX', $claim);
        } else {
            $nextStage = $this->currentStageCode($claim);
            $nextText = $nextStage
                ? 'Menunggu persetujuan ' . ClaimFeeExternalWorkflow::stageLabel($nextStage) . '.'
                : '';
            $notif->notifySubmitter('CFE_STAGE_APPROVED', $claim, [
                'stage_label' => $stageLabel,
                'next_text' => $nextText,
            ]);
            $nextApprover = $this->resolveApproverForStage($claim, (string) $nextStage);
            if ($nextApprover) {
                $notif->notifyApprovalPending($claim, $nextApprover);
            }
        }

        return $claim;
    }

    public function rejectSales(ClaimFeeExternal $claim, MasterKaryawan $approver, string $remarks): ClaimFeeExternal
    {
        if (!$this->canApprove($claim, (int) $approver->id)) {
            throw new \RuntimeException('Anda bukan verifikator pada tahap ini.');
        }

        $remarks = trim($remarks);
        if (strlen($remarks) < 10) {
            throw new \InvalidArgumentException('Alasan penolakan minimal 10 karakter.');
        }

        $stage = $this->currentStageCode($claim);
        $expected = $this->resolveApproverForStage($claim, (string) $stage);
        $now = Carbon::now()->format('Y-m-d H:i:s');

        ClaimFeeExternalApprovalLog::query()->create([
            'claim_fee_external_id' => $claim->id,
            'stage' => $stage,
            'action' => ClaimFeeExternalApprovalLog::ACTION_REJECTED,
            'actor_karyawan_id' => (int) $approver->id,
            'expected_karyawan_id' => $expected,
            'remarks' => $remarks,
            'created_at' => $now,
        ]);

        $claim->sales_workflow_status = ClaimFeeExternalWorkflow::SALES_REJECTED;
        $claim->rejected_by = $approver->nama_lengkap ?? (string) $approver->id;
        $claim->rejected_at = $now;

        app(ClaimFeeExternalStatusSyncService::class)->sync($claim);
        $this->syncPendingApprover($claim);
        $claim->save();

        app(ClaimFeeExternalNotificationService::class)->notifySubmitter('CFE_SALES_REJECTED', $claim, [
            'remarks' => $remarks,
        ]);

        return $claim;
    }

    /**
     * @param  bool  $skipSubmitterNotif  true pada pengajuan baru (create) — pengaju sudah dapat toast di portal
     */
    public function afterSubmit(ClaimFeeExternal $claim, bool $skipSubmitterNotif = false): void
    {
        if (!$this->workflowEnabled()) {
            return;
        }

        $notif = app(ClaimFeeExternalNotificationService::class);
        $stage = $this->currentStageCode($claim);

        if ($claim->sales_workflow_status === ClaimFeeExternalWorkflow::SALES_APPROVED) {
            if (!$skipSubmitterNotif) {
                $notif->notifySubmitter('CFE_SUBMITTED', $claim, ['next_text' => 'Tidak ada tahap persetujuan sales.']);
                $notif->notifySubmitter('CFE_SALES_APPROVED', $claim);
                $notif->notifySubmitter('CFE_FINANCE_WAITING_TAX', $claim);
            }

            return;
        }

        if (!$skipSubmitterNotif) {
            $nextText = $stage
                ? 'Menunggu persetujuan ' . ClaimFeeExternalWorkflow::stageLabel($stage) . '.'
                : '';
            $notif->notifySubmitter('CFE_SUBMITTED', $claim, ['next_text' => $nextText]);
        }

        $approverId = $stage ? $this->resolveApproverForStage($claim, $stage) : null;
        if ($approverId) {
            $notif->notifyApprovalPending($claim, $approverId);
        }
    }

    public function denySupervisorSubmit(?string $grade): bool
    {
        $g = strtoupper(str_replace('_', ' ', trim((string) $grade)));
        if ($g === 'SPV') {
            $g = 'SUPERVISOR';
        }

        return $g === 'SUPERVISOR';
    }

    public function assertFinanceEditAllowed(ClaimFeeExternal $claim): void
    {
        if (!$this->workflowEnabled()) {
            return;
        }

        if (in_array($claim->finance_status, ClaimFeeExternalWorkflow::financeLockedStatuses(), true)) {
            throw new \RuntimeException('Claim tidak dapat diubah. Buat pengajuan baru jika ditolak finance.');
        }
    }

    public function buildPipeline(ClaimFeeExternal $claim): array
    {
        $pipeline = [];
        $stages = $this->stagesArray($claim);

        $pipeline[] = [
            'key' => 'SUBMITTED',
            'label' => 'Diajukan',
            'state' => 'done',
            'acted_at' => $claim->submitted_at ?? $claim->created_at,
            'actor_name' => $claim->created_by ?: null,
            'phase_text' => 'Pengajuan sales dikirim',
        ];

        foreach ($stages as $stage) {
            $log = ClaimFeeExternalApprovalLog::query()
                ->where('claim_fee_external_id', $claim->id)
                ->where('stage', $stage)
                ->orderByDesc('id')
                ->first();

            $expected = $this->resolveApproverForStage($claim, $stage);
            $expectedName = $expected
                ? (MasterKaryawan::query()->where('id', $expected)->value('nama_lengkap') ?? '-')
                : '-';

            if ($log) {
                $state = $log->action === ClaimFeeExternalApprovalLog::ACTION_REJECTED ? 'rejected' : 'done';
                $actorName = MasterKaryawan::query()->where('id', $log->actor_karyawan_id)->value('nama_lengkap');
            } else {
                $state = ($this->currentStageCode($claim) === $stage
                    && in_array($claim->sales_workflow_status ?? '', [
                        ClaimFeeExternalWorkflow::SALES_PENDING_SPV,
                        ClaimFeeExternalWorkflow::SALES_PENDING_MANAGER,
                        ClaimFeeExternalWorkflow::SALES_PENDING_SENIOR_MANAGER,
                    ], true))
                    ? 'pending'
                    : 'waiting';
                $actorName = null;
            }

            $stageLabel = ClaimFeeExternalWorkflow::stageLabel($stage);
            if ($log && $log->action === ClaimFeeExternalApprovalLog::ACTION_REJECTED) {
                $label = 'Ditolak ' . $stageLabel;
            } elseif ($log && $log->action === ClaimFeeExternalApprovalLog::ACTION_APPROVED) {
                $label = 'Disetujui ' . $stageLabel;
            } elseif ($state === 'pending') {
                $label = 'Menunggu ' . $stageLabel;
            } else {
                $label = 'Persetujuan ' . $stageLabel;
            }

            $pipeline[] = [
                'key' => $stage,
                'label' => $label,
                'state' => $state,
                'actor_name' => $actorName,
                'acted_at' => $log->created_at ?? null,
                'expected_approver_name' => $state === 'pending' ? $expectedName : null,
                'phase_text' => $state === 'pending' ? 'Menunggu persetujuan atasan' : null,
            ];
        }

        $salesApproved = ($claim->sales_workflow_status ?? '') === ClaimFeeExternalWorkflow::SALES_APPROVED
            || in_array($claim->finance_status, ClaimFeeExternalWorkflow::financeLockedStatuses(), true);

        $lastSalesActor = null;
        if ($salesApproved) {
            $lastLog = ClaimFeeExternalApprovalLog::query()
                ->where('claim_fee_external_id', $claim->id)
                ->where('action', ClaimFeeExternalApprovalLog::ACTION_APPROVED)
                ->orderByDesc('id')
                ->first();
            if ($lastLog) {
                $lastSalesActor = MasterKaryawan::query()
                    ->where('id', $lastLog->actor_karyawan_id)
                    ->value('nama_lengkap');
            } elseif ($stages === []) {
                $lastSalesActor = 'Tanpa rantai atasan (otomatis)';
            }
        }

        $pipeline[] = [
            'key' => 'APPROVED_SALES',
            'label' => 'Disetujui Sales',
            'state' => $salesApproved ? 'done' : 'waiting',
            'acted_at' => $claim->sales_approved_at,
            'actor_name' => $lastSalesActor,
            'phase_text' => $salesApproved ? 'Siap masuk proses finance' : null,
        ];

        return $this->appendFinancePipeline($pipeline, $claim);
    }

    /** @param list<array<string, mixed>> $pipeline */
    private function appendFinancePipeline(array $pipeline, ClaimFeeExternal $claim): array
    {
        if (!$this->workflowEnabled()) {
            return $pipeline;
        }

        $fs = (string) ($claim->finance_status ?? '');
        $salesApproved = ($claim->sales_workflow_status ?? '') === ClaimFeeExternalWorkflow::SALES_APPROVED
            || in_array($fs, ClaimFeeExternalWorkflow::financeLockedStatuses(), true);

        if (!$salesApproved && $fs === '') {
            return $pipeline;
        }

        if ($fs === ClaimFeeExternalWorkflow::FINANCE_REJECTED) {
            $pipeline[] = [
                'key' => 'FINANCE_REJECTED',
                'label' => 'Ditolak Finance',
                'state' => 'rejected',
                'actor_name' => $claim->rejected_by ?: null,
                'acted_at' => $claim->rejected_at ?? null,
                'phase_text' => 'Pengajuan dihentikan di finance',
            ];

            return $pipeline;
        }

        $pipeline[] = $this->buildFinanceTaxPipelineStep($claim, $fs, $salesApproved);
        $pipeline[] = $this->buildFinancePaymentPipelineStep($claim, $fs);

        return $pipeline;
    }

    /** @return array<string, mixed> */
    private function buildFinanceTaxPipelineStep(ClaimFeeExternal $claim, string $fs, bool $salesApproved): array
    {
        $state = 'waiting';
        $phase = 'Belum masuk antrean pajak';
        $actor = null;
        $at = null;

        $taxDone = in_array($fs, [
            ClaimFeeExternalWorkflow::FINANCE_READY_TO_PAY,
            ClaimFeeExternalWorkflow::FINANCE_PAID,
        ], true);

        if ($taxDone) {
            $state = 'done';
            $phase = 'Pajak selesai diverifikasi';
            $actor = $claim->processed_by ?? null;
            $at = $claim->processed_at ?? null;
        } elseif ($fs === ClaimFeeExternalWorkflow::FINANCE_TAX_IN_PROGRESS) {
            $state = 'pending';
            $phase = 'Sedang diproses pajak';
            $actor = $claim->processed_by ?? null;
            $at = $claim->processed_at ?? null;
        } elseif ($fs === ClaimFeeExternalWorkflow::FINANCE_WAITING_TAX
            || ($salesApproved && $fs === '')) {
            $state = 'pending';
            $phase = 'Menunggu tim pajak';
        } elseif ($salesApproved) {
            $state = 'waiting';
        }

        return [
            'key' => 'FINANCE_TAX',
            'label' => 'Verifikasi Pajak',
            'state' => $state,
            'phase_text' => $phase,
            'actor_name' => $actor,
            'acted_at' => $at,
        ];
    }

    /** @return array<string, mixed> */
    private function buildFinancePaymentPipelineStep(ClaimFeeExternal $claim, string $fs): array
    {
        $state = 'waiting';
        $phase = 'Menunggu penyelesaian pajak';
        $actor = null;
        $at = null;

        if ($fs === ClaimFeeExternalWorkflow::FINANCE_PAID) {
            $state = 'done';
            $phase = 'Pembayaran selesai';
            $actor = $claim->transferred_by ?? $claim->processed_by ?? null;
            $at = $claim->tanggal_pembayaran ?? null;
        } elseif ($fs === ClaimFeeExternalWorkflow::FINANCE_READY_TO_PAY) {
            $state = 'pending';
            $phase = 'Siap proses pembayaran / transfer';
            $actor = $claim->processed_by ?? null;
        } elseif (in_array($fs, [
            ClaimFeeExternalWorkflow::FINANCE_TAX_IN_PROGRESS,
            ClaimFeeExternalWorkflow::FINANCE_WAITING_TAX,
        ], true)) {
            $state = 'waiting';
        }

        return [
            'key' => 'FINANCE_PAYMENT',
            'label' => 'Pembayaran',
            'state' => $state,
            'phase_text' => $phase,
            'actor_name' => $actor,
            'acted_at' => $at,
        ];
    }

    /** @return array{can_edit: bool, can_delete: bool, can_approve: bool, can_reject: bool} */
    public function permissionsFor(ClaimFeeExternal $claim, int $viewerKaryawanId): array
    {
        $isOwner = (int) $claim->sales_id === $viewerKaryawanId;

        return [
            'can_edit' => $isOwner && $this->canEdit($claim),
            'can_delete' => $isOwner && $this->canEdit($claim),
            'can_approve' => $this->canApprove($claim, $viewerKaryawanId),
            'can_reject' => $this->canApprove($claim, $viewerKaryawanId),
        ];
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

    /**
     * Slot periode/order bisa diajukan lagi (mis. ditolak Finance — Q4).
     */
    public function isClaimSlotReleased(ClaimFeeExternal $claim): bool
    {
        if (!($claim->is_active ?? false)) {
            return true;
        }

        if (($claim->finance_status ?? '') === ClaimFeeExternalWorkflow::FINANCE_REJECTED) {
            return true;
        }

        $sales = $claim->sales_workflow_status ?? '';
        $statusPembayaran = strtoupper(trim((string) ($claim->status_pembayaran ?? '')));

        if ($sales === ClaimFeeExternalWorkflow::SALES_APPROVED && $statusPembayaran === 'REJECTED') {
            return true;
        }

        if ($this->workflowEnabled() === false && $statusPembayaran === 'REJECTED') {
            return true;
        }

        return false;
    }

    public function findBlockingClaim(string $noOrder, ?string $periode, ?int $excludeId = null): ?ClaimFeeExternal
    {
        $noOrder = trim($noOrder);
        if ($noOrder === '') {
            return null;
        }

        $periode = $periode !== null ? trim($periode) : null;
        if ($periode === '') {
            $periode = null;
        }

        $query = ClaimFeeExternal::query()
            ->where('is_active', true)
            ->where('no_order', $noOrder);

        if ($periode === null) {
            $query->where(function ($q) {
                $q->whereNull('periode')->orWhere('periode', '');
            });
        } else {
            $query->where('periode', $periode);
        }

        if ($excludeId !== null && $excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        foreach ($query->get() as $claim) {
            if (!$this->isClaimSlotReleased($claim)) {
                return $claim;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function blockedPeriodsForOrder(string $noOrder, ?int $excludeId = null): array
    {
        $noOrder = trim($noOrder);
        if ($noOrder === '') {
            return [];
        }

        $blocked = [];
        $query = ClaimFeeExternal::query()
            ->where('is_active', true)
            ->where('no_order', $noOrder)
            ->whereNotNull('periode')
            ->where('periode', '!=', '');

        if ($excludeId !== null && $excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        foreach ($query->get() as $claim) {
            if ($this->isClaimSlotReleased($claim)) {
                continue;
            }
            $blocked[] = (string) $claim->periode;
        }

        return array_values(array_unique($blocked));
    }

    public function duplicateBlockMessage(ClaimFeeExternal $blocking): string
    {
        $periode = trim((string) ($blocking->periode ?? ''));
        $suffix = $periode !== '' ? " untuk periode {$periode}" : '';

        return 'Claim Fee External untuk No Order ' . ($blocking->no_order ?? '-')
            . $suffix
            . ' sudah diajukan dan masih dalam proses. '
            . 'Tidak dapat mengajukan duplikat. '
            . 'Jika ditolak Finance, ajukan claim baru setelah penolakan.';
    }
}