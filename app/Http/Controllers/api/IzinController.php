<?php

namespace App\Http\Controllers\api;

use App\Models\Hr\HrRequest;
use App\Models\LeaveRequest;
use App\Models\MasterKaryawan;
use App\Models\PermissionRequest;
use App\Http\Controllers\Controller;
use App\Services\Hr\ApprovalService;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\HrTableMode;
use App\Services\Hr\LegacyHrMirror;
use App\Services\Hr\Portal\PortalHrdIzinDatatableQuery;
use App\Services\Hr\PortalHrSync;
use App\Services\Hr\WorkflowStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class IzinController extends Controller
{
    public function indexUnprocessed(Request $request)
    {
        if (HrTableMode::portalReadsHrTables()) {
            $data = app(PortalHrdIzinDatatableQuery::class)->unprocessed((int) $request->periode);

            return Datatables::of($data)->make(true);
        }

        $permissions = PermissionRequest::query()->toBase()
            ->from('intilab_apps.permission_requests as pr')
            ->leftJoin('master_karyawan as u', 'pr.employee_id', '=', 'u.id')
            ->leftJoin('master_divisi as d', 'u.id_department', '=', 'd.id')
            ->select(
                DB::raw("CONCAT('PR-', pr.id) as id"),
                'pr.no_document',
                'd.nama_divisi',
                DB::raw('NULL as special_leave_name'),
                DB::raw('CASE
            WHEN pr.status = "Approved Atasan" THEN "APPROVED ATASAN"
            WHEN pr.status = "Approved HRD" THEN "APPROVED HRD"
            WHEN pr.status = "Rejected Atasan" THEN "REJECTED ATASAN"
            WHEN pr.status = "Rejected HRD" THEN "REJECTED HRD"
            ELSE "WAITING"
        END as status'),
                DB::raw('CASE
            WHEN pr.type = "Event Leave" THEN "kegiatan"
            WHEN pr.type = "Sick Leave" THEN "sakit"
            WHEN pr.type = "Late Arrival" THEN "datang_terlambat"
            ELSE pr.type
        END as type_document'),
                'pr.start_date as tanggal_mulai',
                'pr.end_date as tanggal_selesai',
                'pr.start_time as jam_mulai',
                'pr.end_time as jam_selesai',
                'pr.description as keterangan',
                'pr.approved_atasan_by',
                'pr.approved_atasan_at',
                'pr.approved_hrd_by',
                'pr.approved_hrd_at',
                'pr.rejected_atasan_by',
                'pr.rejected_atasan_at',
                'pr.rejected_hrd_by',
                'pr.rejected_hrd_at',
                'u.nama_lengkap as nama_karyawan',
                'pr.created_by as nama_pengaju',
                'pr.attachment as filename',
                DB::raw('NULL as nama_delegasi'),
                'pr.created_at as diajukan_pada'
            )
            ->whereNull('pr.rejected_atasan_by')
            ->whereNull('pr.rejected_hrd_by')
            ->where('pr.status', 'Approved Atasan')
            ->where(function ($query) {
                 $query->where('u.atasan_langsung', 'NOT LIKE', '%"1"%')
                       ->orWhereNull('u.atasan_langsung');
            })
            ->whereNotNull('pr.approved_atasan_by')
            ->whereNotNull('pr.approved_atasan_at')
            ->whereYear('pr.created_at', $request->periode);

        $data = $permissions->get();

        return Datatables::of($data)->make(true);
    }

    public function indexProcessed(Request $request)
    {
        if (HrTableMode::portalReadsHrTables()) {
            $data = app(PortalHrdIzinDatatableQuery::class)->processed((int) $request->periode);

            return Datatables::of($data)->make(true);
        }

         $permissions = PermissionRequest::query()->toBase()
            ->from('intilab_apps.permission_requests as pr')
            ->leftJoin('master_karyawan as u', 'pr.employee_id', '=', 'u.id')
            ->leftJoin('master_divisi as d', 'u.id_department', '=', 'd.id')
            ->select(
                DB::raw("CONCAT('PR-', pr.id) as id"),
                'pr.no_document',
                'd.nama_divisi',
                DB::raw('NULL as special_leave_name'),
                DB::raw('CASE
            WHEN pr.status = "Approved Atasan" THEN "APPROVED"
            WHEN pr.status = "Approved HRD" THEN "APPROVED HRD"
            WHEN pr.status = "Rejected Atasan" THEN "REJECTED"
            WHEN pr.status = "Rejected HRD" THEN "REJECTED HRD"
            ELSE "WAITING"
        END as status'),
                DB::raw('CASE
            WHEN pr.type = "Event Leave" THEN "kegiatan"
            WHEN pr.type = "Sick Leave" THEN "sakit"
            WHEN pr.type = "Late Arrival" THEN "datang_terlambat"
            ELSE pr.type
        END as type_document'),
                'pr.start_date as tanggal_mulai',
                'pr.end_date as tanggal_selesai',
                'pr.start_time as jam_mulai',
                'pr.end_time as jam_selesai',
                'pr.description as keterangan',
                'pr.approved_atasan_by',
                'pr.approved_atasan_at',
                'pr.approved_hrd_by',
                'pr.approved_hrd_at',
                'pr.rejected_atasan_by',
                'pr.rejected_atasan_at',
                'pr.rejected_hrd_by',
                'pr.rejected_hrd_at',
                'u.nama_lengkap as nama_karyawan',
                'pr.created_by as nama_pengaju',
                'pr.attachment as filename',
                DB::raw('NULL as nama_delegasi'),
                'pr.created_at as diajukan_pada'
            )
            ->whereNotNull('pr.approved_hrd_by')
            ->whereNotNull('pr.approved_hrd_at')
            ->whereNull('pr.rejected_atasan_by')
            ->whereNull('pr.rejected_hrd_by')
            ->whereYear('pr.created_at', $request->periode)
            ->whereNotNull('pr.approved_atasan_by');

        $data = $permissions->get();

        return Datatables::of($data)->make(true);
    }

    public function tabCounts(Request $request)
    {
        $periode = (int) ($request->periode ?? date('Y'));

        if (HrTableMode::portalReadsHrTables()) {
            $query = app(PortalHrdIzinDatatableQuery::class);

            return response()->json([
                'success' => true,
                'data' => [
                    'on_progress' => $query->unprocessed($periode)->count(),
                    'processed' => $query->processed($periode)->count(),
                ],
            ]);
        }

        $onProgressPermissions = PermissionRequest::query()->toBase()
            ->from('intilab_apps.permission_requests as pr')
            ->leftJoin('master_karyawan as u', 'pr.employee_id', '=', 'u.id')
            ->whereNull('pr.rejected_atasan_by')
            ->whereNull('pr.rejected_hrd_by')
            ->where('pr.status', 'Approved Atasan')
            ->where(function ($query) {
                $query->where('u.atasan_langsung', 'NOT LIKE', '%"1"%')
                    ->orWhereNull('u.atasan_langsung');
            })
            ->whereNotNull('pr.approved_atasan_by')
            ->whereNotNull('pr.approved_atasan_at')
            ->whereYear('pr.created_at', $periode)
            ->count();

        $processedPermissions = PermissionRequest::query()->toBase()
            ->from('intilab_apps.permission_requests as pr')
            ->whereNotNull('pr.approved_hrd_by')
            ->whereNotNull('pr.approved_hrd_at')
            ->whereNull('pr.rejected_atasan_by')
            ->whereNull('pr.rejected_hrd_by')
            ->whereYear('pr.created_at', $periode)
            ->whereNotNull('pr.approved_atasan_by')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'on_progress' => $onProgressPermissions,
                'processed' => $processedPermissions,
            ],
        ]);
    }

    public function approveIzin(Request $request)
    {
        DB::beginTransaction();
        try {
            $idStr = (string) $request->id;

            $response = $this->approveIzinOnHrTables($idStr);
            if ($response !== null) {
                DB::commit();

                return $response;
            }

            if (strpos($idStr, 'PR-') === 0) {
                $id = substr($idStr, 3);
                $model = PermissionRequest::find($id);
            } else if (strpos($idStr, 'LR-') === 0) {
                $id = substr($idStr, 3);
                $model = LeaveRequest::find($id);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Format ID tidak valid'
                ], 400);
            }

            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form tidak ditemukan'
                ], 404);
            }

            $model->update([
                'status' => 'Approved HRD',
                'approved_hrd_by' => $this->karyawan,
                'approved_hrd_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            $sync = app(PortalHrSync::class);
            if (strpos($idStr, 'PR-') === 0) {
                $sync->syncPermissionFromLegacy((int) $id);
            } else {
                $sync->syncLeaveFromLegacy((int) $id);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form berhasil disetujui'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rejectIzin(Request $request)
    {
        DB::beginTransaction();
        try {
            $idStr = (string) $request->id;

            $response = $this->rejectIzinOnHrTables($idStr, $request->keterangan);
            if ($response !== null) {
                DB::commit();

                return $response;
            }

            if (strpos($idStr, 'PR-') === 0) {
                $id = substr($idStr, 3);
                $model = PermissionRequest::find($id);
            } else if (strpos($idStr, 'LR-') === 0) {
                $id = substr($idStr, 3);
                $model = LeaveRequest::find($id);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Format ID tidak valid'
                ], 400);
            }

            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form tidak ditemukan'
                ], 404);
            }

            $model->update([
                'status' => 'Rejected HRD',
                'rejected_hrd_by' => $this->karyawan,
                'rejected_hrd_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'reject_hrd_reason' => $request->keterangan,
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            $sync = app(PortalHrSync::class);
            if (strpos($idStr, 'PR-') === 0) {
                $sync->syncPermissionFromLegacy((int) $id);
            } else {
                $sync->syncLeaveFromLegacy((int) $id);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form berhasil ditolak'
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse|null null = fallback ke legacy apps
     */
    private function approveIzinOnHrTables(string $idStr)
    {
        $resolved = $this->resolveHrRequestFromCompositeId($idStr);
        if ($resolved === null) {
            return response()->json([
                'success' => false,
                'message' => 'Format ID tidak valid',
            ], 400);
        }

        [$requestType, $apiId] = $resolved;
        $hrRequest = HrRequestResolver::findByPortalSliceId($requestType, $apiId);
        if (!$hrRequest) {
            return HrTableMode::portalReadsHrTables()
                ? response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan tidak ditemukan di HR (ID: ' . $idStr . ')',
                ], 404)
                : null;
        }

        if ($hrRequest->status !== WorkflowStatus::APPROVED_ATASAN) {
            return response()->json([
                'success' => false,
                'message' => 'Menunggu persetujuan atasan (rantai manager) terlebih dahulu',
            ], 422);
        }

        $approver = MasterKaryawan::find($this->user_id);
        if (!$approver) {
            return response()->json([
                'success' => false,
                'message' => 'Data karyawan HRD tidak ditemukan',
            ], 403);
        }

        app(ApprovalService::class)->approveHrd($hrRequest, $approver, $this->karyawan);
        $hrRequest = $hrRequest->fresh();
        app(LegacyHrMirror::class)->syncPortalHrdDecision($hrRequest);

        $legacyId = HrRequestResolver::legacyIdForHrRequest($hrRequest);
        if ($legacyId) {
            if ($requestType === HrRequest::TYPE_PERMISSION) {
                app(PortalHrSync::class)->syncPermissionFromLegacy($legacyId);
            } else {
                app(PortalHrSync::class)->syncLeaveFromLegacy($legacyId);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Form berhasil disetujui',
        ], 200);
    }

    /**
     * @return \Illuminate\Http\JsonResponse|null null = fallback ke legacy apps
     */
    private function rejectIzinOnHrTables(string $idStr, ?string $reason)
    {
        $resolved = $this->resolveHrRequestFromCompositeId($idStr);
        if ($resolved === null) {
            return response()->json([
                'success' => false,
                'message' => 'Format ID tidak valid',
            ], 400);
        }

        [$requestType, $apiId] = $resolved;
        $hrRequest = HrRequestResolver::findByPortalSliceId($requestType, $apiId);
        if (!$hrRequest) {
            return HrTableMode::portalReadsHrTables()
                ? response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan tidak ditemukan di HR (ID: ' . $idStr . ')',
                ], 404)
                : null;
        }

        $approver = MasterKaryawan::find($this->user_id);
        if (!$approver) {
            return response()->json([
                'success' => false,
                'message' => 'Data karyawan HRD tidak ditemukan',
            ], 403);
        }

        app(ApprovalService::class)->rejectHrd($hrRequest, $approver, $reason, $this->karyawan);
        $hrRequest = $hrRequest->fresh();
        app(LegacyHrMirror::class)->syncPortalHrdDecision($hrRequest);

        $legacyId = HrRequestResolver::legacyIdForHrRequest($hrRequest);
        if ($legacyId) {
            if ($requestType === HrRequest::TYPE_PERMISSION) {
                app(PortalHrSync::class)->syncPermissionFromLegacy($legacyId);
            } else {
                app(PortalHrSync::class)->syncLeaveFromLegacy($legacyId);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Form berhasil ditolak',
        ], 200);
    }

    /**
     * @return array{0: string, 1: int}|null [request_type, api_id]
     */
    private function resolveHrRequestFromCompositeId(string $idStr): ?array
    {
        if (strpos($idStr, 'PR-') === 0) {
            return [HrRequest::TYPE_PERMISSION, (int) substr($idStr, 3)];
        }
        if (strpos($idStr, 'LR-') === 0) {
            return [HrRequest::TYPE_LEAVE, (int) substr($idStr, 3)];
        }

        return null;
    }
}
