<?php

namespace App\Http\Controllers\api;

use App\Models\Lemburan;
use App\Models\{FormHeader, FormDetail};
use App\Models\Rfid;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Services\GetAtasan;
use App\Services\GetBawahan;
use App\Services\Notification;
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
        // dd($request);
        $data = FormHeader::on('android_intilab')
            ->leftJoin('android_intilab.form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'fd.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.user_id', '=', 'u.id')
            ->select(
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                        WHEN form_header.status = "APPROVE ATASAN" THEN "WAITING APPROVE HRD" 
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
            ->where('form_header.type_document', 'lembur')
            ->whereNotNull('fd.approved_atasan_by')
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

        $data = FormHeader::on('android_intilab')
            ->leftJoin('android_intilab.form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'fd.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.user_id', '=', 'u.id')
            ->select(
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                                WHEN form_header.status = "APPROVE ATASAN" THEN "APPROVED ATASAN" 
                                WHEN form_header.status = "APPROVE HRD" THEN "APPROVED HRD" 
                                WHEN form_header.status = "APPROVE FINANCE" THEN "APPROVED" 
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
            ->whereNotNull('fd.approved_atasan_by')
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

    public function indexUnprocessedFinance(Request $request)
    {
        $data = FormHeader::on('android_intilab')
            ->leftJoin('android_intilab.form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'fd.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.user_id', '=', 'u.id')
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
            ->whereNotNull('fd.approved_atasan_by')
            ->whereNotNull('fd.approved_hrd_by')
            ->whereNull('fd.approved_finance_by')
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


    public function indexProcessedFinance(Request $request)
    {
        $data = FormHeader::on('android_intilab')
            ->leftJoin('android_intilab.form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'fd.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.user_id', '=', 'u.id')
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
            ->whereNotNull('fd.approved_atasan_by')
            ->whereNotNull('fd.approved_hrd_by')
            ->whereNotNull('fd.approved_finance_by')
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

    public function indexByOwner(Request $request)
    {
        $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
        $data = FormHeader::on('android_intilab')
            ->leftJoin('android_intilab.form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'fd.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.user_id', '=', 'u.id')
            ->select(
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                                WHEN form_header.status = "APPROVE ATASAN" THEN "WAITING APPROVE HRD" 
                                WHEN form_header.status = "APPROVE HRD" THEN "WAITING APPROVE FINANCE" 
                                WHEN form_header.status = "APPROVE FINANCE" THEN "APPROVED" 
                                WHEN form_header.status = "REJECTED ATASAN" THEN "REJECTED" 
                                WHEN form_header.status = "REJECTED HRD" THEN "REJECTED HRD" 
                                WHEN form_header.status = "REJECTED FINANCE" THEN "REJECTED FINANCE" 
                                ELSE "WAITING" 
                            END as status'),

                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw("COUNT(DISTINCT fd.user_id) as total_karyawan"),

                DB::raw('MAX(fd.approved_hrd_by) as approved_hrd_by'),
                DB::raw('MAX(fd.approved_hrd_at) as approved_hrd_at'),
                DB::raw('MAX(fd.approved_atasan_by) as approved_atasan_by'),
                DB::raw('MAX(fd.approved_atasan_at) as approved_atasan_at'),
                DB::raw('MAX(fd.rejected_atasan_by) as rejected_atasan_by'),
                DB::raw('MAX(fd.rejected_atasan_at) as rejected_atasan_at'),
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
            ->havingRaw("
                (
                    (MAX(fd.approved_finance_by) IS NULL AND MAX(fd.approved_hrd_by) IS NULL)
                )
            ")
            ->where('form_header.type_document', 'Lembur')
            ->whereIn('form_header.created_by', $bawahan)
            ->whereYear('fd.tanggal_mulai', $request->periode)
            ->get()
            ->transform(function ($item) {
                $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
                return $item;
            });

        return Datatables::of($data)
            ->addColumn('can_approve', function ($row) {
                return $this->grade == 'MANAGER' ? true : false;
            })
            ->make(true);
    }

    public function indexByOwnerProcessed(Request $request)
    {
        $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('nama_lengkap')->toArray();
        $data = FormHeader::on('android_intilab')
            ->leftJoin('android_intilab.form_detail as fd', 'fd.no_document', '=', 'form_header.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'fd.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.user_id', '=', 'u.id')
            ->select(
                'form_header.id',
                'form_header.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                                WHEN form_header.status = "APPROVE ATASAN" THEN "WAITING APPROVE HRD" 
                                WHEN form_header.status = "APPROVE HRD" THEN "WAITING APPROVE FINANCE" 
                                WHEN form_header.status = "APPROVE FINANCE" THEN "APPROVED" 
                                WHEN form_header.status = "REJECTED ATASAN" THEN "REJECTED" 
                                WHEN form_header.status = "REJECTED HRD" THEN "REJECTED HRD" 
                                WHEN form_header.status = "REJECTED FINANCE" THEN "REJECTED FINANCE" 
                                ELSE "WAITING" 
                            END as status'),

                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw("COUNT(DISTINCT fd.user_id) as total_karyawan"),

                DB::raw('MAX(fd.approved_hrd_by) as approved_hrd_by'),
                DB::raw('MAX(fd.approved_hrd_at) as approved_hrd_at'),
                DB::raw('MAX(fd.approved_atasan_by) as approved_atasan_by'),
                DB::raw('MAX(fd.approved_atasan_at) as approved_atasan_at'),
                DB::raw('MAX(fd.rejected_atasan_by) as rejected_atasan_by'),
                DB::raw('MAX(fd.rejected_atasan_at) as rejected_atasan_at'),
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
            // ->where(function ($query) {
            //     $query->whereNotNull('MAX(fd.approved_hrd_by)')->orWhereNotNull('MAX(fd.rejected_hrd_by)');
            // })
            ->havingRaw("
                (
                    (MAX(fd.approved_finance_by) IS NOT NULL AND MAX(fd.approved_hrd_by) IS NOT NULL)
                )
            ")
            ->whereIn('form_header.created_by', $bawahan)
            ->whereYear('fd.tanggal_mulai', $request->periode)
            ->get()
            ->transform(function ($item) {
                $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
                return $item;
            });

        return Datatables::of($data)
            ->addColumn('can_approve', function ($row) {
                return $this->grade == 'MANAGER' ? true : false;
            })
            ->make(true);
    }

    public function getListKaryawan(Request $request)
    {
        $allKaryawan = MasterKaryawan::select('id', 'nama_lengkap')->where('is_active', true)->where('department', $request->dept)->get();

        return response()->json(
            [
                'message' => 'get data karyawan success',
                'data' => $allKaryawan
            ],
            200
        );
    }

    public function updateLembur(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = FormHeader::on('android_intilab')->find($request->id);
    
            FormDetail::on('android_intilab')->where('no_document', $header->no_document)->delete();
            
            foreach ($request->data as $detail) {
                $atasan = MasterKaryawan::select('atasan_langsung')->where('id', $detail)->first()->atasan_langsung;
                $details[] = [
                    'no_document' => $header->no_document,
                    'user_id' => $detail,
                    'department_id' => $this->department,
                    'atasan_langsung' => $atasan,
                    'jam_mulai' => $request->jam_mulai,
                    'jam_selesai' => $request->jam_selesai,
                    'tanggal_mulai' => $request->tanggal_lembur,
                    'tanggal_selesai' => !empty($request->tanggal_selesai) ? $request->tanggal_selesai : $request->tanggal_lembur ?? null,
                    'approved_atasan_by' => $this->grade === 'MANAGER' ? $this->karyawan : null,
                    'approved_atasan_at' => $this->grade === 'MANAGER' ? Carbon::now()->format('Y-m-d H:i:s') : null,
                    'keterangan' => $request->keterangan,
                    'created_by' => $this->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ];
            }
            // dd($details);
            FormDetail::on('android_intilab')->insert($details);

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Form Lembur berhasil diupdate',
                'data' => [
                    'no_document' => $header->no_document
                ]
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => 'Error: ' . $th->getMessage(),
                'line' => $th->getLine()
            ], 500);
        }


    }

    public function createLembur(Request $request)
    {
        DB::beginTransaction();
        try {
            $romanMonth = [
                '01' => 'I',
                '02' => 'II',
                '03' => 'III',
                '04' => 'IV',
                '05' => 'V',
                '06' => 'VI',
                '07' => 'VII',
                '08' => 'VIII',
                '09' => 'IX',
                '10' => 'X',
                '11' => 'XI',
                '12' => 'XII'
            ];
            // $prefix = 'ISL/LEMBUR/' . date('y') . '-' . $romanMonth[date('m')];
            // $no_document = $prefix . '/' . str_pad($this->getLatestNumber($prefix), 6, '0', STR_PAD_LEFT);
            $timestamp = Carbon::now()->timestamp;
            $no_document = substr($timestamp, 0, 10);
            FormHeader::on('android_intilab')->create([
                'no_document' => $no_document,
                'type_document' => 'Lembur',
                'tanggal' => $request->tanggal_lembur,
                'created_by' => $this->karyawan,
                'status' => $this->grade === 'MANAGER' ? 'APPROVE ATASAN' : null,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
            $details = [];

            foreach ($request->data as $detail) {
                $atasan = MasterKaryawan::select('atasan_langsung')->where('id', $detail)->first()->atasan_langsung;
                $details[] = [
                    'no_document' => $no_document,
                    'user_id' => $detail,
                    'department_id' => $this->department,
                    'atasan_langsung' => $atasan,
                    'jam_mulai' => $request->jam_mulai,
                    'jam_selesai' => $request->jam_selesai,
                    'tanggal_mulai' => $request->tanggal_lembur,
                    'tanggal_selesai' => !empty($request->tanggal_selesai) ? $request->tanggal_selesai : $request->tanggal_lembur ?? null,
                    'approved_atasan_by' => $this->grade === 'MANAGER' ? $this->karyawan : null,
                    'approved_atasan_at' => $this->grade === 'MANAGER' ? Carbon::now()->format('Y-m-d H:i:s') : null,
                    'keterangan' => $request->keterangan,
                    'created_by' => $this->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ];
            }
            // dd($details);
            FormDetail::on('android_intilab')->insert($details);

            $sendNotifTo = [];
            if($this->grade === 'MANAGER') {
                $idBuDella = 5;
                $atasan = GetAtasan::where('id', $idBuDella)->get();
                $bawahan = GetBawahan::where('id', $idBuDella)->get();
                $sendNotifTo = array_merge($atasan->pluck('id')->toArray(), $bawahan->pluck('id')->toArray());
            } else {
                $atasan = GetAtasan::where('id', $this->user_id)->get();
                $sendNotifTo = $atasan->pluck('id')->toArray();
            }
            
            Notification::whereIn('id', $sendNotifTo)
                ->title('Lembur Telah Dibuat!')
                ->message('Lembur telah dibuat' . ' Oleh ' . $this->karyawan)
                ->url('/form-lembur')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form Lembur berhasil dibuat',
                'data' => [
                    'no_document' => $no_document
                ]
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => 'Error: ' . $th->getMessage(),
                'line' => $th->getLine()
            ], 500);
        }
    }

    private function getLatestNumber($no_document)
    {
        $latestDocument = FormHeader::on('android_intilab')->where('no_document', 'LIKE', $no_document . '%')->orderBy('no_document', 'DESC')->first();

        if ($latestDocument) {
            $lastNumber = intval(substr($latestDocument->no_document, -6));
            return $lastNumber + 1;
        } else {
            return 1;
        }
    }


     public function approveLembur(Request $request)
    {
        DB::beginTransaction();
        try {

            $formHeader = FormHeader::on('android_intilab')->where('id', $request->id)->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formDetailIds = FormDetail::on('android_intilab')->where('no_document', $formHeader->no_document)->pluck('id')->toArray();

            $formHeader->status = 'APPROVE HRD';
            $formHeader->save();

            foreach ($formDetailIds as $formDetailId) {
                $formDetail = FormDetail::on('android_intilab')->where('id', $formDetailId)->first();
                $formDetail->approved_hrd_by = $this->karyawan;
                $formDetail->approved_hrd_at = Carbon::now()->format('Y-m-d H:i:s');
                $formDetail->save();
            }

            $userId = GetAtasan::where('nama_lengkap', $formHeader->created_by)->get()->pluck('id')->toArray();

            $message = 'Form lembur telah di approve';


            Notification::whereIn('id', $userId)
                ->title('Form Lembur')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/form-lembur')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form Lembur berhasil disetujui'
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

    public function rejectLembur(Request $request)
    {
        DB::beginTransaction();
        try {

            $formHeader = FormHeader::on('android_intilab')->where('id', $request->id)->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formDetailIds = FormDetail::on('android_intilab')->where('no_document', $formHeader->no_document)->pluck('id')->toArray();

            $formHeader->status = 'DRAFT';
            $formHeader->save();

            foreach ($formDetailIds as $formDetailId) {
                $formDetail = FormDetail::on('android_intilab')->where('id', $formDetailId)->first();
                $formDetail->rejected_hrd_by = $this->karyawan;
                $formDetail->rejected_hrd_at = Carbon::now()->format('Y-m-d H:i:s');
                $formDetail->approved_atasan_at = null;
                $formDetail->approved_atasan_by = null;
                $formDetail->keterangan_reject = $request->keterangan;
                $formDetail->save();
            }

            $message = 'Form lembur telah di reject';


            $userId = GetAtasan::where('nama_lengkap', $formHeader->created_by)->get()->pluck('id')->toArray();


            Notification::whereIn('id', $userId)
                ->title('Form Lembur')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/form-lembur')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form Lembur berhasil ditolak'
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

    public function approveAtasanLembur(Request $request)
    {
        DB::beginTransaction();
        try {

            $formHeader = FormHeader::on('android_intilab')->where('id', $request->id)->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formDetailIds = FormDetail::on('android_intilab')->where('no_document', $formHeader->no_document)->pluck('id')->toArray();

            $formHeader->status = 'APPROVE ATASAN';
            $formHeader->save();

            foreach ($formDetailIds as $formDetailId) {
                $formDetail = FormDetail::on('android_intilab')->where('id', $formDetailId)->first();
                $formDetail->approved_atasan_by = $this->karyawan;
                $formDetail->approved_atasan_at = Carbon::now()->format('Y-m-d H:i:s');
                $formDetail->save();
            }

            $message = 'Form lembur telah di approve';
            $userId = GetAtasan::where('nama_lengkap', $formHeader->created_by)->get()->pluck('id')->toArray();

            Notification::whereIn('id', $userId)
                ->title('Form Lembur')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/form-lembur')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form Lembur berhasil disetujui'
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

    public function rejectAtasanLembur(Request $request)
    {
        DB::beginTransaction();
        try {

            $formHeader = FormHeader::on('android_intilab')->where('id', $request->id)->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formDetailIds = FormDetail::on('android_intilab')->where('no_document', $formHeader->no_document)->pluck('id')->toArray();

            $formHeader->status = 'DRAFT';
            $formHeader->save();

            foreach ($formDetailIds as $formDetailId) {
                $formDetail = FormDetail::on('android_intilab')->where('id', $formDetailId)->first();
                $formDetail->rejected_atasan_by = $this->karyawan;
                $formDetail->rejected_atasan_at = Carbon::now()->format('Y-m-d H:i:s');
                $formDetail->keterangan_reject = $request->keterangan;
                $formDetail->save();
            }

            $message = 'Form lembur telah di reject';
            $userId = GetAtasan::where('nama_lengkap', $formHeader->created_by)->get()->pluck('id')->toArray();
            Notification::whereIn('id', $userId)
                ->title('Form Lembur')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/form-lembur')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form Lembur berhasil ditolak'
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




    public function approveLemburFinance(Request $request)
    {
        DB::beginTransaction();
        try {

            $formHeader = FormHeader::on('android_intilab')->where('id', $request->id)->where('status', 'APPROVE HRD')->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formDetailIds = FormDetail::on('android_intilab')->where('no_document', $formHeader->no_document)->pluck('id')->toArray();

            $formHeader->status = 'APPROVE FINANCE';
            $formHeader->save();

            foreach ($formDetailIds as $formDetailId) {
                $formDetail = FormDetail::on('android_intilab')->where('id', $formDetailId)->first();
                $formDetail->approved_finance_by = $this->karyawan;
                $formDetail->approved_finance_at = Carbon::now()->format('Y-m-d H:i:s');
                $formDetail->save();
            }


            $message = 'Form lembur telah di approve';
            $userId = GetAtasan::where('nama_lengkap', $formHeader->created_by)->get()->pluck('id')->toArray();
            Notification::whereIn('id', $userId)
                ->title('Form Lembur')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/form-lembur')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form Lembur berhasil disetujui'
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

    public function rejectLemburFinance(Request $request)
    {
        DB::beginTransaction();
        try {

            $formHeader = FormHeader::on('android_intilab')->where('id', $request->id)->where('status', 'APPROVE HRD')->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formDetailIds = FormDetail::on('android_intilab')->where('no_document', $formHeader->no_document)->pluck('id')->toArray();

            $formHeader->status = 'APPROVE ATASAN';
            $formHeader->save();

            foreach ($formDetailIds as $formDetailId) {
                $formDetail = FormDetail::on('android_intilab')->where('id', $formDetailId)->first();
                $formDetail->rejected_finance_by = $this->karyawan;
                $formDetail->rejected_finance_at = Carbon::now()->format('Y-m-d H:i:s');
                $formDetail->approved_hrd_at = null;
                $formDetail->approved_hrd_by = null;
                $formDetail->keterangan_reject = $request->keterangan;
                $formDetail->save();
            }


            $message = 'Form lembur telah di reject';
            $userId = GetAtasan::where('nama_lengkap', $formHeader->created_by)->get()->pluck('id')->toArray();
            Notification::whereIn('id', $userId)
                ->title('Form Lembur')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/form-lembur')
                ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Form Lembur berhasil ditolak'
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
