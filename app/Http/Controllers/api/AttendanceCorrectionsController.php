<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Hr\HrRequest;
use App\Models\IntilabInternal\AttendanceCorrections;
use App\Models\MasterKaryawan;
use App\Services\Hr\ApprovalService;
use App\Services\Hr\AttendanceCorrectionAtasanQueueService;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\HrTableMode;
use App\Services\Hr\Greatday\AttendanceCorrectionAbsensiApplyService;
use App\Services\Hr\LegacyHrMirror;
use App\Services\Hr\WorkflowStatus;
use App\Support\Greatday\KaryawanPresentation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionsController extends Controller
{
    public function index(Request $request)
    {
        $data = AttendanceCorrections::with(['karyawan.jabatan'])->where('is_active', 1);

        if ($request->has('status')) {
            $data->where('status', $request->status);
        }

        return datatables()->of($data)
            ->addColumn('atasan_steps', function ($row) {
                return app(AttendanceCorrectionAtasanQueueService::class)->atasanStepsForLegacyRow($row);
            })
            ->addColumn('menunggu_atasan', function ($row) {
                return app(AttendanceCorrectionAtasanQueueService::class)->waitingAtasanNameForLegacyRow($row);
            })
            ->addColumn('jabatan_karyawan', function ($row) {
                $jabatan = optional($row->karyawan)->jabatan;
                if (is_object($jabatan)) {
                    return $jabatan->nama_jabatan ?: '-';
                }

                return $jabatan ?: '-';
            })
            ->filterColumn('jabatan_karyawan', function ($query, $keyword) {
                $ids = MasterKaryawan::query()
                    ->whereHas('jabatan', function ($jabatanQuery) use ($keyword) {
                        $jabatanQuery->where('nama_jabatan', 'like', "%{$keyword}%");
                    })
                    ->pluck('id');

                $query->whereIn('employee_id', $ids->isEmpty() ? [0] : $ids);
            })
            ->filterColumn('karyawan', function ($query, $keyword) {
                $ids = MasterKaryawan::query()
                    ->where('nama_lengkap', 'like', "%{$keyword}%")
                    ->pluck('id');

                $query->where(function ($sub) use ($keyword, $ids) {
                    if ($ids->isNotEmpty()) {
                        $sub->whereIn('employee_id', $ids);
                    }
                    $sub->orWhere('created_by', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('jabatan_karyawan', function ($query, $keyword) {
                $ids = MasterKaryawan::query()
                    ->where(function ($q) use ($keyword) {
                        $q->where('jabatan', 'like', "%{$keyword}%")
                            ->orWhereIn('id_jabatan', function ($sub) use ($keyword) {
                                $sub->select('id')
                                    ->from('master_jabatan')
                                    ->where('nama_jabatan', 'like', "%{$keyword}%");
                            });
                    })
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    $query->whereRaw('0 = 1');

                    return;
                }

                $query->whereIn('employee_id', $ids);
            })
            ->filterColumn('menunggu_atasan', function ($query, $keyword) {
                $employeeIds = app(AttendanceCorrectionAtasanQueueService::class)->employeeIdsWaitingOnAtasanNamed($keyword);
                $query->whereIn('employee_id', $employeeIds);
            })
            ->filterColumn('atasan_steps', function ($query, $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword === '') {
                    return;
                }
                $query->where('approved_atasan_at', 'like', "%{$keyword}%");
            })
            ->filterColumn('approved_atasan_by', function ($query, $keyword) {
                $query->where('approved_atasan_by', 'like', "%{$keyword}%");
            })
            ->filterColumn('approved_hrd_by', function ($query, $keyword) {
                $query->where('approved_hrd_by', 'like', "%{$keyword}%");
            })
            ->filterColumn('approved_hrd_at', function ($query, $keyword) {
                $query->where('approved_hrd_at', 'like', "%{$keyword}%");
            })
            ->make(true);
    }

    public function tabCounts(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'approved_atasan' => AttendanceCorrections::where('is_active', 1)->where('status', WorkflowStatus::APPROVED_ATASAN)->count(),
                'pending' => AttendanceCorrections::where('is_active', 1)->where('status', WorkflowStatus::PENDING)->count(),
                'approved_hrd' => AttendanceCorrections::where('is_active', 1)->where('status', WorkflowStatus::APPROVED_HRD)->count(),
                'rejected_hrd' => AttendanceCorrections::where('is_active', 1)->where('status', WorkflowStatus::REJECTED_HRD)->count(),
            ],
        ]);
    }

    public function approve(Request $request)
    {
        $apiId = (int) $request->id;
        $hrResponse = $this->approveOnHrTables($apiId);
        if ($hrResponse !== null) {
            return $hrResponse;
        }

        DB::beginTransaction();
        try {
            $correction = AttendanceCorrections::where('is_active', 1)->find($apiId);
            if (!$correction) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            app(AttendanceCorrectionAbsensiApplyService::class)->apply(
                (int) $correction->employee_id,
                (string) $correction->date,
                (string) $correction->type,
                (string) $correction->time
            );

            $hrdName = $this->karyawan ?? 'HRD';
            $correction->approved_hrd_by = $hrdName;
            $correction->approved_hrd_at = date('Y-m-d H:i:s');
            $correction->status = WorkflowStatus::APPROVED_HRD;
            $correction->save();

            DB::commit();

            return response()->json(['message' => 'Absensi berhasil di-approve & diupdate'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    public function reject(Request $request)
    {
        $reason = trim((string) ($request->reason ?? $request->keterangan ?? ''));
        if ($reason === '') {
            return response()->json(['message' => 'Alasan penolakan wajib diisi'], 422);
        }

        $apiId = (int) $request->id;
        $hrResponse = $this->rejectOnHrTables($apiId, $reason);
        if ($hrResponse !== null) {
            return $hrResponse;
        }

        try {
            $correction = AttendanceCorrections::where('is_active', 1)->find($apiId);
            if (!$correction) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $hrdName = $this->karyawan ?? 'HRD';
            $correction->rejected_hrd_by = $hrdName;
            $correction->rejected_hrd_at = date('Y-m-d H:i:s');
            $correction->reject_hrd_reason = $reason;
            $correction->status = WorkflowStatus::REJECTED_HRD;
            $correction->save();

            return response()->json(['message' => 'Koreksi absensi berhasil di-reject'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse|null null = lanjut legacy apps saja
     */
    private function approveOnHrTables(int $apiId)
    {
        $hrRequest = HrRequestResolver::findByPortalSliceId(HrRequest::TYPE_ATTENDANCE_CORRECTION, $apiId);
        if (!$hrRequest) {
            return HrTableMode::portalReadsHrTables()
                ? response()->json(['message' => 'Data koreksi absensi tidak ditemukan di HR'], 404)
                : null;
        }

        if ($hrRequest->status !== WorkflowStatus::APPROVED_ATASAN) {
            return response()->json([
                'message' => 'Menunggu persetujuan atasan terlebih dahulu',
            ], 422);
        }

        $approver = MasterKaryawan::find($this->user_id);
        if (!$approver) {
            return response()->json(['message' => 'Data karyawan HRD tidak ditemukan'], 403);
        }

        DB::beginTransaction();
        try {
            $hrRequest->loadMissing('attendanceCorrectionDetail');
            $detail = $hrRequest->attendanceCorrectionDetail;
            if ($detail) {
                app(AttendanceCorrectionAbsensiApplyService::class)->apply(
                    (int) $hrRequest->karyawan_id,
                    (string) $detail->correction_date,
                    (string) $detail->correction_type,
                    (string) $detail->correction_time
                );
            }

            app(ApprovalService::class)->approveHrd($hrRequest, $approver, $this->karyawan);
            app(LegacyHrMirror::class)->syncPortalHrdDecision($hrRequest->fresh());

            DB::commit();

            return response()->json(['message' => 'Absensi berhasil di-approve & diupdate'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse|null null = lanjut legacy apps saja
     */
    private function rejectOnHrTables(int $apiId, string $reason)
    {
        $hrRequest = HrRequestResolver::findByPortalSliceId(HrRequest::TYPE_ATTENDANCE_CORRECTION, $apiId);
        if (!$hrRequest) {
            return HrTableMode::portalReadsHrTables()
                ? response()->json(['message' => 'Data koreksi absensi tidak ditemukan di HR'], 404)
                : null;
        }

        $approver = MasterKaryawan::find($this->user_id);
        if (!$approver) {
            return response()->json(['message' => 'Data karyawan HRD tidak ditemukan'], 403);
        }

        app(ApprovalService::class)->rejectHrd($hrRequest, $approver, $reason, $this->karyawan);
        app(LegacyHrMirror::class)->syncPortalHrdDecision($hrRequest->fresh());

        return response()->json(['message' => 'Koreksi absensi berhasil di-reject'], 200);
    }

}
