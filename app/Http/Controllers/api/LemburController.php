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



class LemburController extends Controller
{
    public function indexUnprocessed(Request $request)
    {
        $data = FormHeader::on(env('ANDROID'))
            ->leftJoin('intilab_apps.form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'fd.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.user_id', '=', 'u.user_id')
            ->select(
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                        WHEN form_header.status = "APPROVE ATASAN" THEN "APPROVED" 
                        WHEN form_header.status = "APPROVE HRD" THEN "APPROVED HRD" 
                        WHEN form_header.status = "APPROVE FINANCE" THEN "APPROVED FINANCE" 
                        WHEN form_header.status = "REJECTED ATASAN" THEN "REJECTED" 
                        WHEN form_header.status = "REJECTED HRD" THEN "REJECTED HRD" 
                        WHEN form_header.status = "REJECTED FINANCE" THEN "REJECTED FINANCE" 
                        ELSE "WAITING" 
                    END as status'),

                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw("COUNT(DISTINCT fd.user_id) as total_karyawan"),

                // ambil salah satu (karena per dokumen harusnya sama)
                DB::raw('MAX(fd.approved_hrd_by) as approved_hrd_by'),
                DB::raw('MAX(fd.approved_hrd_at) as approved_hrd_at'),
                DB::raw('MAX(fd.approved_finance_by) as approved_finance_by'),
                DB::raw('MAX(fd.approved_finance_at) as approved_finance_at'),

                DB::raw('MAX(fd.tanggal_mulai) as tanggal'),
                DB::raw('MAX(fd.jam_mulai) as jam_mulai'),
                DB::raw('MAX(fd.jam_selesai) as jam_selesai'),

                DB::raw('MAX(form_header.created_by) as nama_pengaju'),
                DB::raw('MAX(form_header.created_at) as diajukan_pada'),
                DB::raw('MAX(fd.keterangan) as keterangan')
            )
            ->groupBy(
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                'form_header.status'
            )
            ->where('form_header.type_document', 'Lembur')
            ->whereNull('fd.approved_hrd_by')
            ->whereNull('fd.approved_finance_by')
            ->whereNull('fd.rejected_atasan_by')
            ->whereNull('fd.rejected_hrd_by')
            ->whereYear('fd.tanggal_mulai', $request->periode)
            ->get()
            ->transform(function ($item) {
                $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
                return $item;
            });

        return Datatables::of($data)->make(true);
    }

    public function indexProcessed(Request $request)
    {

        $data = FormHeader::on(env('ANDROID'))
            ->leftJoin('intilab_apps.form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'fd.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.user_id', '=', 'u.user_id')
            ->select(
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                                WHEN form_header.status = "APPROVE ATASAN" THEN "APPROVED" 
                                WHEN form_header.status = "APPROVE HRD" THEN "APPROVED HRD" 
                                WHEN form_header.status = "APPROVE FINANCE" THEN "APPROVED FINANCE" 
                                WHEN form_header.status = "REJECTED ATASAN" THEN "REJECTED" 
                                WHEN form_header.status = "REJECTED HRD" THEN "REJECTED HRD" 
                                WHEN form_header.status = "REJECTED FINANCE" THEN "REJECTED FINANCE" 
                                ELSE "WAITING" 
                            END as status'),

                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw("COUNT(DISTINCT fd.user_id) as total_karyawan"),

                // ambil salah satu (karena per dokumen harusnya sama)
                DB::raw('MAX(fd.approved_hrd_by) as approved_hrd_by'),
                DB::raw('MAX(fd.approved_hrd_at) as approved_hrd_at'),
                DB::raw('MAX(fd.approved_finance_by) as approved_finance_by'),
                DB::raw('MAX(fd.approved_finance_at) as approved_finance_at'),

                DB::raw('MAX(fd.tanggal_mulai) as tanggal'),
                DB::raw('MAX(fd.jam_mulai) as jam_mulai'),
                DB::raw('MAX(fd.jam_selesai) as jam_selesai'),

                DB::raw('MAX(form_header.created_by) as nama_pengaju'),
                DB::raw('MAX(form_header.created_at) as diajukan_pada'),
                DB::raw('MAX(fd.keterangan) as keterangan')


            )
            ->groupBy(
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                'form_header.status'
            )
            ->where('form_header.type_document', 'Lembur')
            ->whereNotNull('fd.approved_hrd_by')
            ->whereNull('fd.rejected_atasan_by')
            ->whereNull('fd.rejected_hrd_by')
            ->whereNull('fd.rejected_finance_by')
            ->whereYear('fd.tanggal_mulai', $request->periode)
            ->get()
            ->transform(function ($item) {
                $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
                return $item;
            });

        return Datatables::of($data)->make(true);
    }

    // public function getKaryawanLembur(Request $request)
    // {
    //     try {
    //         $users = FormDetail::leftJoin('master_karyawan as u', 'form_detail.user_id', '=', 'u.id')
    //             ->select([
    //                 'form_detail.user_id',
    //                 'u.nama_lengkap',
    //             ])
    //             ->where('form_detail.no_document', $request->no_document)
    //             ->where('u.nama_lengkap', 'LIKE', '%' . $request->search . '%')
    //             ->whereNull('form_detail.rejected_atasan_by')
    //             ->whereNull('form_detail.approved_hrd_by')
    //             ->limit(10)
    //             ->get();

    //         return response()->json([
    //             'results' => $users,
    //             'pagination' => [
    //                 'more' => false
    //             ]
    //         ]);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function approveLembur(Request $request)
    {
        DB::beginTransaction();
        try {

            $formHeader = FormHeader::on(env('ANDROID'))->where('id', $request->id)->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formDetailIds = FormDetail::on(env('ANDROID'))->where('no_document', $formHeader->no_document)->pluck('id')->toArray();

            $formHeader->status = 'APPROVE HRD';
            $formHeader->save();

            foreach ($formDetailIds as $formDetailId) {
                $formDetail = FormDetail::on(env('ANDROID'))->where('id', $formDetailId)->first();
                $formDetail->approved_hrd_by = $this->karyawan;
                $formDetail->approved_hrd_at = Carbon::now()->format('Y-m-d H:i:s');
                $formDetail->save();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form Lembur berhasil disetujui'
            ], 200);



            // DB::connection(env('ANDROID'))
            //     ->table('form_detail as fd')
            //     ->where('fd.no_document', $request->nodoc)
            //     ->update([
            //         'approved_hrd_by' => DB::raw('CASE WHEN user_id IN (' . implode(',', $request->user_id) . ') THEN "' . $this->name . '" ELSE NULL END'),
            //         'approved_hrd_at' => DB::raw('CASE WHEN user_id IN (' . implode(',', $request->user_id) . ') THEN "' . $this->globaldate . '" ELSE NULL END'),
            //         'rejected_hrd_by' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . $this->name . '" ELSE NULL END'),
            //         'rejected_hrd_at' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . $this->globaldate . '" ELSE NULL END'),
            //         'keterangan_reject' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . ($request->has('keterangan') ? $request->keterangan : NULL) . '" ELSE NULL END'),
            //         'updated_by' => $this->name,
            //         'updated_at' => date('Y-m-d H:i:s'),
            //     ]);

            // DB::connection(env('ANDROID'))
            //     ->table('form_header as fh')
            //     ->where('fh.no_document', $request->nodoc)
            //     ->update([
            //         'status' => 'APPROVE HRD',
            //         'updated_by' => $this->name,
            //         'updated_at' => date('Y-m-d H:i:s'),
            //     ]);

        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rejectLembur(Request $request)
    {
        DB::beginTransaction();
        try {

            $formHeader = FormHeader::on(env('ANDROID'))->where('id', $request->id)->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formDetailIds = FormDetail::on(env('ANDROID'))->where('no_document', $formHeader->no_document)->pluck('id')->toArray();

            $formHeader->status = 'REJECT HRD';
            $formHeader->save();

            foreach ($formDetailIds as $formDetailId) {
                $formDetail = FormDetail::on(env('ANDROID'))->where('id', $formDetailId)->first();
                $formDetail->rejected_hrd_by = $this->karyawan;
                $formDetail->rejected_hrd_at = Carbon::now()->format('Y-m-d H:i:s');
                $formDetail->keterangan_reject = $request->keterangan;
                $formDetail->save();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form Lembur berhasil ditolak'
            ], 200);



            // DB::connection(env('ANDROID'))
            //     ->table('form_detail as fd')
            //     ->where('fd.no_document', $request->nodoc)
            //     ->update([
            //         'approved_hrd_by' => DB::raw('CASE WHEN user_id IN (' . implode(',', $request->user_id) . ') THEN "' . $this->name . '" ELSE NULL END'),
            //         'approved_hrd_at' => DB::raw('CASE WHEN user_id IN (' . implode(',', $request->user_id) . ') THEN "' . $this->globaldate . '" ELSE NULL END'),
            //         'rejected_hrd_by' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . $this->name . '" ELSE NULL END'),
            //         'rejected_hrd_at' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . $this->globaldate . '" ELSE NULL END'),
            //         'keterangan_reject' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . ($request->has('keterangan') ? $request->keterangan : NULL) . '" ELSE NULL END'),
            //         'updated_by' => $this->name,
            //         'updated_at' => date('Y-m-d H:i:s'),
            //     ]);

            // DB::connection(env('ANDROID'))
            //     ->table('form_header as fh')
            //     ->where('fh.no_document', $request->nodoc)
            //     ->update([
            //         'status' => 'APPROVE HRD',
            //         'updated_by' => $this->name,
            //         'updated_at' => date('Y-m-d H:i:s'),
            //     ]);

        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function indexUnprocessedFinance(Request $request)
    {
        $data = FormHeader::on(env('ANDROID'))
            ->leftJoin('intilab_apps.form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'fd.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.user_id', '=', 'u.user_id')
            ->select(
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                                WHEN form_header.status = "APPROVE ATASAN" THEN "APPROVED" 
                                WHEN form_header.status = "APPROVE HRD" THEN "APPROVED HRD" 
                                WHEN form_header.status = "APPROVE FINANCE" THEN "APPROVED FINANCE" 
                                WHEN form_header.status = "REJECTED ATASAN" THEN "REJECTED" 
                                WHEN form_header.status = "REJECTED HRD" THEN "REJECTED HRD" 
                                WHEN form_header.status = "REJECTED FINANCE" THEN "REJECTED FINANCE" 
                                ELSE "WAITING" 
                            END as status'),

                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw("COUNT(DISTINCT fd.user_id) as total_karyawan"),

                DB::raw('MAX(fd.approved_hrd_by) as approved_hrd_by'),
                DB::raw('MAX(fd.approved_hrd_at) as approved_hrd_at'),
                DB::raw('MAX(fd.approved_finance_by) as approved_finance_by'),
                DB::raw('MAX(fd.approved_finance_at) as approved_finance_at'),

                DB::raw('MAX(fd.tanggal_mulai) as tanggal'),
                DB::raw('MAX(fd.jam_mulai) as jam_mulai'),
                DB::raw('MAX(fd.jam_selesai) as jam_selesai'),

                DB::raw('MAX(form_header.created_by) as nama_pengaju'),
                DB::raw('MAX(form_header.created_at) as diajukan_pada'),
                DB::raw('MAX(fd.keterangan) as keterangan')


            )
            ->groupBy(
                'form_header.id',
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                'form_header.status'
            )
            ->where('form_header.type_document', 'Lembur')
            ->whereNotNull('fd.approved_hrd_by')
            ->whereNull('fd.approved_finance_by')
            ->whereNull('fd.rejected_atasan_by')
            ->whereNull('fd.rejected_hrd_by')
            ->whereYear('fd.tanggal_mulai', $request->periode)
            ->get()
            ->transform(function ($item) {
                $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
                return $item;
            });

        // dd($test);

        // $data = FormHeader::on(env('ANDROID'))
        //     ->leftJoin('intilab_apps.form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
        //     ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'fd.department_id')
        //     ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.user_id', '=', 'u.id')
        //     ->select(
        //         'form_header.id',
        //         'form_header.no_document',
        //         'd.nama_divisi as name_department',
        //         DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
        //         DB::raw('MAX(fd.tanggal_mulai) as tanggal'),
        //         DB::raw('MAX(fd.jam_mulai) as jam_mulai'),
        //         DB::raw('MAX(fd.jam_selesai) as jam_selesai'),
        //         DB::raw('COUNT(fd.id) as total_karyawan'),
        //         'fd.keterangan',
        //         DB::raw('CASE 
        //                         WHEN form_header.status = "APPROVE ATASAN" THEN "APPROVED" 
        //                         WHEN form_header.status = "APPROVE HRD" THEN "APPROVED" 
        //                         WHEN form_header.status = "APPROVE FINANCE" THEN "APPROVED" 
        //                         WHEN form_header.status = "REJECTED ATASAN" THEN "REJECTED" 
        //                         WHEN form_header.status = "REJECTED HRD" THEN "REJECTED" 
        //                         WHEN form_header.status = "REJECTED FINANCE" THEN "REJECTED" 
        //                         ELSE "WAITING" 
        //                     END as status'),
        //         'form_header.created_at as add_at',
        //         'form_header.created_by as add_by',
        //         'fd.approved_atasan_by',
        //         'fd.approved_atasan_at',
        //         'fd.approved_hrd_by',
        //         'fd.approved_hrd_at',
        //         'fd.approved_finance_by',
        //         'fd.approved_finance_at',
        //     )
        //     ->groupBy(
        //         'form_header.id',
        //         'form_header.no_document',
        //         'd.nama_divisi',
        //         'fd.keterangan',
        //         'form_header.status',
        //         'form_header.created_at',
        //         'fd.approved_atasan_by',
        //         'fd.approved_atasan_at',
        //         'fd.approved_hrd_by',
        //         'fd.approved_hrd_at',
        //         'fd.approved_finance_by',
        //         'fd.approved_finance_at'
        //     )
        //     ->where('form_header.type_document', 'Lembur')
        //     ->whereNull('fd.approved_hrd_by')
        //     ->whereNull('fd.approved_finance_by')
        //     ->whereNull('fd.rejected_atasan_by')
        //     ->whereNull('fd.rejected_hrd_by')
        //     ->whereYear('fd.tanggal_mulai', $request->periode)
        //     ->get()
        //     ->transform(function ($item) {
        //         $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
        //         return $item;
        //     });

        return Datatables::of($data)->make(true);
    }


    public function indexProcessedFinance(Request $request)
    {
        $data = FormHeader::on(env('ANDROID'))
            ->leftJoin('intilab_apps.form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'fd.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.user_id', '=', 'u.user_id')
            ->select(
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                                WHEN form_header.status = "APPROVE ATASAN" THEN "APPROVED" 
                                WHEN form_header.status = "APPROVE HRD" THEN "APPROVED HRD" 
                                WHEN form_header.status = "APPROVE FINANCE" THEN "APPROVED FINANCE" 
                                WHEN form_header.status = "REJECTED ATASAN" THEN "REJECTED" 
                                WHEN form_header.status = "REJECTED HRD" THEN "REJECTED HRD" 
                                WHEN form_header.status = "REJECTED FINANCE" THEN "REJECTED FINANCE" 
                                ELSE "WAITING" 
                            END as status'),

                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw("COUNT(DISTINCT fd.user_id) as total_karyawan"),

                DB::raw('MAX(fd.approved_hrd_by) as approved_hrd_by'),
                DB::raw('MAX(fd.approved_hrd_at) as approved_hrd_at'),
                DB::raw('MAX(fd.approved_finance_by) as approved_finance_by'),
                DB::raw('MAX(fd.approved_finance_at) as approved_finance_at'),

                DB::raw('MAX(fd.tanggal_mulai) as tanggal'),
                DB::raw('MAX(fd.jam_mulai) as jam_mulai'),
                DB::raw('MAX(fd.jam_selesai) as jam_selesai'),

                DB::raw('MAX(form_header.created_by) as nama_pengaju'),
                DB::raw('MAX(form_header.created_at) as diajukan_pada'),
                DB::raw('MAX(fd.keterangan) as keterangan')

            )
            ->groupBy(
                'form_header.id',
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                'form_header.status'
            )
            ->where('form_header.type_document', 'Lembur')
            ->whereNotNull('fd.approved_hrd_by')
            ->whereNotNull('fd.approved_finance_by')
            ->whereNull('fd.rejected_atasan_by')
            ->whereNull('fd.rejected_hrd_by')
            ->whereYear('fd.tanggal_mulai', $request->periode)
            ->get()
            ->transform(function ($item) {
                $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
                return $item;
            });

        // dd($test);

        // $data = FormHeader::on(env('ANDROID'))
        //     ->leftJoin('intilab_apps.form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
        //     ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'fd.department_id')
        //     ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.user_id', '=', 'u.id')
        //     ->select(
        //         'form_header.id',
        //         'form_header.no_document',
        //         'd.nama_divisi as name_department',
        //         DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
        //         DB::raw('MAX(fd.tanggal_mulai) as tanggal'),
        //         DB::raw('MAX(fd.jam_mulai) as jam_mulai'),
        //         DB::raw('MAX(fd.jam_selesai) as jam_selesai'),
        //         DB::raw('COUNT(fd.id) as total_karyawan'),
        //         'fd.keterangan',
        //         DB::raw('CASE 
        //                         WHEN form_header.status = "APPROVE ATASAN" THEN "APPROVED" 
        //                         WHEN form_header.status = "APPROVE HRD" THEN "APPROVED" 
        //                         WHEN form_header.status = "APPROVE FINANCE" THEN "APPROVED" 
        //                         WHEN form_header.status = "REJECTED ATASAN" THEN "REJECTED" 
        //                         WHEN form_header.status = "REJECTED HRD" THEN "REJECTED" 
        //                         WHEN form_header.status = "REJECTED FINANCE" THEN "REJECTED" 
        //                         ELSE "WAITING" 
        //                     END as status'),
        //         'form_header.created_at as add_at',
        //         'form_header.created_by as add_by',
        //         'fd.approved_atasan_by',
        //         'fd.approved_atasan_at',
        //         'fd.approved_hrd_by',
        //         'fd.approved_hrd_at',
        //         'fd.approved_finance_by',
        //         'fd.approved_finance_at',
        //     )
        //     ->groupBy(
        //         'form_header.id',
        //         'form_header.no_document',
        //         'd.nama_divisi',
        //         'fd.keterangan',
        //         'form_header.status',
        //         'form_header.created_at',
        //         'fd.approved_atasan_by',
        //         'fd.approved_atasan_at',
        //         'fd.approved_hrd_by',
        //         'fd.approved_hrd_at',
        //         'fd.approved_finance_by',
        //         'fd.approved_finance_at'
        //     )
        //     ->where('form_header.type_document', 'Lembur')
        //     ->whereNull('fd.approved_hrd_by')
        //     ->whereNull('fd.approved_finance_by')
        //     ->whereNull('fd.rejected_atasan_by')
        //     ->whereNull('fd.rejected_hrd_by')
        //     ->whereYear('fd.tanggal_mulai', $request->periode)
        //     ->get()
        //     ->transform(function ($item) {
        //         $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
        //         return $item;
        //     });

        return Datatables::of($data)->make(true);
    }


    public function approveLemburFinance(Request $request)
    {
        DB::beginTransaction();
        try {

            $formHeader = FormHeader::on(env('ANDROID'))->where('id', $request->id)->where('status', 'APPROVE HRD')->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formDetailIds = FormDetail::on(env('ANDROID'))->where('no_document', $formHeader->no_document)->pluck('id')->toArray();

            $formHeader->status = 'APPROVE FINANCE';
            $formHeader->save();

            foreach ($formDetailIds as $formDetailId) {
                $formDetail = FormDetail::on(env('ANDROID'))->where('id', $formDetailId)->first();
                $formDetail->approved_finance_by = $this->karyawan;
                $formDetail->approved_finance_at = Carbon::now()->format('Y-m-d H:i:s');
                $formDetail->save();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form Lembur berhasil disetujui'
            ], 200);



            // DB::connection(env('ANDROID'))
            //     ->table('form_detail as fd')
            //     ->where('fd.no_document', $request->nodoc)
            //     ->update([
            //         'approved_hrd_by' => DB::raw('CASE WHEN user_id IN (' . implode(',', $request->user_id) . ') THEN "' . $this->name . '" ELSE NULL END'),
            //         'approved_hrd_at' => DB::raw('CASE WHEN user_id IN (' . implode(',', $request->user_id) . ') THEN "' . $this->globaldate . '" ELSE NULL END'),
            //         'rejected_hrd_by' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . $this->name . '" ELSE NULL END'),
            //         'rejected_hrd_at' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . $this->globaldate . '" ELSE NULL END'),
            //         'keterangan_reject' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . ($request->has('keterangan') ? $request->keterangan : NULL) . '" ELSE NULL END'),
            //         'updated_by' => $this->name,
            //         'updated_at' => date('Y-m-d H:i:s'),
            //     ]);

            // DB::connection(env('ANDROID'))
            //     ->table('form_header as fh')
            //     ->where('fh.no_document', $request->nodoc)
            //     ->update([
            //         'status' => 'APPROVE HRD',
            //         'updated_by' => $this->name,
            //         'updated_at' => date('Y-m-d H:i:s'),
            //     ]);

        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rejectLemburFinance(Request $request)
    {
        DB::beginTransaction();
        try {

            $formHeader = FormHeader::on(env('ANDROID'))->where('id', $request->id)->where('status', 'APPROVE HRD')->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formDetailIds = FormDetail::on(env('ANDROID'))->where('no_document', $formHeader->no_document)->pluck('id')->toArray();

            $formHeader->status = 'REJECT FINANCE';
            $formHeader->save();

            foreach ($formDetailIds as $formDetailId) {
                $formDetail = FormDetail::on(env('ANDROID'))->where('id', $formDetailId)->first();
                $formDetail->rejected_finance_by = $this->karyawan;
                $formDetail->rejected_finance_at = Carbon::now()->format('Y-m-d H:i:s');
                $formDetail->keterangan_reject = $request->keterangan;
                $formDetail->save();
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form Lembur berhasil ditolak'
            ], 200);



            // DB::connection(env('ANDROID'))
            //     ->table('form_detail as fd')
            //     ->where('fd.no_document', $request->nodoc)
            //     ->update([
            //         'approved_hrd_by' => DB::raw('CASE WHEN user_id IN (' . implode(',', $request->user_id) . ') THEN "' . $this->name . '" ELSE NULL END'),
            //         'approved_hrd_at' => DB::raw('CASE WHEN user_id IN (' . implode(',', $request->user_id) . ') THEN "' . $this->globaldate . '" ELSE NULL END'),
            //         'rejected_hrd_by' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . $this->name . '" ELSE NULL END'),
            //         'rejected_hrd_at' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . $this->globaldate . '" ELSE NULL END'),
            //         'keterangan_reject' => DB::raw('CASE WHEN user_id NOT IN (' . implode(',', $request->user_id) . ') THEN "' . ($request->has('keterangan') ? $request->keterangan : NULL) . '" ELSE NULL END'),
            //         'updated_by' => $this->name,
            //         'updated_at' => date('Y-m-d H:i:s'),
            //     ]);

            // DB::connection(env('ANDROID'))
            //     ->table('form_header as fh')
            //     ->where('fh.no_document', $request->nodoc)
            //     ->update([
            //         'status' => 'APPROVE HRD',
            //         'updated_by' => $this->name,
            //         'updated_at' => date('Y-m-d H:i:s'),
            //     ]);

        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}