<?php

namespace App\Http\Controllers\api;

use App\Models\PermissionRequest;
use App\Models\LeaveRequest;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class IzinController extends Controller
{
    public function indexUnprocessed(Request $request)
    {
        $permissions = PermissionRequest::query()
            ->from('intilab_apps.permission_requests as pr')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'pr.employee_id', '=', 'u.user_id')
            ->leftJoin('intilab_produksi.master_divisi as d', 'u.id_department', '=', 'd.id')
            ->select(
                DB::raw("CONCAT('PR-', pr.id) as id"),
                'pr.no_document',
                'd.nama_divisi',
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

        $leaves = LeaveRequest::query()
            ->from('intilab_apps.leave_requests as lr')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'lr.employee_id', '=', 'u.user_id')
            ->leftJoin('intilab_produksi.master_divisi as d', 'u.id_department', '=', 'd.id')
            ->select(
                DB::raw("CONCAT('LR-', lr.id) as id"),
                'lr.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
            WHEN lr.status = "Approved Atasan" THEN "APPROVED ATASAN" 
            WHEN lr.status = "Approved HRD" THEN "APPROVED HRD" 
            WHEN lr.status = "Rejected Atasan" THEN "REJECTED ATASAN" 
            WHEN lr.status = "Rejected HRD" THEN "REJECTED HRD" 
            ELSE "WAITING" 
        END as status'),
                DB::raw('CASE 
            WHEN lr.type = "Annual Leave" THEN "cuti" 
            WHEN lr.type = "Special Leave" THEN "cuti_khusus" 
            WHEN lr.type = "Unpaid Leave" THEN "unpaid_leave" 
            ELSE lr.type 
        END as type_document'),
                'lr.start_date as tanggal_mulai',
                'lr.end_date as tanggal_selesai',
                DB::raw('NULL as jam_mulai'),
                DB::raw('NULL as jam_selesai'),
                'lr.description as keterangan',
                'lr.approved_atasan_by',
                'lr.approved_atasan_at',
                'lr.approved_hrd_by',
                'lr.approved_hrd_at',
                'lr.rejected_atasan_by',
                'lr.rejected_atasan_at',
                'lr.rejected_hrd_by',
                'lr.rejected_hrd_at',
                'lr.created_by as nama_pengaju',
                'lr.attachment as filename',
                DB::raw('NULL as nama_delegasi'),
                'lr.created_at as diajukan_pada'
            )
            ->whereNull('lr.rejected_atasan_by')
            ->whereNull('lr.rejected_hrd_by')
            ->where('lr.status', 'Approved Atasan')
            ->where(function ($query) {
                 $query->where('u.atasan_langsung', 'NOT LIKE', '%"1"%')
                       ->orWhereNull('u.atasan_langsung');
            })
            ->whereNotNull('lr.approved_atasan_by')
            ->whereNotNull('lr.approved_atasan_at')
            ->whereYear('lr.created_at', $request->periode);

        $data = $permissions->unionAll($leaves)->get();

        return Datatables::of($data)->make(true);
    }

    public function indexProcessed(Request $request)
    {
         $permissions = PermissionRequest::query()
            ->from('intilab_apps.permission_requests as pr')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'pr.employee_id', '=', 'u.user_id')
            ->leftJoin('intilab_produksi.master_divisi as d', 'u.id_department', '=', 'd.id')
            ->select(
                DB::raw("CONCAT('PR-', pr.id) as id"),
                'pr.no_document',
                'd.nama_divisi',
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

        $leaves = LeaveRequest::query()
            ->from('intilab_apps.leave_requests as lr')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'lr.employee_id', '=', 'u.user_id')
            ->leftJoin('intilab_produksi.master_divisi as d', 'u.id_department', '=', 'd.id')
            ->select(
                DB::raw("CONCAT('LR-', lr.id) as id"),
                'lr.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
            WHEN lr.status = "Approved Atasan" THEN "APPROVED" 
            WHEN lr.status = "Approved HRD" THEN "APPROVED HRD" 
            WHEN lr.status = "Rejected Atasan" THEN "REJECTED" 
            WHEN lr.status = "Rejected HRD" THEN "REJECTED HRD" 
            ELSE "WAITING" 
        END as status'),
                DB::raw('CASE 
            WHEN lr.type = "Annual Leave" THEN "cuti" 
            WHEN lr.type = "Special Leave" THEN "cuti_khusus" 
            WHEN lr.type = "Unpaid Leave" THEN "unpaid_leave" 
            ELSE lr.type 
        END as type_document'),
                'lr.start_date as tanggal_mulai',
                'lr.end_date as tanggal_selesai',
                DB::raw('NULL as jam_mulai'),
                DB::raw('NULL as jam_selesai'),
                'lr.description as keterangan',
                'lr.approved_atasan_by',
                'lr.approved_atasan_at',
                'lr.approved_hrd_by',
                'lr.approved_hrd_at',
                'lr.rejected_atasan_by',
                'lr.rejected_atasan_at',
                'lr.rejected_hrd_by',
                'lr.rejected_hrd_at',
                'lr.created_by as nama_pengaju',
                'lr.attachment as filename',
                DB::raw('NULL as nama_delegasi'),
                'lr.created_at as diajukan_pada'
            )
            ->whereNotNull('lr.approved_hrd_by')
            ->whereNotNull('lr.approved_hrd_at')
            ->whereNull('lr.rejected_atasan_by')
            ->whereNull('lr.rejected_hrd_by')
            ->whereYear('lr.created_at', $request->periode)
            ->whereNotNull('lr.approved_atasan_by');

        $data = $permissions->unionAll($leaves)->get();

        return Datatables::of($data)->make(true);
    }

    public function approveIzin(Request $request)
    {
        DB::beginTransaction();
        try {
            $idStr = $request->id;
            
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
            $idStr = $request->id;
            
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
}
