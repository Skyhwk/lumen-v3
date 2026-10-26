<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use App\Models\SalaryAdjustmentApprovalToken;
use App\Models\SalaryAdjustmentAssessmentSession;
use App\Models\SalaryAdjustmentRequest;
use Carbon\Carbon;

class ApprovalAppPenyesuaianDetailService
{
    public function findByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $approvalToken = SalaryAdjustmentApprovalToken::where('token', $token)->first();
        if (!$approvalToken) {
            return null;
        }

        $record = SalaryAdjustmentRequest::with([
            'kpi.items',
            'statusLogs',
            'assessment.sessions',
            'counseling',
            'newJabatan',
            'rekap',
            'receiverManager',
        ])->find($approvalToken->request_id);

        if (!$record || !$record->is_active) {
            return null;
        }

        $employee = MasterKaryawan::with(['jabatan', 'divisi'])->find($record->employee_id);
        $manager = MasterKaryawan::find($record->requested_by_id);
        $namaJabatan = KaryawanProfileService::resolveJabatan($employee);
        $snapshot = SalaryAdjustmentEvaluationService::formatAdjustmentSnapshot($record);
        $attendance = (new SalaryAdjustmentEvaluationService())->getAttendanceSummary((int) $record->employee_id);
        $workflow = app(EmployeeAdjustmentWorkflowResolver::class);
        $hasCompensationAdjustment = $workflow->recordHasSalaryAdjustment($record);
        $compensationDisplay = $this->buildCompensationDisplayMeta($record, $snapshot, $hasCompensationAdjustment);

        return [
            'id' => (string) $record->id,
            'domain' => 'penyesuaian',
            'token' => $token,
            'title' => $employee->nama_lengkap ?? '-',
            'subtitle' => $namaJabatan !== '-' ? $namaJabatan : ($record->jabatan ?: '-'),
            'can_decide' => $this->canDecide($approvalToken, $record),
            'token_state' => [
                'decision' => $approvalToken->decision,
                'decided_at' => optional($approvalToken->decided_at)->toDateTimeString(),
                'reject_reason' => $approvalToken->reject_reason,
            ],
            'employee' => array_merge([
                'nama_lengkap' => $employee->nama_lengkap ?? '-',
                'nik' => $employee->nik_karyawan ?? null,
                'jabatan' => $namaJabatan !== '-' ? $namaJabatan : ($record->jabatan ?: '-'),
                'divisi' => optional($employee->divisi)->nama_divisi ?? null,
            ], app(ApprovalAppInboxService::class)->employeePhotoFields($employee)),
            'request' => [
                'no_document' => $record->no_document,
                'request_type' => $record->request_type ?: EmployeeAdjustmentTypeRegistry::TYPE_PENYESUAIAN_GAJI,
                'request_type_label' => EmployeeAdjustmentTypeRegistry::label($record->request_type),
                'status' => $record->status,
                'status_label' => SalaryAdjustmentWorkflowService::statusLabel($record->status),
                'manager_nama' => $manager->nama_lengkap ?? $record->created_by,
                'bulan_efektif' => $record->bulan_efektif,
                'tanggal_efektif' => $record->tanggal_efektif,
                'catatan_tambahan' => $record->catatan_tambahan,
                'new_jabatan_nama' => optional($record->newJabatan)->nama_jabatan,
                'has_salary_adjustment' => $hasCompensationAdjustment,
            ],
            'salary' => array_merge([
                'current_gaji_pokok' => (float) $record->current_gaji_pokok,
                'current_tunjangan_kerja' => (float) $record->current_tunjangan_kerja,
                'adjustment_gaji_pokok' => (float) ($record->adjustment_gaji_pokok ?? 0),
                'adjustment_tunjangan' => (float) ($record->adjustment_tunjangan ?? 0),
                'requested_gaji_pokok' => (float) $record->requested_gaji_pokok,
                'requested_tunjangan_kerja' => (float) $record->requested_tunjangan_kerja,
            ], $snapshot, $compensationDisplay),
            'kpi' => $this->mapKpi($record),
            'assessment' => $this->mapAssessmentForRequest($record),
            'attendance' => $this->mapAttendance($attendance),
            'counseling' => $this->mapCounseling($record->counseling),
            'logs' => collect(SalaryAdjustmentWorkflowService::formatLogs($record->statusLogs))
                ->map(function (array $log) {
                    if (!empty($log['created_at'])) {
                        $log['created_at'] = (string) $log['created_at'];
                    }

                    return $log;
                })
                ->take(-10)
                ->reverse()
                ->values()
                ->all(),
        ];
    }

    public function findAttendanceDailyByToken(string $token, string $month): ?array
    {
        $token = trim($token);
        $month = trim($month);
        if ($token === '' || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            return null;
        }

        $approvalToken = SalaryAdjustmentApprovalToken::where('token', $token)->first();
        if (!$approvalToken) {
            return null;
        }

        $record = SalaryAdjustmentRequest::find($approvalToken->request_id);
        if (!$record || !$record->is_active) {
            return null;
        }

        Carbon::setLocale('id');
        $daily = (new SalaryAdjustmentAttendanceSummaryService())->buildMonthDailyForDisplay((int) $record->employee_id, $month);

        return [
            'month' => $month,
            'label' => Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y'),
            'daily' => $this->mapAttendanceDailyList($daily),
        ];
    }

    public function findSessionForToken(string $token, int $sessionId): ?SalaryAdjustmentAssessmentSession
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $approvalToken = SalaryAdjustmentApprovalToken::where('token', $token)->first();
        if (!$approvalToken) {
            return null;
        }

        $session = SalaryAdjustmentAssessmentSession::query()
            ->with('assessment.request')
            ->where('id', $sessionId)
            ->whereHas('assessment', function ($q) use ($approvalToken) {
                $q->where('request_id', $approvalToken->request_id);
            })
            ->first();

        return $session;
    }

    private function canDecide(SalaryAdjustmentApprovalToken $token, SalaryAdjustmentRequest $record): bool
    {
        return $token->approver_role === SalaryAdjustmentEmailService::ROLE_IBU
            && $token->is_active
            && !$token->decision
            && $record->status === SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_IBU;
    }

    private function mapKpi(SalaryAdjustmentRequest $record): ?array
    {
        $kpi = $record->kpi;
        if (!$kpi) {
            return null;
        }

        $items = [];
        foreach ($kpi->items ?? [] as $item) {
            $items[] = [
                'criteria' => $item->criteria_name ?? $item->criteria ?? '-',
                'score' => $item->score,
                'weight' => $item->weight,
                'final_score' => $item->final_score,
            ];
        }

        return [
            'total_score_avg' => $kpi->total_score_avg,
            'total_final_score' => $kpi->total_final_score,
            'interpretation' => $kpi->interpretation,
            'summary' => $kpi->summary,
            'strengths' => $kpi->strengths,
            'improvements' => $kpi->improvements,
            'items' => $items,
        ];
    }

    private function mapAssessmentForRequest(SalaryAdjustmentRequest $record): array
    {
        $assessment = $record->assessment;
        if (!$assessment) {
            return [
                'has_attempt' => false,
                'summary' => 'Belum ada assessment.',
                'sessions' => [],
            ];
        }

        $assessment->loadMissing('sessions');
        if ($assessment->sessions->isEmpty()) {
            return [
                'has_attempt' => false,
                'summary' => 'Belum ada assessment.',
                'sessions' => [],
            ];
        }

        $sessions = [];
        $totalQuestions = 0;
        $completed = 0;

        foreach ($assessment->sessions->sortBy('session_order') as $session) {
            $total = (int) ($session->question_count ?? 0);
            $totalQuestions += $total;
            if (($session->status ?? '') === 'completed') {
                $completed++;
            }

            $storedResult = is_array($session->result_json) ? $session->result_json : [];
            $score = isset($storedResult['score']) ? (float) $storedResult['score'] : null;
            $categoryName = $session->category_name ?? 'Sesi';
            $summaryText = null;
            if ($score !== null) {
                $summaryText = $categoryName . ': ' . $score . '/100';
            } elseif (!empty($storedResult)) {
                $summaryText = 'Hasil assessment tersedia.';
            }

            $sessions[] = [
                'id' => (int) $session->id,
                'name' => $session->session_name ?? $categoryName,
                'category_name' => $session->category_name ?? null,
                'status' => $session->status ?? 'pending',
                'score' => $score,
                'total' => $total,
                'answered' => $total,
                'has_result' => !empty($storedResult),
                'summary_text' => $summaryText,
            ];
        }

        $count = count($sessions);
        $overall = $count > 0 ? (int) round(($completed / $count) * 100) : 0;

        return [
            'has_attempt' => true,
            'attempt_status' => $assessment->attempt_status,
            'summary' => $completed >= $count && $count > 0
                ? 'Assessment selesai — lihat hasil per sesi.'
                : 'Assessment — ' . $completed . '/' . $count . ' sesi selesai',
            'overall_progress' => $overall,
            'total_questions' => $totalQuestions,
            'total_answered' => $totalQuestions,
            'sessions' => $sessions,
        ];
    }

    private function mapAttendance(?array $attendance): ?array
    {
        if (!$attendance || empty($attendance['summary'])) {
            return null;
        }

        $monthly = [];
        foreach ($attendance['monthly'] ?? [] as $month) {
            $monthSummary = is_array($month['summary'] ?? null) ? $month['summary'] : [];
            $monthly[] = array_merge(
                $this->mapAttendanceMetrics($monthSummary),
                [
                    'month' => $month['month'] ?? null,
                    'label' => $month['label'] ?? ($month['month'] ?? '-'),
                    'daily' => $this->mapAttendanceDailyList($month['daily'] ?? []),
                ]
            );
        }

        return array_merge(
            $this->mapAttendanceMetrics($attendance['summary']),
            [
                'period_label' => $attendance['period_label'] ?? null,
                'months_count' => (int) ($attendance['months_count'] ?? 3),
                'monthly' => $monthly,
                'system_effective_from' => $attendance['system_effective_from']
                    ?? SalaryAdjustmentAttendanceSummaryService::SYSTEM_REQUESTS_EFFECTIVE_FROM,
                'system_effective_note' => $attendance['system_effective_note'] ?? null,
            ]
        );
    }

    private function mapAttendanceMetrics(array $summary): array
    {
        return [
            'hadir' => (int) ($summary['hadir'] ?? 0),
            'izin' => (int) ($summary['izin'] ?? 0),
            'cuti' => (int) ($summary['cuti'] ?? 0),
            'lembur' => (int) ($summary['lembur'] ?? 0),
            'alpa' => (int) ($summary['alpa'] ?? $summary['alpha'] ?? 0),
        ];
    }

    private function mapAttendanceDailyList($daily): array
    {
        $rows = [];
        foreach ($daily as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                'tanggal' => $row['tanggal'] ?? null,
                'hari' => $row['hari'] ?? null,
                'date_label' => $row['date_label'] ?? null,
                'shift' => $row['shift'] ?? null,
                'shift_label' => $row['shift_label'] ?? ($row['shift'] ?? null),
                'day_kind' => $row['day_kind'] ?? 'workday',
                'jam_masuk' => $row['jam_masuk'] ?? null,
                'jam_keluar' => $row['jam_keluar'] ?? null,
                'selisih' => $row['selisih'] ?? null,
                'status' => $row['status'] ?? null,
                'keterangan' => $row['keterangan'] ?? null,
            ];
        }

        return $rows;
    }

    private function buildCompensationDisplayMeta(
        SalaryAdjustmentRequest $record,
        array $snapshot,
        bool $showSection
    ): array {
        $useHrd = !empty($snapshot['has_hrd_final_decision']);
        $gajiDelta = $useHrd
            ? (float) ($snapshot['hrd_final_adjustment_gaji_pokok'] ?? $record->adjustment_gaji_pokok ?? 0)
            : (float) ($record->adjustment_gaji_pokok ?? 0);
        $tunjDelta = $useHrd
            ? (float) ($snapshot['hrd_final_adjustment_tunjangan'] ?? $record->adjustment_tunjangan ?? 0)
            : (float) ($record->adjustment_tunjangan ?? 0);

        $requestType = EmployeeAdjustmentTypeRegistry::normalizeType($record->request_type);
        if ($requestType === EmployeeAdjustmentTypeRegistry::TYPE_PENYESUAIAN_GAJI) {
            $showSection = true;
        }

        return [
            'show_compensation_section' => $showSection,
            'has_gaji_adjustment' => abs($gajiDelta) >= 0.005,
            'has_tunjangan_adjustment' => abs($tunjDelta) >= 0.005,
        ];
    }

    private function mapCounseling($counseling): ?array
    {
        if (!$counseling) {
            return null;
        }

        return [
            'status' => $counseling->status,
            'scheduled_date' => $counseling->scheduled_date,
            'scheduled_time' => $counseling->scheduled_time,
            'location' => $counseling->location,
            'counselor_name' => $counseling->counselor_name,
            'description' => $counseling->description,
            'result_notes' => $counseling->result_notes,
            'completed_at' => optional($counseling->completed_at)->toDateTimeString(),
        ];
    }
}
