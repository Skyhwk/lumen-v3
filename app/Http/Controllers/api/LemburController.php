<?php

namespace App\Http\Controllers\api;

use App\Models\Lemburan;
use App\Models\{FormHeader, FormDetail};
use App\Models\Rfid;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;



class LemburController extends Controller
{
    public function indexUnprocessed(Request $request)
    {
        $data = FormHeader::on(env('ANDROID'))
            ->leftJoin('form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
            ->leftJoin('master_divisi as d', 'd.id', '=', 'fd.department_id')
            ->leftJoin('master_karyawan as u', 'fd.user_id', '=', 'u.id')
            ->select(
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi as name_department',
                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw('MAX(fd.tanggal_mulai) as tanggal'),
                DB::raw('MAX(fd.jam_mulai) as jam_mulai'),
                DB::raw('MAX(fd.jam_selesai) as jam_selesai'),
                'fd.keterangan',
                DB::raw('CASE 
                                WHEN form_header.status = "APPROVE ATASAN" THEN "APPROVED" 
                                WHEN form_header.status = "APPROVE HRD" THEN "APPROVED" 
                                WHEN form_header.status = "APPROVE FINANCE" THEN "APPROVED" 
                                WHEN form_header.status = "REJECTED ATASAN" THEN "REJECTED" 
                                WHEN form_header.status = "REJECTED HRD" THEN "REJECTED" 
                                WHEN form_header.status = "REJECTED FINANCE" THEN "REJECTED" 
                                ELSE "WAITING" 
                            END as status'),
                'form_header.created_at as add_at',
                'fd.approved_atasan_by',
                'fd.approved_atasan_at',
                'fd.approved_hrd_by',
                'fd.approved_hrd_at',
                'fd.approved_finance_by',
                'fd.approved_finance_at',
            )
            ->groupBy(
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                'fd.keterangan',
                'form_header.status',
                'form_header.created_at',
                'fd.approved_atasan_by',
                'fd.approved_atasan_at',
                'fd.approved_hrd_by',
                'fd.approved_hrd_at',
                'fd.approved_finance_by',
                'fd.approved_finance_at'
            )
            ->where('form_header.type_document', 'Lembur')
            ->whereNull('fd.approved_hrd_by')
            ->whereNull('fd.approved_finance_by')
            ->whereNull('fd.rejected_atasan_by')
            ->whereNull('fd.rejected_hrd_by')
            ->whereYear('fd.tanggal_mulai', $request->tahun)
            ->get()
            ->transform(function ($item) {
                $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
                return $item;
            });

        return Datatables::of($data)->make(true);
    }

    public function indexProcessed(Request $request)
    {
        $data = FormHeader::leftJoin('form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
            ->leftJoin('intilab_2024.department as d', 'd.id', '=', 'fd.department_id')
            ->leftJoin('intilab_2024.users as u', 'fd.user_id', '=', 'u.id')
            ->select(
                'form_header.id',
                'form_header.no_document',
                'd.name_department',
                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw('MAX(fd.tanggal_mulai) as tanggal'),
                DB::raw('MAX(fd.jam_mulai) as jam_mulai'),
                DB::raw('MAX(fd.jam_selesai) as jam_selesai'),
                'fd.keterangan',
                DB::raw('CASE 
                                WHEN form_header.status = "APPROVE ATASAN" THEN "APPROVED" 
                                WHEN form_header.status = "APPROVE HRD" THEN "APPROVED" 
                                WHEN form_header.status = "APPROVE FINANCE" THEN "APPROVED" 
                                WHEN form_header.status = "REJECTED ATASAN" THEN "REJECTED" 
                                WHEN form_header.status = "REJECTED HRD" THEN "REJECTED" 
                                WHEN form_header.status = "REJECTED FINANCE" THEN "REJECTED" 
                                ELSE "WAITING" 
                            END as status'),
                'form_header.created_at as add_at',
                'fd.approved_atasan_by',
                'fd.approved_atasan_at',
                'fd.approved_hrd_by',
                'fd.approved_hrd_at',
                'fd.approved_finance_by',
                'fd.approved_finance_at',
            )
            ->groupBy(
                'form_header.id',
                'form_header.no_document',
                'd.name_department',
                'fd.keterangan',
                'form_header.status',
                'form_header.created_at',
                'fd.approved_atasan_by',
                'fd.approved_atasan_at',
                'fd.approved_hrd_by',
                'fd.approved_hrd_at',
                'fd.approved_finance_by',
                'fd.approved_finance_at'
            )
            ->where('form_header.type_document', 'Lembur')
            ->whereNotNull('fd.approved_hrd_by')
            ->whereNull('fd.approved_finance_by')
            ->whereNull('fd.rejected_atasan_by')
            ->whereNull('fd.rejected_hrd_by')
            ->whereYear('fd.tanggal_mulai', $request->tahun)
            ->get()
            ->transform(function ($item) {
                $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
                return $item;
            });

        return Datatables::of($data)->make(true);
    }

    public function getKaryawanLembur(Request $request)
    {
        try {
            $users = FormDetail::leftJoin('master_karyawan as u', 'form_detail.user_id', '=', 'u.id')
                ->select([
                    'form_detail.user_id',
                    'u.nama_lengkap',
                ])
                ->where('form_detail.no_document', $request->no_document)
                ->where('u.nama_lengkap', 'LIKE', '%' . $request->search . '%')
                ->whereNull('form_detail.rejected_atasan_by')
                ->whereNull('form_detail.approved_hrd_by')
                ->limit(10)
                ->get();

            return response()->json([
                'results' => $users,
                'pagination' => [
                    'more' => false
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approveLembur(Request $request)
    {
        DB::beginTransaction();
        try {
            DB::connection(env('ANDROID'))
                ->table('form_detail as fd')
                ->where('fd.no_document', $request->nodoc)
                ->update([
                    'approved_hrd_by' => DB::raw('CASE WHEN user_id IN (' . implode(',', $request->user_id) . ') THEN "' . $this->name . '" ELSE NULL END'),
                    'approved_hrd_at' => DB::raw('CASE WHEN user_id IN (' . implode(',', $request->user_id) . ') THEN "' . $this->globaldate . '" ELSE NULL END'),
                    'rejected_hrd_by' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . $this->name . '" ELSE NULL END'),
                    'rejected_hrd_at' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . $this->globaldate . '" ELSE NULL END'),
                    'keterangan_reject' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . ($request->has('keterangan') ? $request->keterangan : NULL) . '" ELSE NULL END'),
                    'updated_by' => $this->name,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            DB::connection(env('ANDROID'))
                ->table('form_header as fh')
                ->where('fh.no_document', $request->nodoc)
                ->update([
                    'status' => 'APPROVE HRD',
                    'updated_by' => $this->name,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'error' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}