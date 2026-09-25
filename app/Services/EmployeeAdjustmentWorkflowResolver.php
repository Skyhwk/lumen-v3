<?php

namespace App\Services;

use App\Models\SalaryAdjustmentRequest;
use Carbon\Carbon;

class EmployeeAdjustmentWorkflowResolver
{
    public const APPLY_STATUS_PENDING = 'pending';
    public const APPLY_STATUS_APPLIED = 'applied';
    public const APPLY_STATUS_FAILED = 'failed';

    /** @var array<string, string> */
    public const APPLY_STATUS_LABELS = [
        self::APPLY_STATUS_PENDING => 'Menunggu Apply',
        self::APPLY_STATUS_APPLIED => 'Sudah Diterapkan',
        self::APPLY_STATUS_FAILED => 'Apply Gagal',
    ];

    public const STATUS_WAITING_RECEIVER = 'waiting_receiver';
    public const STATUS_RECEIVER_RESPONDED = 'receiver_responded';

    public function profileForType(?string $requestType): string
    {
        return EmployeeAdjustmentTypeRegistry::workflowProfile($requestType);
    }

    public function requiresFinanceReview(SalaryAdjustmentRequest $record): bool
    {
        if (!$this->recordHasSalaryAdjustment($record)) {
            return false;
        }

        return EmployeeAdjustmentTypeRegistry::get($record->request_type)['requires_finance_when_salary'] ?? true;
    }

    public function requiresAssessment(?string $requestType): bool
    {
        return EmployeeAdjustmentTypeRegistry::requiresAssessment($requestType);
    }

    public function requiresCounseling(?string $requestType): bool
    {
        return EmployeeAdjustmentTypeRegistry::requiresCounseling($requestType);
    }

    public function recordHasSalaryAdjustment(SalaryAdjustmentRequest $record): bool
    {
        if ($record->has_salary_adjustment === false) {
            return false;
        }

        $gaji = (float) ($record->adjustment_gaji_pokok ?? 0);
        $tunj = (float) ($record->adjustment_tunjangan ?? 0);

        if ($record->request_type === EmployeeAdjustmentTypeRegistry::TYPE_MUTASI) {
            if ($record->receiver_salary_decision === EmployeeAdjustmentMutasiService::SALARY_SAMA) {
                return false;
            }

            if (in_array($record->receiver_salary_decision, [
                EmployeeAdjustmentMutasiService::SALARY_KENAIKAN,
                EmployeeAdjustmentMutasiService::SALARY_PENURUNAN,
            ], true)) {
                $gaji = (float) ($record->receiver_adjustment_gaji ?? 0);
                $tunj = (float) ($record->receiver_adjustment_tunjangan ?? 0);

                return abs($gaji) > 0 || abs($tunj) > 0;
            }

            return false;
        }

        return abs($gaji) > 0 || abs($tunj) > 0;
    }

    public function resolveScheduledApplyDate(array $payload, string $requestType): ?Carbon
    {
        $type = EmployeeAdjustmentTypeRegistry::normalizeType($requestType);

        if (in_array($type, [
            EmployeeAdjustmentTypeRegistry::TYPE_GAGAL_PELATIHAN,
            EmployeeAdjustmentTypeRegistry::TYPE_PHK,
        ], true)) {
            return $this->parseDate($payload['tanggal_berakhir_kerja'] ?? null);
        }

        if (in_array($type, [
            EmployeeAdjustmentTypeRegistry::TYPE_PERPANJANG_KONTRAK,
            EmployeeAdjustmentTypeRegistry::TYPE_PERPANJANG_PELATIHAN,
        ], true)) {
            return $this->parseDate($payload['tanggal_mulai'] ?? null);
        }

        if (!empty($payload['tanggal_efektif'])) {
            return $this->parseDate($payload['tanggal_efektif']);
        }

        if (!empty($payload['bulan_efektif']) && preg_match('/^\d{4}-\d{2}$/', (string) $payload['bulan_efektif'])) {
            return Carbon::createFromFormat('Y-m-d', $payload['bulan_efektif'] . '-01')->startOfDay();
        }

        return null;
    }

    public function shouldApplyImmediately(?Carbon $scheduledApplyAt): bool
    {
        if (!$scheduledApplyAt) {
            return true;
        }

        return $scheduledApplyAt->lte(Carbon::today());
    }

    public static function applyStatusLabel(?string $status): string
    {
        if ($status === null || $status === '') {
            return '-';
        }

        return self::APPLY_STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public function resolvePostProcessStatus(SalaryAdjustmentRequest $record): string
    {
        if (!$this->requiresAssessment($record->request_type)) {
            return SalaryAdjustmentWorkflowService::STATUS_FINAL_EVALUATION;
        }

        return SalaryAdjustmentWorkflowService::STATUS_HRD_PROCESSING;
    }

    public function resolvePostAssessmentCompletedStatus(SalaryAdjustmentRequest $record): string
    {
        if (!$this->requiresCounseling($record->request_type)) {
            return SalaryAdjustmentWorkflowService::STATUS_FINAL_EVALUATION;
        }

        return SalaryAdjustmentWorkflowService::STATUS_ASSESSMENT_COMPLETED;
    }

    /** @return list<string> */
    public static function assessmentSkippableStatuses(): array
    {
        return [
            SalaryAdjustmentWorkflowService::STATUS_HRD_PROCESSING,
            SalaryAdjustmentWorkflowService::STATUS_WAITING_ASSESSMENT,
            SalaryAdjustmentWorkflowService::STATUS_ASSESSMENT_IN_PROGRESS,
        ];
    }

    public function resolveSkipAssessmentTarget(SalaryAdjustmentRequest $record, string $target = 'next'): string
    {
        $target = strtolower(trim($target));

        if ($target === 'final') {
            return SalaryAdjustmentWorkflowService::STATUS_FINAL_EVALUATION;
        }

        return $this->resolvePostAssessmentCompletedStatus($record);
    }

    public function resolvePostFinalEvaluationStatus(SalaryAdjustmentRequest $record): string
    {
        if ($this->requiresFinanceReview($record)) {
            return SalaryAdjustmentWorkflowService::STATUS_FINANCE_REVIEW;
        }

        return SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_IBU;
    }

    public function requiresSalaryDecisionAtFinalEvaluation(SalaryAdjustmentRequest $record): bool
    {
        return $this->recordHasSalaryAdjustment($record);
    }

    public function buildWorkflowMeta(SalaryAdjustmentRequest $record): array
    {
        return [
            'request_type' => $record->request_type ?: EmployeeAdjustmentTypeRegistry::TYPE_PENYESUAIAN_GAJI,
            'request_type_label' => EmployeeAdjustmentTypeRegistry::label($record->request_type),
            'workflow_profile' => $record->workflow_profile ?: $this->profileForType($record->request_type),
            'requires_assessment' => $this->requiresAssessment($record->request_type),
            'requires_counseling' => $this->requiresCounseling($record->request_type),
            'requires_finance' => $this->requiresFinanceReview($record),
            'has_salary_adjustment' => $this->recordHasSalaryAdjustment($record),
            'requires_salary_at_final_evaluation' => $this->requiresSalaryDecisionAtFinalEvaluation($record),
        ];
    }

    public function postProcessLogNotes(SalaryAdjustmentRequest $record): string
    {
        if (!$this->requiresAssessment($record->request_type)) {
            return 'HRD memproses permohonan — lewati assessment & konseling';
        }

        return 'HRD memulai proses permohonan';
    }

    private function parseDate($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
