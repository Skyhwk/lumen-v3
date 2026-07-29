<?php

namespace App\Http\Controllers\api;

use App\Models\Lemburan;
use App\Models\{FormHeader, FormDetail};
use App\Models\Rfid;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;



class IzinController extends Controller
{
    public function indexUnprocessed(Request $request)
    {
        $data = DB::table('intilab_apps.permission_requests as pr')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'pr.employee_id', '=', 'u.user_id')
            ->leftJoin('intilab_produksi.master_divisi as d', 'u.id_department', '=', 'd.id')
            ->select(
                'pr.id',
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
            ->whereYear('pr.created_at', $request->periode)
            ->get();

        return Datatables::of($data)->make(true);
    }

    public function indexProcessed(Request $request)
    {
         $data = DB::table('intilab_apps.permission_requests as pr')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'pr.employee_id', '=', 'u.user_id')
            ->leftJoin('intilab_produksi.master_divisi as d', 'u.id_department', '=', 'd.id')
            ->select(
                'pr.id',
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
            ->whereNotNull('pr.approved_atasan_by')
            ->get();

        return Datatables::of($data)->make(true);
    }

    public function approveIzin(Request $request)
    {
        DB::beginTransaction();
        try {
            $permissionRequest = DB::table('intilab_apps.permission_requests')->where('id', $request->id)->first();

            if (!$permissionRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Izin tidak ditemukan'
                ], 404);
            }

            DB::table('intilab_apps.permission_requests')->where('id', $request->id)->update([
                'status' => 'Approved HRD',
                'approved_hrd_by' => $this->karyawan,
                'approved_hrd_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form Izin berhasil disetujui'
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
            $permissionRequest = DB::table('intilab_apps.permission_requests')->where('id', $request->id)->first();

            if (!$permissionRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Izin tidak ditemukan'
                ], 404);
            }

            DB::table('intilab_apps.permission_requests')->where('id', $request->id)->update([
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
                'message' => 'Form Izin berhasil ditolak'
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
