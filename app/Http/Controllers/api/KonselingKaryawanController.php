<?php

namespace App\Http\Controllers\api;

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
use App\Services\SalaryAdjustmentEvaluationService;
use App\Services\SalaryAdjustmentLogService;
use App\Services\SalaryAdjustmentWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class KonselingKaryawanController extends Controller
{
    public function tabCounts(Request $request)
    {
        $periode = $request->periode ?? date('Y');

        $base = DB::connection('mysql')
            ->table('salary_adjustment_counselings as sac')
            ->join('salary_adjustment_requests as sar', 'sac.request_id', '=', 'sar.id')
            ->where('sar.is_active', true)
            ->whereYear('sar.created_at', $periode);

        return response()->json([
            'success' => true,
            'data' => [
                'scheduled' => (clone $base)->where('sac.status', 'scheduled')->count(),
                'completed' => (clone $base)->where('sac.status', 'completed')->count(),
            ],
        ]);
    }

    public function indexScheduled(Request $request)
    {
        return $this->indexByStatus($request, 'scheduled');
    }

    public function indexCompleted(Request $request)
    {
        return $this->indexByStatus($request, 'completed');
    }

    public function show(Request $request)
    {
        $counseling = SalaryAdjustmentCounseling::with([
            'request.kpi.items',
            'request.assessment.sessions',
            'request.statusLogs',
            'request.newJabatan',
            'request.rekap',
            'request.receiverManager',
        ])->find((int) $request->id);

        if (!$counseling) {
            return response()->json(['success' => false, 'message' => 'Data konseling tidak ditemukan'], 404);
        }

        $requestRecord = $counseling->request;
        if (!$requestRecord || !$requestRecord->is_active) {
            return response()->json(['success' => false, 'message' => 'Permohonan tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge(
                $this->buildRequestDetailPayload($requestRecord, (int) $counseling->employee_id),
                [
                    'counseling_id' => $counseling->id,
                    'counseling' => $counseling,
                ]
            ),
        ]);
    }

    private function buildRequestDetailPayload(SalaryAdjustmentRequest $record, int $employeeId): array
    {
        $employee = MasterKaryawan::with('jabatan')->find($employeeId);
        $manager = MasterKaryawan::find($record->requested_by_id);
        $namaJabatan = KaryawanProfileService::resolveJabatan($employee);

        return array_merge([
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
            'reject_reason' => $record->reject_reason,
            'status' => $record->status,
            'status_label' => SalaryAdjustmentWorkflowService::statusLabel($record->status),
            'workflow_meta' => (new EmployeeAdjustmentWorkflowResolver())->buildWorkflowMeta($record),
            'kpi' => $record->kpi,
            'assessment' => $record->assessment,
            'assessment_report' => (new SalaryAdjustmentAssessmentReportService())
                ->buildAssessmentReport($record->assessment),
            'attendance' => (new SalaryAdjustmentEvaluationService())
                ->getAttendanceSummary($employeeId),
            'logs' => SalaryAdjustmentWorkflowService::formatLogs($record->statusLogs),
        ],
            SalaryAdjustmentEvaluationService::formatAdjustmentSnapshot($record),
            (new EmployeeAdjustmentMutasiService())->formatReceiverFields($record),
            (new EmployeeAdjustmentRekapService())->formatForDetail($record)
        );
    }

    public function complete(Request $request)
    {
        $notes = trim((string) ($request->result_notes ?? $request->notes ?? ''));
        if ($notes === '') {
            return response()->json(['success' => false, 'message' => 'Catatan hasil konseling wajib diisi'], 422);
        }

        $counseling = SalaryAdjustmentCounseling::find((int) $request->id);
        if (!$counseling) {
            return response()->json(['success' => false, 'message' => 'Data konseling tidak ditemukan'], 404);
        }

        if ($counseling->status !== 'scheduled') {
            return response()->json(['success' => false, 'message' => 'Konseling sudah diproses sebelumnya'], 400);
        }

        $requestRecord = SalaryAdjustmentRequest::find($counseling->request_id);
        if (!$requestRecord || !$requestRecord->is_active) {
            return response()->json(['success' => false, 'message' => 'Permohonan tidak ditemukan'], 404);
        }

        if ($requestRecord->status !== SalaryAdjustmentWorkflowService::STATUS_COUNSELING_SCHEDULED) {
            return response()->json(['success' => false, 'message' => 'Status permohonan tidak valid untuk menyelesaikan konseling'], 400);
        }

        DB::connection('mysql')->beginTransaction();
        try {
            $from = $requestRecord->status;

            $counseling->status = 'completed';
            $counseling->result_notes = $notes;
            $counseling->completed_by = $this->karyawan;
            $counseling->completed_at = Carbon::now();
            $counseling->save();

            $requestRecord->status = SalaryAdjustmentWorkflowService::STATUS_FINAL_EVALUATION;
            $requestRecord->updated_by = $this->karyawan;
            $requestRecord->save();

            SalaryAdjustmentLogService::log(
                $requestRecord->id,
                $from,
                $requestRecord->status,
                'counseling_complete',
                $this->user_id,
                $this->karyawan,
                $notes
            );

            DB::connection('mysql')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Konseling berhasil diselesaikan',
            ]);
        } catch (\Throwable $e) {
            DB::connection('mysql')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function indexByStatus(Request $request, string $status)
    {
        $periode = $request->periode ?? date('Y');

        $query = DB::connection('mysql')
            ->table('salary_adjustment_counselings as sac')
            ->join('salary_adjustment_requests as sar', 'sac.request_id', '=', 'sar.id')
            ->leftJoin('master_karyawan as karyawan', 'sac.employee_id', '=', 'karyawan.id')
            ->leftJoin('master_divisi as d', 'karyawan.id_department', '=', 'd.id')
            ->leftJoin('master_karyawan as manager', 'sar.requested_by_id', '=', 'manager.id')
            ->where('sar.is_active', true)
            ->where('sac.status', $status)
            ->whereYear('sar.created_at', $periode)
            ->select(
                'sac.id',
                'sac.request_id',
                'sac.employee_id',
                'sac.scheduled_date',
                'sac.scheduled_time',
                'sac.type',
                'sac.location',
                'sac.meeting_link',
                'sac.counselor_name',
                'sac.status',
                'sac.completed_at',
                'sar.no_document',
                'sar.jabatan',
                'sar.status as request_status',
                'karyawan.nama_lengkap',
                'd.nama_divisi',
                'manager.nama_lengkap as manager_nama'
            )
            ->orderByDesc('sac.scheduled_date')
            ->orderByDesc('sac.id');

        return Datatables::of($query)
            ->addColumn('status_label', function ($row) {
                return $row->status === 'completed' ? 'Selesai' : 'Dijadwalkan';
            })
            ->addColumn('type_label', function ($row) {
                return $row->type ?: '-';
            })
            ->addColumn('schedule_label', function ($row) {
                $date = $row->scheduled_date ? date('d/m/Y', strtotime($row->scheduled_date)) : '-';
                $time = $row->scheduled_time ? substr((string) $row->scheduled_time, 0, 5) : '';

                return trim($date . ($time ? " {$time}" : ''));
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
            ->filterColumn('type_label', function ($query, $keyword) {
                $query->where('sac.type', 'like', "%{$keyword}%");
            })
            ->filterColumn('schedule_label', function ($query, $keyword) {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('sac.scheduled_date', 'like', "%{$keyword}%")
                        ->orWhere('sac.scheduled_time', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('counselor_name', function ($query, $keyword) {
                $query->where('sac.counselor_name', 'like', "%{$keyword}%");
            })
            ->filterColumn('completed_at', function ($query, $keyword) {
                $query->where('sac.completed_at', 'like', "%{$keyword}%");
            })
            ->orderColumn('no_document', fn ($query, $order) => $query->orderBy('sar.no_document', $order))
            ->orderColumn('nama_lengkap', fn ($query, $order) => $query->orderBy('karyawan.nama_lengkap', $order))
            ->orderColumn('manager_nama', fn ($query, $order) => $query->orderBy('manager.nama_lengkap', $order))
            ->orderColumn('type_label', fn ($query, $order) => $query->orderBy('sac.type', $order))
            ->orderColumn('schedule_label', fn ($query, $order) => $query->orderBy('sac.scheduled_date', $order))
            ->orderColumn('counselor_name', fn ($query, $order) => $query->orderBy('sac.counselor_name', $order))
            ->orderColumn('completed_at', fn ($query, $order) => $query->orderBy('sac.completed_at', $order))
            ->make(true);
    }
}
