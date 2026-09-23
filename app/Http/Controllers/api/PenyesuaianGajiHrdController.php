<?php

namespace App\Http\Controllers\api;

use App\Helpers\FrontendPublicUrl;
use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\SalaryAdjustmentCounseling;
use App\Models\SalaryAdjustmentRequest;
use App\Services\EmployeeAdjustmentMutasiService;
use App\Services\EmployeeAdjustmentRekapService;
use App\Services\EmployeeAdjustmentTypeRegistry;
use App\Services\EmployeeAdjustmentWorkflowResolver;
use App\Services\KaryawanProfileService;
use App\Services\SalaryAdjustmentAssessmentReportService;
use App\Services\SalaryAdjustmentAssessmentService;
use App\Services\SalaryAdjustmentEmailService;
use App\Services\SalaryAdjustmentEvaluationService;
use App\Services\SalaryAdjustmentLogService;
use App\Services\SalaryAdjustmentWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;

class PenyesuaianGajiHrdController extends Controller
{
    public function tabCounts(Request $request)
    {
        $periode = $request->periode ?? date('Y');

        $base = DB::connection('mysql')
            ->table('salary_adjustment_requests')
            ->where('is_active', true)
            ->whereYear('created_at', $periode);

        return response()->json([
            'success' => true,
            'data' => [
                'waiting_process' => (clone $base)
                    ->whereIn('status', SalaryAdjustmentWorkflowService::statusesForHrdTab(
                        SalaryAdjustmentWorkflowService::HRD_TAB_WAITING_PROCESS
                    ))
                    ->count(),
                'waiting_assessment' => (clone $base)
                    ->whereIn('status', SalaryAdjustmentWorkflowService::statusesForHrdTab(
                        SalaryAdjustmentWorkflowService::HRD_TAB_WAITING_ASSESSMENT
                    ))
                    ->count(),
                'counseling_schedule' => (clone $base)
                    ->where('status', SalaryAdjustmentWorkflowService::STATUS_ASSESSMENT_COMPLETED)
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('salary_adjustment_counselings as sac')
                            ->whereColumn('sac.request_id', 'salary_adjustment_requests.id');
                    })
                    ->count(),
                'final_evaluation' => (clone $base)
                    ->whereIn('status', SalaryAdjustmentWorkflowService::statusesForHrdTab(
                        SalaryAdjustmentWorkflowService::HRD_TAB_FINAL_EVALUATION
                    ))
                    ->count(),
                'waiting_approval_ibu' => (clone $base)
                    ->where('status', SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_IBU)
                    ->count(),
                'waiting_approval_bapak' => (clone $base)
                    ->where('status', SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_BAPAK)
                    ->count(),
                'rekap_complete' => (clone $base)
                    ->where('status', SalaryAdjustmentWorkflowService::STATUS_COMPLETED)
                    ->count(),
                'rekap_rejected' => (clone $base)
                    ->where('status', SalaryAdjustmentWorkflowService::STATUS_REJECTED)
                    ->count(),
            ],
        ]);
    }

    public function indexWaitingProcess(Request $request)
    {
        $periode = $request->periode ?? date('Y');

        $query = DB::connection('mysql')
            ->table('salary_adjustment_requests as sar')
            ->leftJoin('master_karyawan as karyawan', 'sar.employee_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('master_karyawan as manager', 'sar.requested_by_id', '=', 'manager.id')
            ->where('sar.is_active', true)
            ->whereYear('sar.created_at', $periode)
            ->whereIn('sar.status', SalaryAdjustmentWorkflowService::statusesForHrdTab(
                SalaryAdjustmentWorkflowService::HRD_TAB_WAITING_PROCESS
            ))
            ->select(
                'sar.id',
                'sar.no_document',
                'sar.request_type',
                'sar.employee_id',
                'sar.jabatan',
                'sar.adjustment_gaji_pokok',
                'sar.adjustment_tunjangan',
                'sar.bulan_efektif',
                'sar.status',
                'sar.created_by',
                'sar.created_at',
                'karyawan.nama_lengkap',
                'd.nama_divisi',
                'manager.nama_lengkap as manager_nama'
            )
            ->orderByDesc('sar.id');

        return $this->applyHrdDatatablesFilters(
            $this->decorateRequestTypeColumn(
                Datatables::of($query)
                    ->addColumn('status_label', function ($row) {
                        return SalaryAdjustmentWorkflowService::statusLabel($row->status);
                    })
                    ->addColumn('skip_assessment', function ($row) {
                        return !EmployeeAdjustmentTypeRegistry::requiresAssessment($row->request_type ?? null);
                    })
            )
        )->make(true);
    }

    public function indexWaitingAssessment(Request $request)
    {
        return $this->indexByHrdTab($request, SalaryAdjustmentWorkflowService::HRD_TAB_WAITING_ASSESSMENT, true);
    }

    public function indexFinalEvaluation(Request $request)
    {
        return $this->indexByHrdTab($request, SalaryAdjustmentWorkflowService::HRD_TAB_FINAL_EVALUATION, false);
    }

    public function indexWaitingApprovalIbu(Request $request)
    {
        return $this->indexByHrdTab($request, SalaryAdjustmentWorkflowService::HRD_TAB_WAITING_APPROVAL_IBU, false);
    }

    public function indexWaitingApprovalBapak(Request $request)
    {
        return $this->indexByHrdTab($request, SalaryAdjustmentWorkflowService::HRD_TAB_WAITING_APPROVAL_BAPAK, false);
    }

    public function indexRekapComplete(Request $request)
    {
        return $this->indexRekap($request, SalaryAdjustmentWorkflowService::STATUS_COMPLETED);
    }

    public function indexRekapRejected(Request $request)
    {
        return $this->indexRekap($request, SalaryAdjustmentWorkflowService::STATUS_REJECTED);
    }

    public function evaluationBundle(Request $request)
    {
        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => (new SalaryAdjustmentEvaluationService())->buildBundle($record),
        ]);
    }

    public function approveFinalEvaluation(Request $request)
    {
        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if ($record->status !== SalaryAdjustmentWorkflowService::STATUS_FINAL_EVALUATION) {
            return response()->json(['success' => false, 'message' => 'Permohonan tidak dapat disetujui pada status ini'], 400);
        }

        $workflowResolver = new EmployeeAdjustmentWorkflowResolver();
        $requiresSalary = $workflowResolver->requiresSalaryDecisionAtFinalEvaluation($record);
        $adjustmentGaji = 0.0;
        $adjustmentTunjangan = 0.0;

        if ($requiresSalary) {
            $adjustmentGaji = $this->parseAmount(
                $request->adjustment_gaji_pokok ?? $record->adjustment_gaji_pokok
            );
            $adjustmentTunjangan = $this->parseAmount(
                $request->adjustment_tunjangan ?? $record->adjustment_tunjangan
            );
            if ($adjustmentGaji <= 0 && $adjustmentTunjangan <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Minimal salah satu penyesuaian gaji pokok atau tunjangan harus diisi',
                ], 400);
            }
        }

        $nextStatus = $workflowResolver->resolvePostFinalEvaluationStatus($record);
        $skipFinance = $nextStatus === SalaryAdjustmentWorkflowService::STATUS_WAITING_APPROVAL_IBU;

        DB::connection('mysql')->beginTransaction();
        try {
            $from = $record->status;
            $hrdNotes = trim((string) ($request->hrd_final_adjustment_notes ?? ''));
            $notes = trim((string) ($request->notes ?? '')) ?: 'HRD menyetujui evaluasi final';

            $this->ensureSubmittedSnapshot($record);

            $submittedSnapshot = [
                'adjustment_gaji_pokok' => (float) ($record->submitted_adjustment_gaji_pokok ?? 0),
                'adjustment_tunjangan' => (float) ($record->submitted_adjustment_tunjangan ?? 0),
                'requested_gaji_pokok' => (float) ($record->submitted_requested_gaji_pokok ?? 0),
                'requested_tunjangan_kerja' => (float) ($record->submitted_requested_tunjangan_kerja ?? 0),
            ];

            if ($requiresSalary) {
                $currentGaji = (float) $record->current_gaji_pokok;
                $currentTunjangan = (float) $record->current_tunjangan_kerja;

                $record->adjustment_gaji_pokok = $adjustmentGaji > 0 ? $adjustmentGaji : null;
                $record->adjustment_tunjangan = $adjustmentTunjangan > 0 ? $adjustmentTunjangan : null;
                $record->requested_gaji_pokok = $currentGaji + max(0, $adjustmentGaji);
                $record->requested_tunjangan_kerja = $currentTunjangan + max(0, $adjustmentTunjangan);
                $record->hrd_final_adjustment_gaji_pokok = $adjustmentGaji;
                $record->hrd_final_adjustment_tunjangan = $adjustmentTunjangan;
                $record->hrd_final_requested_gaji_pokok = $record->requested_gaji_pokok;
                $record->hrd_final_requested_tunjangan_kerja = $record->requested_tunjangan_kerja;
            }

            $record->hrd_final_adjustment_notes = $hrdNotes !== '' ? $hrdNotes : null;
            $record->status = $nextStatus;
            $record->final_eval_approved_by = $this->karyawan;
            $record->final_eval_approved_at = Carbon::now();
            $record->updated_by = $this->karyawan;
            $record->save();

            $finalSnapshot = [
                'adjustment_gaji_pokok' => (float) ($record->adjustment_gaji_pokok ?? 0),
                'adjustment_tunjangan' => (float) ($record->adjustment_tunjangan ?? 0),
                'requested_gaji_pokok' => (float) $record->requested_gaji_pokok,
                'requested_tunjangan_kerja' => (float) $record->requested_tunjangan_kerja,
            ];

            $changedByHrd = $requiresSalary && (
                round($submittedSnapshot['adjustment_gaji_pokok'], 2) !== round($finalSnapshot['adjustment_gaji_pokok'], 2)
                || round($submittedSnapshot['adjustment_tunjangan'], 2) !== round($finalSnapshot['adjustment_tunjangan'], 2)
                || round($submittedSnapshot['requested_gaji_pokok'], 2) !== round($finalSnapshot['requested_gaji_pokok'], 2)
                || round($submittedSnapshot['requested_tunjangan_kerja'], 2) !== round($finalSnapshot['requested_tunjangan_kerja'], 2)
            );
            if ($changedByHrd && $notes === 'HRD menyetujui evaluasi final') {
                $notes = 'HRD menyetujui evaluasi final dengan penyesuaian nominal';
            }
            if ($skipFinance && $notes === 'HRD menyetujui evaluasi final') {
                $notes = 'HRD menyetujui evaluasi final — lewati Finance';
            }

            SalaryAdjustmentLogService::log(
                $record->id,
                $from,
                $record->status,
                'final_eval_approve',
                $this->user_id,
                $this->karyawan,
                $notes,
                [
                    'submitted' => $submittedSnapshot,
                    'final' => $finalSnapshot,
                    'changed_by_hrd' => $changedByHrd,
                    'hrd_final_adjustment_notes' => $hrdNotes !== '' ? $hrdNotes : null,
                    'skip_finance' => $skipFinance,
                ]
            );

            DB::connection('mysql')->commit();

            $emailSent = false;
            if ($skipFinance) {
                $emailSent = (new SalaryAdjustmentEmailService())->sendForRole(
                    $record->fresh(),
                    SalaryAdjustmentEmailService::ROLE_IBU,
                    $this->karyawan ?: 'HRD'
                );
            }

            return response()->json([
                'success' => true,
                'message' => $skipFinance
                    ? ($emailSent
                        ? 'Evaluasi final disetujui — lewati Finance. Email Waiting Approval terkirim.'
                        : 'Evaluasi final disetujui — lewati Finance. Email Waiting Approval gagal dikirim.')
                    : 'Evaluasi final berhasil disetujui',
                'skip_finance' => $skipFinance,
                'email_sent' => $emailSent,
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function rejectFinalEvaluation(Request $request)
    {
        $reason = trim((string) ($request->reject_reason ?? $request->keterangan ?? ''));
        if ($reason === '') {
            return response()->json(['success' => false, 'message' => 'Alasan penolakan wajib diisi'], 422);
        }

        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if ($record->status !== SalaryAdjustmentWorkflowService::STATUS_FINAL_EVALUATION) {
            return response()->json(['success' => false, 'message' => 'Permohonan tidak dapat ditolak pada status ini'], 400);
        }

        DB::connection('mysql')->beginTransaction();
        try {
            $from = $record->status;

            $record->status = SalaryAdjustmentWorkflowService::STATUS_REJECTED;
            $record->rejected_stage = SalaryAdjustmentWorkflowService::STATUS_FINAL_EVALUATION;
            $record->reject_reason = $reason;
            $record->final_eval_rejected_by = $this->karyawan;
            $record->final_eval_rejected_at = Carbon::now();
            $record->rejected_by = $this->karyawan;
            $record->rejected_at = Carbon::now();
            $record->updated_by = $this->karyawan;
            $record->save();

            SalaryAdjustmentLogService::log(
                $record->id,
                $from,
                $record->status,
                'final_eval_reject',
                $this->user_id,
                $this->karyawan,
                $reason
            );

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Permohonan berhasil ditolak',
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function approveFinanceAppeal(Request $request)
    {
        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if ($record->status !== SalaryAdjustmentWorkflowService::STATUS_FINANCE_RETURNED) {
            return response()->json(['success' => false, 'message' => 'Permohonan tidak dapat diajukan banding pada status ini'], 400);
        }

        $appealNotes = trim((string) ($request->hrd_appeal_notes ?? ''));
        if ($appealNotes === '') {
            return response()->json(['success' => false, 'message' => 'Keterangan banding wajib diisi'], 422);
        }

        $adjustmentGaji = $this->parseAmount(
            $request->adjustment_gaji_pokok ?? $record->hrd_final_adjustment_gaji_pokok ?? $record->adjustment_gaji_pokok
        );
        $adjustmentTunjangan = $this->parseAmount(
            $request->adjustment_tunjangan ?? $record->hrd_final_adjustment_tunjangan ?? $record->adjustment_tunjangan
        );
        if ($adjustmentGaji <= 0 && $adjustmentTunjangan <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal salah satu penyesuaian gaji pokok atau tunjangan harus diisi',
            ], 400);
        }

        DB::connection('mysql')->beginTransaction();
        try {
            $from = $record->status;
            $currentGaji = (float) $record->current_gaji_pokok;
            $currentTunjangan = (float) $record->current_tunjangan_kerja;

            $record->adjustment_gaji_pokok = $adjustmentGaji > 0 ? $adjustmentGaji : null;
            $record->adjustment_tunjangan = $adjustmentTunjangan > 0 ? $adjustmentTunjangan : null;
            $record->requested_gaji_pokok = $currentGaji + max(0, $adjustmentGaji);
            $record->requested_tunjangan_kerja = $currentTunjangan + max(0, $adjustmentTunjangan);
            $record->hrd_final_adjustment_gaji_pokok = $adjustmentGaji;
            $record->hrd_final_adjustment_tunjangan = $adjustmentTunjangan;
            $record->hrd_final_requested_gaji_pokok = $record->requested_gaji_pokok;
            $record->hrd_final_requested_tunjangan_kerja = $record->requested_tunjangan_kerja;
            $record->hrd_appeal_notes = $appealNotes;
            $record->finance_return_reason = null;
            $record->finance_rejected_by = null;
            $record->finance_rejected_at = null;
            $record->status = SalaryAdjustmentWorkflowService::STATUS_FINANCE_REVIEW;
            $record->updated_by = $this->karyawan;
            $record->save();

            SalaryAdjustmentLogService::log(
                $record->id,
                $from,
                $record->status,
                'hrd_appeal_approve',
                $this->user_id,
                $this->karyawan,
                $appealNotes,
                [
                    'hrd_final' => [
                        'adjustment_gaji_pokok' => (float) ($record->hrd_final_adjustment_gaji_pokok ?? 0),
                        'adjustment_tunjangan' => (float) ($record->hrd_final_adjustment_tunjangan ?? 0),
                        'requested_gaji_pokok' => (float) $record->hrd_final_requested_gaji_pokok,
                        'requested_tunjangan_kerja' => (float) $record->hrd_final_requested_tunjangan_kerja,
                    ],
                ]
            );

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Banding berhasil diajukan — permohonan dikembalikan ke Finance',
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function rejectFinanceAppeal(Request $request)
    {
        $reason = trim((string) ($request->reject_reason ?? $request->keterangan ?? ''));
        if ($reason === '') {
            return response()->json(['success' => false, 'message' => 'Alasan penolakan wajib diisi'], 422);
        }

        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if ($record->status !== SalaryAdjustmentWorkflowService::STATUS_FINANCE_RETURNED) {
            return response()->json(['success' => false, 'message' => 'Permohonan tidak dapat ditolak pada status ini'], 400);
        }

        DB::connection('mysql')->beginTransaction();
        try {
            $from = $record->status;

            $record->status = SalaryAdjustmentWorkflowService::STATUS_REJECTED;
            $record->rejected_stage = SalaryAdjustmentWorkflowService::STATUS_FINANCE_RETURNED;
            $record->reject_reason = $reason;
            $record->rejected_by = $this->karyawan;
            $record->rejected_at = Carbon::now();
            $record->updated_by = $this->karyawan;
            $record->save();

            SalaryAdjustmentLogService::log(
                $record->id,
                $from,
                $record->status,
                'hrd_appeal_reject',
                $this->user_id,
                $this->karyawan,
                $reason
            );

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Banding ditolak HRD — proses permohonan selesai',
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function indexCounselingSchedule(Request $request)
    {
        $periode = $request->periode ?? date('Y');

        $query = DB::connection('mysql')
            ->table('salary_adjustment_requests as sar')
            ->leftJoin('master_karyawan as karyawan', 'sar.employee_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('master_karyawan as manager', 'sar.requested_by_id', '=', 'manager.id')
            ->leftJoin('salary_adjustment_assessments as sa', 'sar.id', '=', 'sa.request_id')
            ->where('sar.is_active', true)
            ->whereYear('sar.created_at', $periode)
            ->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_ASSESSMENT_COMPLETED)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('salary_adjustment_counselings as sac')
                    ->whereColumn('sac.request_id', 'sar.id');
            })
            ->select(
                'sar.id',
                'sar.no_document',
                'sar.request_type',
                'sar.employee_id',
                'sar.jabatan',
                'sar.bulan_efektif',
                'sar.status',
                'sar.created_at',
                'karyawan.nama_lengkap',
                'd.nama_divisi',
                'manager.nama_lengkap as manager_nama',
                'sa.total_score',
                'sa.completed_at as assessment_completed_at'
            )
            ->orderByDesc('sar.id');

        return $this->applyHrdDatatablesFilters(
            $this->decorateRequestTypeColumn(
                Datatables::of($query)
                    ->addColumn('status_label', function ($row) {
                        return SalaryAdjustmentWorkflowService::statusLabel($row->status);
                    })
                    ->addColumn('can_schedule', fn () => true)
            )
        )->make(true);
    }

    public function show(Request $request)
    {
        $record = SalaryAdjustmentRequest::with(['kpi.items', 'statusLogs', 'assessment.sessions', 'counseling', 'newJabatan', 'rekap', 'receiverManager'])->find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $employee = MasterKaryawan::with('jabatan')->find($record->employee_id);
        $manager = MasterKaryawan::find($record->requested_by_id);
        $namaJabatan = KaryawanProfileService::resolveJabatan($employee);

        return response()->json([
            'success' => true,
            'data' => array_merge([
                'id' => $record->id,
                'no_document' => $record->no_document,
                'request_type' => $record->request_type ?: EmployeeAdjustmentTypeRegistry::TYPE_PENYESUAIAN_GAJI,
                'request_type_label' => EmployeeAdjustmentTypeRegistry::label($record->request_type),
                'workflow_profile' => $record->workflow_profile,
                'nama_lengkap' => $employee->nama_lengkap ?? '-',
                'manager_nama' => $manager->nama_lengkap ?? $record->created_by,
                'jabatan' => $namaJabatan !== '-' ? $namaJabatan : ($record->jabatan ?: '-'),
                'current_gaji_pokok' => (float) $record->current_gaji_pokok,
                'current_tunjangan_kerja' => (float) $record->current_tunjangan_kerja,
                'adjustment_gaji_pokok' => (float) ($record->adjustment_gaji_pokok ?? 0),
                'adjustment_tunjangan' => (float) ($record->adjustment_tunjangan ?? 0),
                'requested_gaji_pokok' => (float) $record->requested_gaji_pokok,
                'requested_tunjangan_kerja' => (float) $record->requested_tunjangan_kerja,
                'bulan_efektif' => $record->bulan_efektif,
                'tanggal_efektif' => $record->tanggal_efektif,
                'tanggal_mulai' => $record->tanggal_mulai,
                'tanggal_selesai' => $record->tanggal_selesai,
                'tanggal_berakhir_kerja' => $record->tanggal_berakhir_kerja,
                'new_jabatan_id' => $record->new_jabatan_id,
                'new_jabatan_nama' => optional($record->newJabatan)->nama_jabatan,
                'has_salary_adjustment' => (bool) $record->has_salary_adjustment,
                'receiver_manager_id' => $record->receiver_manager_id,
                'receiver_manager_nama' => optional($record->receiverManager)->nama_lengkap,
                'scheduled_apply_at' => $record->scheduled_apply_at,
                'catatan_tambahan' => $record->catatan_tambahan,
                'status' => $record->status,
                'status_label' => SalaryAdjustmentWorkflowService::statusLabel($record->status),
                'workflow_meta' => (new EmployeeAdjustmentWorkflowResolver())->buildWorkflowMeta($record),
                'kpi' => $record->kpi,
                'assessment' => $record->assessment,
                'assessment_report' => (new SalaryAdjustmentAssessmentReportService())
                    ->buildAssessmentReport($record->assessment),
                'attendance' => (new SalaryAdjustmentEvaluationService())
                    ->getAttendanceSummary((int) $record->employee_id),
                'counseling' => $record->counseling,
                'logs' => SalaryAdjustmentWorkflowService::formatLogs($record->statusLogs),
            ],
                SalaryAdjustmentEvaluationService::formatAdjustmentSnapshot($record),
                (new EmployeeAdjustmentMutasiService())->formatReceiverFields($record),
                (new EmployeeAdjustmentRekapService())->formatForDetail($record)
            ),
        ]);
    }

    public function exportRekap(Request $request)
    {
        $periode = $request->periode ?? date('Y');
        $scope = $request->scope ?? 'all';
        $requestType = trim((string) ($request->request_type ?? ''));

        $query = DB::connection('mysql')
            ->table('salary_adjustment_requests as sar')
            ->leftJoin('master_karyawan as karyawan', 'sar.employee_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('master_karyawan as manager', 'sar.requested_by_id', '=', 'manager.id')
            ->leftJoin('master_karyawan as receiver', 'sar.receiver_manager_id', '=', 'receiver.id')
            ->leftJoin('salary_adjustment_kpi as sk', 'sar.id', '=', 'sk.request_id')
            ->leftJoin('salary_adjustment_assessments as sa', 'sar.id', '=', 'sa.request_id')
            ->leftJoin('employee_adjustment_rekap as ear', 'sar.id', '=', 'ear.request_id')
            ->where('sar.is_active', true)
            ->whereYear('sar.created_at', $periode)
            ->select(
                'sar.no_document',
                'sar.request_type',
                'karyawan.nama_lengkap',
                'karyawan.nik_karyawan',
                'd.nama_divisi',
                'manager.nama_lengkap as manager_nama',
                'receiver.nama_lengkap as receiver_manager_nama',
                'sar.jabatan',
                'ear.jabatan_baru',
                'ear.status_karyawan_lama',
                'ear.status_karyawan_baru',
                'sar.current_gaji_pokok',
                'sar.current_tunjangan_kerja',
                'sar.adjustment_gaji_pokok',
                'sar.adjustment_tunjangan',
                'sar.requested_gaji_pokok',
                'sar.requested_tunjangan_kerja',
                'ear.gaji_lama',
                'ear.gaji_baru',
                'ear.tunjangan_lama',
                'ear.tunjangan_baru',
                'sar.bulan_efektif',
                'sar.tanggal_efektif',
                'sar.tanggal_mulai',
                'sar.tanggal_selesai',
                'sar.tanggal_berakhir_kerja',
                'ear.tgl_berakhir_kontrak_lama',
                'ear.tgl_berakhir_kontrak_baru',
                'sar.scheduled_apply_at',
                'sar.apply_status',
                'sar.status',
                'sar.rejected_stage',
                'sar.reject_reason',
                'sar.applied_at',
                'sar.bapak_approved_at',
                'sar.created_at',
                'sk.total_score_avg as kpi_score',
                'sa.total_score as assessment_score'
            )
            ->orderByDesc('sar.id');

        if ($scope === 'completed') {
            $query->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_COMPLETED);
        } elseif ($scope === 'rejected') {
            $query->where('sar.status', SalaryAdjustmentWorkflowService::STATUS_REJECTED);
        } else {
            $query->whereIn('sar.status', [
                SalaryAdjustmentWorkflowService::STATUS_COMPLETED,
                SalaryAdjustmentWorkflowService::STATUS_REJECTED,
            ]);
        }

        if ($requestType !== '' && EmployeeAdjustmentTypeRegistry::isValid($requestType)) {
            $query->where('sar.request_type', EmployeeAdjustmentTypeRegistry::normalizeType($requestType));
        }

        $rows = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Penyesuaian Karyawan');

        $headers = [
            'No', 'No Dokumen', 'Jenis Permohonan', 'Karyawan', 'NIK', 'Divisi', 'Manager Pengaju',
            'Manager Penerima', 'Jabatan Lama', 'Jabatan Baru', 'Status Karyawan Lama', 'Status Karyawan Baru',
            'Gaji Lama', 'Tunjangan Lama', 'Delta Gaji', 'Delta Tunjangan', 'Target Gaji', 'Target Tunjangan',
            'Gaji Baru (Rekap)', 'Tunjangan Baru (Rekap)', 'Bulan Efektif', 'Tanggal Efektif',
            'Tanggal Mulai', 'Tanggal Selesai', 'Tgl Berakhir Kerja', 'Kontrak Lama', 'Kontrak Baru',
            'Jadwal Apply', 'Status Apply', 'Skor KPI', 'Skor Assessment', 'Status Permohonan',
            'Tahap Ditolak', 'Alasan Ditolak', 'Waiting Approval Final', 'Tanggal Apply', 'Tanggal Pengajuan',
        ];

        foreach ($headers as $colIndex => $header) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, 1, $header);
        }

        $rowIdx = 2;
        foreach ($rows as $index => $row) {
            $sheet->setCellValueByColumnAndRow(1, $rowIdx, $index + 1);
            $sheet->setCellValueByColumnAndRow(2, $rowIdx, $row->no_document);
            $sheet->setCellValueByColumnAndRow(3, $rowIdx, EmployeeAdjustmentTypeRegistry::label($row->request_type));
            $sheet->setCellValueByColumnAndRow(4, $rowIdx, $row->nama_lengkap);
            $sheet->setCellValueByColumnAndRow(5, $rowIdx, $row->nik_karyawan);
            $sheet->setCellValueByColumnAndRow(6, $rowIdx, $row->nama_divisi);
            $sheet->setCellValueByColumnAndRow(7, $rowIdx, $row->manager_nama);
            $sheet->setCellValueByColumnAndRow(8, $rowIdx, $row->receiver_manager_nama);
            $sheet->setCellValueByColumnAndRow(9, $rowIdx, $row->jabatan);
            $sheet->setCellValueByColumnAndRow(10, $rowIdx, $row->jabatan_baru);
            $sheet->setCellValueByColumnAndRow(11, $rowIdx, $row->status_karyawan_lama);
            $sheet->setCellValueByColumnAndRow(12, $rowIdx, $row->status_karyawan_baru);
            $sheet->setCellValueByColumnAndRow(13, $rowIdx, (float) ($row->gaji_lama ?? $row->current_gaji_pokok));
            $sheet->setCellValueByColumnAndRow(14, $rowIdx, (float) ($row->tunjangan_lama ?? $row->current_tunjangan_kerja));
            $sheet->setCellValueByColumnAndRow(15, $rowIdx, (float) ($row->adjustment_gaji_pokok ?? 0));
            $sheet->setCellValueByColumnAndRow(16, $rowIdx, (float) ($row->adjustment_tunjangan ?? 0));
            $sheet->setCellValueByColumnAndRow(17, $rowIdx, (float) $row->requested_gaji_pokok);
            $sheet->setCellValueByColumnAndRow(18, $rowIdx, (float) $row->requested_tunjangan_kerja);
            $sheet->setCellValueByColumnAndRow(19, $rowIdx, $row->gaji_baru !== null ? (float) $row->gaji_baru : null);
            $sheet->setCellValueByColumnAndRow(20, $rowIdx, $row->tunjangan_baru !== null ? (float) $row->tunjangan_baru : null);
            $sheet->setCellValueByColumnAndRow(21, $rowIdx, $row->bulan_efektif);
            $sheet->setCellValueByColumnAndRow(22, $rowIdx, $row->tanggal_efektif);
            $sheet->setCellValueByColumnAndRow(23, $rowIdx, $row->tanggal_mulai);
            $sheet->setCellValueByColumnAndRow(24, $rowIdx, $row->tanggal_selesai);
            $sheet->setCellValueByColumnAndRow(25, $rowIdx, $row->tanggal_berakhir_kerja);
            $sheet->setCellValueByColumnAndRow(26, $rowIdx, $row->tgl_berakhir_kontrak_lama);
            $sheet->setCellValueByColumnAndRow(27, $rowIdx, $row->tgl_berakhir_kontrak_baru);
            $sheet->setCellValueByColumnAndRow(28, $rowIdx, $row->scheduled_apply_at);
            $sheet->setCellValueByColumnAndRow(29, $rowIdx, EmployeeAdjustmentWorkflowResolver::applyStatusLabel($row->apply_status));
            $sheet->setCellValueByColumnAndRow(30, $rowIdx, $row->kpi_score);
            $sheet->setCellValueByColumnAndRow(31, $rowIdx, $row->assessment_score);
            $sheet->setCellValueByColumnAndRow(32, $rowIdx, SalaryAdjustmentWorkflowService::statusLabel($row->status));
            $sheet->setCellValueByColumnAndRow(33, $rowIdx, SalaryAdjustmentWorkflowService::statusLabel($row->rejected_stage));
            $sheet->setCellValueByColumnAndRow(34, $rowIdx, $row->reject_reason);
            $sheet->setCellValueByColumnAndRow(35, $rowIdx, $row->bapak_approved_at);
            $sheet->setCellValueByColumnAndRow(36, $rowIdx, $row->applied_at);
            $sheet->setCellValueByColumnAndRow(37, $rowIdx, $row->created_at);
            $rowIdx++;
        }

        $lastCol = count($headers);
        $lastRow = max(1, $rowIdx - 1);
        $sheet->getStyleByColumnAndRow(1, 1, $lastCol, $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        foreach (range(1, $lastCol) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $scopeLabel = $scope === 'completed' ? 'Selesai' : ($scope === 'rejected' ? 'Ditolak' : 'All');
        $typeLabel = $requestType !== '' && EmployeeAdjustmentTypeRegistry::isValid($requestType)
            ? preg_replace('/[^A-Za-z0-9_-]+/', '_', EmployeeAdjustmentTypeRegistry::label($requestType))
            : 'SemuaJenis';
        $fileName = "Rekap_Penyesuaian_Karyawan_{$typeLabel}_{$scopeLabel}_{$periode}.xlsx";

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    public function listHrdCounselors(Request $request)
    {
        $rows = MasterKaryawan::query()
            ->leftJoin('master_divisi as d', 'master_karyawan.id_department', '=', 'd.id')
            ->where('master_karyawan.is_active', true)
            ->where(function ($query) {
                $query->whereRaw('UPPER(TRIM(d.nama_divisi)) = ?', ['HRD'])
                    ->orWhereRaw('UPPER(TRIM(master_karyawan.department)) = ?', ['HRD']);
            })
            ->orderBy('master_karyawan.nama_lengkap')
            ->get([
                'master_karyawan.id',
                'master_karyawan.nik_karyawan',
                'master_karyawan.nama_lengkap',
                'd.nama_divisi',
            ]);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function scheduleCounseling(Request $request)
    {
        $scheduledDate = trim((string) ($request->scheduled_date ?? ''));
        $scheduledTime = trim((string) ($request->scheduled_time ?? ''));
        $location = trim((string) ($request->location ?? ''));
        $counselorName = trim((string) ($request->counselor_name ?? ''));
        $description = trim((string) ($request->description ?? ''));

        if ($scheduledDate === '' || $scheduledTime === '') {
            return response()->json(['success' => false, 'message' => 'Tanggal dan waktu konseling wajib diisi'], 422);
        }

        if ($location === '') {
            return response()->json(['success' => false, 'message' => 'Lokasi / ruangan konseling wajib diisi'], 422);
        }

        if ($counselorName === '') {
            return response()->json(['success' => false, 'message' => 'Nama konselor wajib dipilih'], 422);
        }

        $normalizedType = 'Offline';

        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $workflowResolver = new EmployeeAdjustmentWorkflowResolver();
        $assessmentService = app(SalaryAdjustmentAssessmentService::class);
        $skippableAssessmentStatuses = EmployeeAdjustmentWorkflowResolver::assessmentSkippableStatuses();

        if (in_array($record->status, $skippableAssessmentStatuses, true)) {
            try {
                $assessmentService->deactivateActiveAssessmentForSkip($record);
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            }
        } elseif ($record->status !== SalaryAdjustmentWorkflowService::STATUS_ASSESSMENT_COMPLETED) {
            return response()->json(['success' => false, 'message' => 'Permohonan belum siap dijadwalkan konseling'], 400);
        }

        if (!$workflowResolver->requiresCounseling($record->request_type)) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis permohonan ini tidak memerlukan konseling',
            ], 400);
        }

        if (SalaryAdjustmentCounseling::where('request_id', $record->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Konseling sudah dijadwalkan sebelumnya'], 400);
        }

        DB::connection('mysql')->beginTransaction();
        try {
            $from = $record->status;

            SalaryAdjustmentCounseling::create([
                'request_id' => $record->id,
                'employee_id' => $record->employee_id,
                'scheduled_date' => $scheduledDate,
                'scheduled_time' => $scheduledTime,
                'type' => $normalizedType,
                'location' => $location,
                'meeting_link' => null,
                'counselor_name' => $counselorName ?: null,
                'description' => $description ?: null,
                'status' => 'scheduled',
                'scheduled_by' => $this->karyawan,
                'scheduled_at' => Carbon::now(),
            ]);

            $record->status = SalaryAdjustmentWorkflowService::STATUS_COUNSELING_SCHEDULED;
            $record->updated_by = $this->karyawan;
            $record->save();

            $scheduleNotes = "Konseling dijadwalkan {$scheduledDate} {$scheduledTime} ({$normalizedType})";
            if (in_array($from, $skippableAssessmentStatuses, true)) {
                $scheduleNotes = 'Assessment dilewati — ' . $scheduleNotes;
            }

            SalaryAdjustmentLogService::log(
                $record->id,
                $from,
                $record->status,
                in_array($from, $skippableAssessmentStatuses, true) ? 'skip_assessment' : 'counseling_schedule',
                $this->user_id,
                $this->karyawan,
                $scheduleNotes
            );

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Jadwal konseling berhasil dibuat',
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function indexRekap(Request $request, string $status)
    {
        $periode = $request->periode ?? date('Y');

        $query = DB::connection('mysql')
            ->table('salary_adjustment_requests as sar')
            ->leftJoin('master_karyawan as karyawan', 'sar.employee_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('master_karyawan as manager', 'sar.requested_by_id', '=', 'manager.id')
            ->leftJoin('employee_adjustment_rekap as ear', 'sar.id', '=', 'ear.request_id')
            ->where('sar.is_active', true)
            ->whereYear('sar.created_at', $periode)
            ->where('sar.status', $status)
            ->select(
                'sar.id',
                'sar.no_document',
                'sar.request_type',
                'sar.employee_id',
                'sar.jabatan',
                'sar.bulan_efektif',
                'sar.tanggal_efektif',
                'sar.scheduled_apply_at',
                'sar.apply_status',
                'sar.status',
                'sar.rejected_stage',
                'sar.reject_reason',
                'sar.applied_at',
                'sar.created_at',
                'karyawan.nama_lengkap',
                'd.nama_divisi',
                'manager.nama_lengkap as manager_nama',
                'ear.jabatan_baru',
                'ear.status_karyawan_baru'
            )
            ->orderByDesc('sar.id');

        return $this->applyHrdDatatablesFilters(
            $this->decorateRequestTypeColumn(
                Datatables::of($query)
                    ->addColumn('status_label', function ($row) {
                        return SalaryAdjustmentWorkflowService::statusLabel($row->status);
                    })
                    ->addColumn('apply_status_label', function ($row) {
                        return EmployeeAdjustmentWorkflowResolver::applyStatusLabel($row->apply_status);
                    })
            )
        )->make(true);
    }

    private function indexByHrdTab(Request $request, string $tab, bool $withAssessmentLink)
    {
        $periode = $request->periode ?? date('Y');
        $statuses = SalaryAdjustmentWorkflowService::statusesForHrdTab($tab);

        $query = DB::connection('mysql')
            ->table('salary_adjustment_requests as sar')
            ->leftJoin('master_karyawan as karyawan', 'sar.employee_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('master_karyawan as manager', 'sar.requested_by_id', '=', 'manager.id')
            ->leftJoin('salary_adjustment_assessments as sa', 'sar.id', '=', 'sa.request_id')
            ->where('sar.is_active', true)
            ->whereYear('sar.created_at', $periode)
            ->whereIn('sar.status', $statuses)
            ->select(
                'sar.id',
                'sar.no_document',
                'sar.request_type',
                'sar.employee_id',
                'sar.jabatan',
                'sar.bulan_efektif',
                'sar.status',
                'sar.created_at',
                'karyawan.nama_lengkap',
                'd.nama_divisi',
                'manager.nama_lengkap as manager_nama',
                'sa.token as assessment_token',
                'sa.link_url',
                'sa.is_link_active',
                'sa.attempt_status',
                'sa.total_score',
                'sa.completed_at as assessment_completed_at'
            )
            ->orderByDesc('sar.id');

        $dt = $this->decorateRequestTypeColumn(
            Datatables::of($query)
                ->addColumn('status_label', function ($row) {
                    return SalaryAdjustmentWorkflowService::statusLabel($row->status);
                })
        );

        if ($withAssessmentLink) {
            $dt->editColumn('link_url', function ($row) {
                if (empty($row->assessment_token)) {
                    return $row->link_url;
                }

                return FrontendPublicUrl::buildAssessmentLink($row->assessment_token);
            });

            $dt->addColumn('can_generate_link', function ($row) {
                if (!EmployeeAdjustmentTypeRegistry::requiresAssessment($row->request_type ?? null)) {
                    return false;
                }

                return in_array($row->status, [
                    SalaryAdjustmentWorkflowService::STATUS_HRD_PROCESSING,
                    SalaryAdjustmentWorkflowService::STATUS_WAITING_ASSESSMENT,
                ], true)
                    && $row->attempt_status !== 'completed'
                    && empty($row->link_url);
            });

            $dt->addColumn('requires_counseling', function ($row) {
                return EmployeeAdjustmentTypeRegistry::requiresCounseling($row->request_type ?? null);
            });

            $dt->addColumn('can_skip_assessment', function ($row) {
                return in_array($row->status, EmployeeAdjustmentWorkflowResolver::assessmentSkippableStatuses(), true)
                    && $row->attempt_status !== 'completed';
            });
        }

        return $this->applyHrdDatatablesFilters($dt)->make(true);
    }

    private function decorateRequestTypeColumn($datatables)
    {
        return $datatables->addColumn('request_type_label', function ($row) {
            return EmployeeAdjustmentTypeRegistry::label($row->request_type ?? EmployeeAdjustmentTypeRegistry::TYPE_PENYESUAIAN_GAJI);
        });
    }

    private function applyHrdDatatablesFilters($datatables)
    {
        return $datatables
            ->filterColumn('request_type_label', function ($query, $keyword) {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('sar.request_type', 'like', "%{$keyword}%");
                    foreach (EmployeeAdjustmentTypeRegistry::all() as $type => $definition) {
                        if (stripos($definition['label'], $keyword) !== false) {
                            $sub->orWhere('sar.request_type', $type);
                        }
                    }
                });
            })
            ->filterColumn('no_document', function ($query, $keyword) {
                $query->where('sar.no_document', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_lengkap', function ($query, $keyword) {
                $query->where('karyawan.nama_lengkap', 'like', "%{$keyword}%");
            })
            ->filterColumn('manager_nama', function ($query, $keyword) {
                $query->where('manager.nama_lengkap', 'like', "%{$keyword}%");
            })
            ->filterColumn('jabatan', function ($query, $keyword) {
                $query->where('sar.jabatan', 'like', "%{$keyword}%");
            })
            ->filterColumn('bulan_efektif', function ($query, $keyword) {
                $query->where('sar.bulan_efektif', 'like', "%{$keyword}%");
            })
            ->filterColumn('status_label', function ($query, $keyword) {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('sar.status', 'like', "%{$keyword}%");
                    foreach (SalaryAdjustmentWorkflowService::STATUS_LABELS as $status => $label) {
                        if (stripos($label, $keyword) !== false) {
                            $sub->orWhere('sar.status', $status);
                        }
                    }
                });
            })
            ->filterColumn('nama_divisi', function ($query, $keyword) {
                $query->where('d.nama_divisi', 'like', "%{$keyword}%");
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('sar.created_at', 'like', "%{$keyword}%");
            })
            ->filterColumn('reject_reason', function ($query, $keyword) {
                $query->where('sar.reject_reason', 'like', "%{$keyword}%");
            })
            ->filterColumn('applied_at', function ($query, $keyword) {
                $query->where('sar.applied_at', 'like', "%{$keyword}%");
            })
            ->filterColumn('apply_status_label', function ($query, $keyword) {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('sar.apply_status', 'like', "%{$keyword}%");
                    foreach (EmployeeAdjustmentWorkflowResolver::APPLY_STATUS_LABELS as $status => $label) {
                        if (stripos($label, $keyword) !== false) {
                            $sub->orWhere('sar.apply_status', $status);
                        }
                    }
                });
            })
            ->filterColumn('jabatan_baru', function ($query, $keyword) {
                $query->where('ear.jabatan_baru', 'like', "%{$keyword}%");
            })
            ->filterColumn('scheduled_apply_at', function ($query, $keyword) {
                $query->where('sar.scheduled_apply_at', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal_efektif', function ($query, $keyword) {
                $query->where('sar.tanggal_efektif', 'like', "%{$keyword}%");
            })
            ->filterColumn('total_score', function ($query, $keyword) {
                $query->where('sa.total_score', 'like', "%{$keyword}%");
            })
            ->filterColumn('attempt_status', function ($query, $keyword) {
                $query->where('sa.attempt_status', 'like', "%{$keyword}%");
            })
            ->filterColumn('assessment_completed_at', function ($query, $keyword) {
                $query->where('sa.completed_at', 'like', "%{$keyword}%");
            })
            ->orderColumn('nama_lengkap', fn ($query, $order) => $query->orderBy('karyawan.nama_lengkap', $order))
            ->orderColumn('manager_nama', fn ($query, $order) => $query->orderBy('manager.nama_lengkap', $order))
            ->orderColumn('nama_divisi', fn ($query, $order) => $query->orderBy('d.nama_divisi', $order))
            ->orderColumn('status_label', fn ($query, $order) => $query->orderBy('sar.status', $order));
    }

    public function skipAssessment(Request $request, SalaryAdjustmentAssessmentService $service)
    {
        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $target = strtolower(trim((string) ($request->input('target') ?? 'next')));
        if (!in_array($target, ['next', 'final'], true)) {
            return response()->json(['success' => false, 'message' => 'Target skip assessment tidak valid'], 422);
        }

        try {
            $result = $service->skipAssessment($record, $target, $this->karyawan, $this->user_id);

            $message = $result['next_status'] === SalaryAdjustmentWorkflowService::STATUS_FINAL_EVALUATION
                ? 'Assessment dilewati — permohonan lanjut ke Evaluasi Final'
                : 'Assessment dilewati — permohonan siap dijadwalkan konseling';

            if ($target === 'final') {
                $message = 'Assessment & konseling dilewati — permohonan lanjut ke Evaluasi Final';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function process(Request $request)
    {
        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if (!in_array($record->status, SalaryAdjustmentWorkflowService::hrdProcessableStatuses(), true)) {
            return response()->json(['success' => false, 'message' => 'Permohonan tidak dapat diproses pada status ini'], 400);
        }

        $workflowResolver = new EmployeeAdjustmentWorkflowResolver();
        $nextStatus = $workflowResolver->resolvePostProcessStatus($record);
        $skipAssessment = $nextStatus === SalaryAdjustmentWorkflowService::STATUS_FINAL_EVALUATION;

        DB::connection('mysql')->beginTransaction();
        try {
            $from = $record->status;
            $record->status = $nextStatus;
            $record->processed_by = $this->karyawan;
            $record->processed_at = Carbon::now();
            $record->updated_by = $this->karyawan;
            $record->save();

            $logNotes = trim((string) ($request->notes ?? '')) ?: $workflowResolver->postProcessLogNotes($record);

            SalaryAdjustmentLogService::log(
                $record->id,
                $from,
                $record->status,
                'process',
                $this->user_id,
                $this->karyawan,
                $logNotes,
                ['skip_assessment' => $skipAssessment]
            );

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'message' => $skipAssessment
                    ? 'Permohonan diproses — langsung ke Evaluasi Final (lewati assessment & konseling)'
                    : 'Permohonan berhasil diproses',
                'skip_assessment' => $skipAssessment,
                'next_status' => $nextStatus,
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request)
    {
        $reason = trim((string) ($request->reject_reason ?? $request->keterangan ?? ''));
        if ($reason === '') {
            return response()->json(['success' => false, 'message' => 'Alasan penolakan wajib diisi'], 400);
        }

        $record = SalaryAdjustmentRequest::find((int) $request->id);
        if (!$record || !$record->is_active) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        if (!in_array($record->status, SalaryAdjustmentWorkflowService::hrdProcessableStatuses(), true)) {
            return response()->json(['success' => false, 'message' => 'Permohonan tidak dapat ditolak pada status ini'], 400);
        }

        DB::connection('mysql')->beginTransaction();
        try {
            $from = $record->status;
            $record->status = SalaryAdjustmentWorkflowService::STATUS_REJECTED;
            $record->rejected_stage = $from;
            $record->reject_reason = $reason;
            $record->rejected_by = $this->karyawan;
            $record->rejected_at = Carbon::now();
            $record->updated_by = $this->karyawan;
            $record->save();

            SalaryAdjustmentLogService::log(
                $record->id,
                $from,
                $record->status,
                'reject',
                $this->user_id,
                $this->karyawan,
                $reason
            );

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Permohonan berhasil ditolak',
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function ensureSubmittedSnapshot(SalaryAdjustmentRequest $record): void
    {
        if ($record->submitted_adjustment_gaji_pokok !== null) {
            return;
        }

        $record->submitted_adjustment_gaji_pokok = $record->adjustment_gaji_pokok;
        $record->submitted_adjustment_tunjangan = $record->adjustment_tunjangan;
        $record->submitted_requested_gaji_pokok = $record->requested_gaji_pokok;
        $record->submitted_requested_tunjangan_kerja = $record->requested_tunjangan_kerja;
    }

    private function parseAmount($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return max(0, (float) $value);
        }

        $normalized = preg_replace('/[^\d]/', '', (string) $value);

        return max(0, (float) ($normalized ?: 0));
    }
}
