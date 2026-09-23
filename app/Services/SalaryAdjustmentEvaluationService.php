<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use App\Models\SalaryAdjustmentRequest;

class SalaryAdjustmentEvaluationService
{
    /** @var SalaryAdjustmentAssessmentReportService */
    private $assessmentReportService;

    /** @var SalaryAdjustmentAttendanceSummaryService */
    private $attendanceSummaryService;

    public function __construct(
        SalaryAdjustmentAssessmentReportService $assessmentReportService = null,
        SalaryAdjustmentAttendanceSummaryService $attendanceSummaryService = null
    ) {
        $this->assessmentReportService = $assessmentReportService ?: new SalaryAdjustmentAssessmentReportService();
        $this->attendanceSummaryService = $attendanceSummaryService ?: new SalaryAdjustmentAttendanceSummaryService();
    }

    public function getAttendanceSummary(int $employeeId, int $months = 3): array
    {
        return $this->attendanceSummaryService->build($employeeId, $months);
    }

    public function buildBundle(SalaryAdjustmentRequest $record): array
    {
        $record->loadMissing(['kpi.items', 'assessment.sessions', 'counseling', 'statusLogs']);

        $employee = MasterKaryawan::with(['jabatan', 'divisi'])->find($record->employee_id);
        $manager = MasterKaryawan::find($record->requested_by_id);

        $adjustmentSnapshot = self::formatAdjustmentSnapshot($record);

        return [
            'request' => array_merge([
                'id' => $record->id,
                'no_document' => $record->no_document,
                'nama_lengkap' => $employee->nama_lengkap ?? '-',
                'nik_karyawan' => $employee->nik_karyawan ?? '-',
                'department' => optional($employee->divisi)->nama_divisi ?? ($employee->department ?? '-'),
                'manager_nama' => $manager->nama_lengkap ?? $record->created_by,
                'jabatan' => $record->jabatan ?: (optional($employee->jabatan)->nama_jabatan ?? '-'),
                'employee_photo' => SalaryAdjustmentEmailViewData::employeePhotoDataUri($employee->image ?? null),
                'employee_photo_url' => SalaryAdjustmentEmailViewData::employeePhotoUrl($employee->image ?? null),
                'current_gaji_pokok' => (float) $record->current_gaji_pokok,
                'current_tunjangan_kerja' => (float) $record->current_tunjangan_kerja,
                'adjustment_gaji_pokok' => (float) ($record->adjustment_gaji_pokok ?? 0),
                'adjustment_tunjangan' => (float) ($record->adjustment_tunjangan ?? 0),
                'requested_gaji_pokok' => (float) $record->requested_gaji_pokok,
                'requested_tunjangan_kerja' => (float) $record->requested_tunjangan_kerja,
                'bulan_efektif' => $record->bulan_efektif,
                'catatan_tambahan' => $record->catatan_tambahan,
                'status' => $record->status,
                'status_label' => SalaryAdjustmentWorkflowService::statusLabel($record->status),
                'finance_approved_at' => $record->finance_approved_at,
                'finance_approved_by' => $record->finance_approved_by,
                'ibu_approved_at' => $record->ibu_approved_at,
                'ibu_approved_by' => $record->ibu_approved_by,
            ], $adjustmentSnapshot),
            'kpi' => $record->kpi,
            'assessment' => $record->assessment,
            'assessment_report' => $this->assessmentReportService->buildAssessmentReport($record->assessment),
            'counseling' => $record->counseling,
            'attendance' => $this->getAttendanceSummary((int) $record->employee_id),
            'logs' => SalaryAdjustmentWorkflowService::formatLogs($record->statusLogs),
        ];
    }

    public static function formatAdjustmentSnapshot(SalaryAdjustmentRequest $record): array
    {
        $submittedGaji = (float) ($record->submitted_adjustment_gaji_pokok ?? $record->adjustment_gaji_pokok ?? 0);
        $submittedTunj = (float) ($record->submitted_adjustment_tunjangan ?? $record->adjustment_tunjangan ?? 0);
        $submittedReqGaji = (float) ($record->submitted_requested_gaji_pokok ?? $record->requested_gaji_pokok ?? 0);
        $submittedReqTunj = (float) ($record->submitted_requested_tunjangan_kerja ?? $record->requested_tunjangan_kerja ?? 0);

        $hrdGaji = (float) ($record->hrd_final_adjustment_gaji_pokok ?? $record->adjustment_gaji_pokok ?? 0);
        $hrdTunj = (float) ($record->hrd_final_adjustment_tunjangan ?? $record->adjustment_tunjangan ?? 0);
        $hrdReqGaji = (float) ($record->hrd_final_requested_gaji_pokok ?? $record->requested_gaji_pokok ?? 0);
        $hrdReqTunj = (float) ($record->hrd_final_requested_tunjangan_kerja ?? $record->requested_tunjangan_kerja ?? 0);

        $activeGaji = (float) ($record->adjustment_gaji_pokok ?? 0);
        $activeTunj = (float) ($record->adjustment_tunjangan ?? 0);
        $activeReqGaji = (float) $record->requested_gaji_pokok;
        $activeReqTunj = (float) $record->requested_tunjangan_kerja;

        $changedByHrd = round($submittedGaji, 2) !== round($hrdGaji, 2)
            || round($submittedTunj, 2) !== round($hrdTunj, 2)
            || round($submittedReqGaji, 2) !== round($hrdReqGaji, 2)
            || round($submittedReqTunj, 2) !== round($hrdReqTunj, 2);

        $changedByFinance = $record->hrd_final_adjustment_gaji_pokok !== null
            && (
                round($hrdGaji, 2) !== round($activeGaji, 2)
                || round($hrdTunj, 2) !== round($activeTunj, 2)
                || round($hrdReqGaji, 2) !== round($activeReqGaji, 2)
                || round($hrdReqTunj, 2) !== round($activeReqTunj, 2)
            );

        return [
            'submitted_adjustment_gaji_pokok' => $submittedGaji,
            'submitted_adjustment_tunjangan' => $submittedTunj,
            'submitted_requested_gaji_pokok' => $submittedReqGaji,
            'submitted_requested_tunjangan_kerja' => $submittedReqTunj,
            'hrd_final_adjustment_gaji_pokok' => $hrdGaji,
            'hrd_final_adjustment_tunjangan' => $hrdTunj,
            'hrd_final_requested_gaji_pokok' => $hrdReqGaji,
            'hrd_final_requested_tunjangan_kerja' => $hrdReqTunj,
            'changed_by_hrd' => $changedByHrd,
            'changed_by_finance' => $changedByFinance,
            'hrd_final_adjustment_notes' => $record->hrd_final_adjustment_notes,
            'finance_final_adjustment_notes' => $record->finance_final_adjustment_notes,
            'finance_return_reason' => $record->finance_return_reason,
            'hrd_appeal_notes' => $record->hrd_appeal_notes,
        ];
    }

    public static function ensureHrdFinalSnapshot(SalaryAdjustmentRequest $record): void
    {
        if ($record->hrd_final_adjustment_gaji_pokok !== null) {
            return;
        }

        $record->hrd_final_adjustment_gaji_pokok = $record->adjustment_gaji_pokok;
        $record->hrd_final_adjustment_tunjangan = $record->adjustment_tunjangan;
        $record->hrd_final_requested_gaji_pokok = $record->requested_gaji_pokok;
        $record->hrd_final_requested_tunjangan_kerja = $record->requested_tunjangan_kerja;
    }
}
