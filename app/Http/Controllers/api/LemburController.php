<?php

namespace App\Http\Controllers\api;

use App\Models\Lemburan;
use App\Models\{FormHeader, OvertimeRequestMembers, OvertimeRequest};
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
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;



class LemburController extends Controller
{
    public function indexUnprocessed(Request $request)
    {
        $data = OvertimeRequest::on('intilab_apps')
            ->leftJoin('intilab_apps.overtime_request_members as fd', 'fd.no_document', '=', 'overtime_requests.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'overtime_requests.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.employee_id', '=', 'u.id')
            ->select(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                        WHEN overtime_requests.status = "Approved Atasan" THEN "Approve Atasan" 
                        WHEN overtime_requests.status = "Approved HRD" THEN "Approved HRD" 
                        WHEN overtime_requests.status = "Approved Finance" THEN "Approved Finance" 
                        WHEN overtime_requests.status = "Rejected Atasan" THEN "Rejected Atasan" 
                        WHEN overtime_requests.status = "Rejected HRD" THEN "Rejected HRD" 
                        WHEN overtime_requests.status = "Rejected Finance" THEN "Rejected Finance" 
                        ELSE "Pending" 
                    END as status'),

                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw("COUNT(DISTINCT fd.employee_id) as total_karyawan"),

                'overtime_requests.approved_hrd_by',
                'overtime_requests.approved_hrd_at',
                'overtime_requests.approved_finance_by',
                'overtime_requests.approved_finance_at',
                'overtime_requests.approved_atasan_by',
                'overtime_requests.approved_atasan_at',

                'overtime_requests.start_date as tanggal',
                'overtime_requests.start_time as jam_mulai',
                'overtime_requests.end_time as jam_selesai',

                'overtime_requests.created_by as nama_pengaju',
                'overtime_requests.created_at as diajukan_pada',
                'overtime_requests.description as keterangan'
            )
            ->groupBy(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                'overtime_requests.status'
            )
            ->whereNotNull('overtime_requests.approved_atasan_by')
            ->whereNull('overtime_requests.approved_hrd_by')
            ->whereNull('overtime_requests.approved_finance_by')
            ->whereNull('overtime_requests.rejected_atasan_by')
            ->whereNull('overtime_requests.rejected_hrd_by')
            ->whereYear('overtime_requests.start_date', $request->periode)
            ->get()
            ->transform(function ($item) {
                $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
                return $item;
            });
        return Datatables::of($data)->make(true);
    }

    public function indexProcessed(Request $request)
    {

        $data = OvertimeRequest::on('intilab_apps')
            ->leftJoin('intilab_apps.overtime_request_members as fd', 'fd.no_document', '=', 'overtime_requests.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'overtime_requests.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.employee_id', '=', 'u.id')
            ->select(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                                WHEN overtime_requests.status = "Approved Atasan" THEN "Approve Atasan" 
                                WHEN overtime_requests.status = "Approved HRD" THEN "Approved HRD" 
                                WHEN overtime_requests.status = "Approved Finance" THEN "Approved Finance" 
                                WHEN overtime_requests.status = "Rejected Atasan" THEN "Rejected" 
                                WHEN overtime_requests.status = "Rejected HRD" THEN "Rejected HRD" 
                                WHEN overtime_requests.status = "Rejected Finance" THEN "Rejected Finance" 
                                ELSE "Pending" 
                            END as status'),

                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw("COUNT(DISTINCT fd.employee_id) as total_karyawan"),

                'overtime_requests.approved_hrd_by',
                'overtime_requests.approved_hrd_at',
                'overtime_requests.approved_finance_by',
                'overtime_requests.approved_finance_at',
                'overtime_requests.approved_atasan_by',
                'overtime_requests.approved_atasan_at',

                'overtime_requests.start_date as tanggal',
                'overtime_requests.start_time as jam_mulai',
                'overtime_requests.end_time as jam_selesai',

                'overtime_requests.created_by as nama_pengaju',
                'overtime_requests.created_at as diajukan_pada',
                'overtime_requests.description as keterangan'

            )
            ->groupBy(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                'overtime_requests.status'
            )
            ->whereNotNull('overtime_requests.approved_atasan_by')
            ->whereNotNull('overtime_requests.approved_hrd_by')
            ->whereNull('overtime_requests.rejected_atasan_by')
            ->whereNull('overtime_requests.rejected_hrd_by')
            ->whereNull('overtime_requests.rejected_finance_by')
            ->whereYear('overtime_requests.start_date', $request->periode)
            ->get()
            ->transform(function ($item) {
                $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
                return $item;
            });

        return Datatables::of($data)->make(true);
    }

    public function indexUnprocessedFinance(Request $request)
    {
        $data = OvertimeRequest::on('intilab_apps')
            ->leftJoin('intilab_apps.overtime_request_members as fd', 'fd.no_document', '=', 'overtime_requests.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'overtime_requests.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.employee_id', '=', 'u.id')
            ->select(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                                WHEN overtime_requests.status = "Approved Atasan" THEN "Approve Atasan" 
                                WHEN overtime_requests.status = "Approved HRD" THEN "Approved HRD" 
                                WHEN overtime_requests.status = "Approved Finance" THEN "Approved Finance" 
                                WHEN overtime_requests.status = "Rejected Atasan" THEN "Rejected" 
                                WHEN overtime_requests.status = "Rejected HRD" THEN "Rejected HRD" 
                                WHEN overtime_requests.status = "Rejected Finance" THEN "Rejected Finance" 
                                ELSE "Pending" 
                            END as status'),

                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw("COUNT(DISTINCT fd.employee_id) as total_karyawan"),

                'overtime_requests.approved_hrd_by',
                'overtime_requests.approved_hrd_at',
                'overtime_requests.approved_finance_by',
                'overtime_requests.approved_finance_at',

                'overtime_requests.start_date as tanggal',
                'overtime_requests.start_time as jam_mulai',
                'overtime_requests.end_time as jam_selesai',

                'overtime_requests.created_by as nama_pengaju',
                'overtime_requests.created_at as diajukan_pada',
                'overtime_requests.description as keterangan'

            )
            ->groupBy(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                'overtime_requests.status'
            )
            ->whereNotNull('overtime_requests.approved_atasan_by')
            ->whereNotNull('overtime_requests.approved_hrd_by')
            ->whereNull('overtime_requests.approved_finance_by')
            ->whereNull('overtime_requests.rejected_atasan_by')
            ->whereNull('overtime_requests.rejected_hrd_by')
            ->whereNull('overtime_requests.rejected_finance_by')
            ->whereYear('overtime_requests.start_date', $request->periode)
            ->get()
            ->transform(function ($item) {
                $item->karyawan = array_map('json_decode', explode('|', $item->karyawan));
                return $item;
            });


        return Datatables::of($data)->make(true);
    }


    public function indexProcessedFinance(Request $request)
    {
        $data = OvertimeRequest::on('intilab_apps')
            ->leftJoin('intilab_apps.overtime_request_members as fd', 'fd.no_document', '=', 'overtime_requests.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'overtime_requests.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.employee_id', '=', 'u.id')
            ->select(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                                WHEN overtime_requests.status = "Approved Atasan" THEN "Approve Atasan" 
                                WHEN overtime_requests.status = "Approved HRD" THEN "Approved HRD" 
                                WHEN overtime_requests.status = "Approved Finance" THEN "Approved Finance" 
                                WHEN overtime_requests.status = "Rejected Atasan" THEN "Rejected" 
                                WHEN overtime_requests.status = "Rejected HRD" THEN "Rejected HRD" 
                                WHEN overtime_requests.status = "Rejected Finance" THEN "Rejected Finance" 
                                ELSE "Pending" 
                            END as status'),

                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw("COUNT(DISTINCT fd.employee_id) as total_karyawan"),

                'overtime_requests.approved_hrd_by',
                'overtime_requests.approved_hrd_at',
                'overtime_requests.approved_finance_by',
                'overtime_requests.approved_finance_at',

                'overtime_requests.start_date as tanggal',
                'overtime_requests.start_time as jam_mulai',
                'overtime_requests.end_time as jam_selesai',

                'overtime_requests.created_by as nama_pengaju',
                'overtime_requests.created_at as diajukan_pada',
                'overtime_requests.description as keterangan'

            )
            ->groupBy(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                'overtime_requests.status'
            )
            ->whereNotNull('overtime_requests.approved_atasan_by')
            ->whereNotNull('overtime_requests.approved_hrd_by')
            ->whereNotNull('overtime_requests.approved_finance_by')
            ->whereNull('overtime_requests.rejected_atasan_by')
            ->whereNull('overtime_requests.rejected_hrd_by')
            ->whereNull('overtime_requests.rejected_finance_by')
            ->whereYear('overtime_requests.start_date', $request->periode)
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
        $data = OvertimeRequest::on('intilab_apps')
            ->leftJoin('intilab_apps.overtime_request_members as fd', 'fd.no_document', '=', 'overtime_requests.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'overtime_requests.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.employee_id', '=', 'u.id')
            ->select(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                                WHEN overtime_requests.status = "Approved Atasan" THEN "Approve Atasan" 
                                WHEN overtime_requests.status = "Approved HRD" THEN "Approved HRD" 
                                WHEN overtime_requests.status = "Approved Finance" THEN "Approved Finance" 
                                WHEN overtime_requests.status = "Rejected Atasan" THEN "Rejected" 
                                WHEN overtime_requests.status = "Rejected HRD" THEN "Rejected HRD" 
                                WHEN overtime_requests.status = "Rejected Finance" THEN "Rejected Finance" 
                                ELSE "Pending" 
                            END as status'),

                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw("COUNT(DISTINCT fd.employee_id) as total_karyawan"),

                'overtime_requests.approved_hrd_by',
                'overtime_requests.approved_hrd_at',
                'overtime_requests.approved_atasan_by',
                'overtime_requests.approved_atasan_at',
                'overtime_requests.rejected_atasan_by',
                'overtime_requests.rejected_atasan_at',
                'overtime_requests.approved_finance_by',
                'overtime_requests.approved_finance_at',

                'overtime_requests.start_date as tanggal',
                'overtime_requests.start_time as jam_mulai',
                'overtime_requests.end_time as jam_selesai',

                'overtime_requests.created_by as nama_pengaju',
                'overtime_requests.created_at as diajukan_pada',
                'overtime_requests.description as keterangan'

            )
            ->groupBy(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                'overtime_requests.status'
            )
            ->whereNull('overtime_requests.approved_finance_by')
            ->whereNull('overtime_requests.approved_hrd_by')
            ->whereIn('overtime_requests.created_by', $bawahan)
            ->whereYear('overtime_requests.start_date', $request->periode)
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
        $data = OvertimeRequest::on('intilab_apps')
            ->leftJoin('intilab_apps.overtime_request_members as fd', 'fd.no_document', '=', 'overtime_requests.no_document')
            ->leftJoin('intilab_produksi.master_divisi as d', 'd.id', '=', 'overtime_requests.department_id')
            ->leftJoin('intilab_produksi.master_karyawan as u', 'fd.employee_id', '=', 'u.id')
            ->select(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                DB::raw('CASE 
                                WHEN overtime_requests.status = "Approved Atasan" THEN "Approve Atasan" 
                                WHEN overtime_requests.status = "Approved HRD" THEN "Approved HRD" 
                                WHEN overtime_requests.status = "Approved Finance" THEN "Approved Finance" 
                                WHEN overtime_requests.status = "Rejected Atasan" THEN "Rejected" 
                                WHEN overtime_requests.status = "Rejected HRD" THEN "Rejected HRD" 
                                WHEN overtime_requests.status = "Rejected Finance" THEN "Rejected Finance" 
                                ELSE "Pending" 
                            END as status'),

                DB::raw("GROUP_CONCAT(DISTINCT CONCAT('{\"id\": \"', u.id, '\", \"nama\": \"', u.nama_lengkap, '\", \"jabatan\": \"', u.grade, '\"}') SEPARATOR '|') as karyawan"),
                DB::raw("COUNT(DISTINCT fd.employee_id) as total_karyawan"),

                'overtime_requests.approved_hrd_by',
                'overtime_requests.approved_hrd_at',
                'overtime_requests.approved_atasan_by',
                'overtime_requests.approved_atasan_at',
                'overtime_requests.rejected_atasan_by',
                'overtime_requests.rejected_atasan_at',
                'overtime_requests.approved_finance_by',
                'overtime_requests.approved_finance_at',

                'overtime_requests.start_date as tanggal',
                'overtime_requests.start_time as jam_mulai',
                'overtime_requests.end_time as jam_selesai',

                'overtime_requests.created_by as nama_pengaju',
                'overtime_requests.created_at as diajukan_pada',
                'overtime_requests.description as keterangan'

            )
            ->groupBy(
                'overtime_requests.id',
                'overtime_requests.no_document',
                'd.nama_divisi',
                'overtime_requests.status'
            )
            ->where(function ($query) {
                $query->whereNotNull('overtime_requests.approved_finance_by')
                    ->orWhereNotNull('overtime_requests.approved_hrd_by');
            })
            ->whereIn('overtime_requests.created_by', $bawahan)
            ->whereYear('overtime_requests.start_date', $request->periode)
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
        $getBawahan = GetBawahan::where('id', $this->user_id)->get();
        $getAtasan = GetAtasan::where('id', $this->user_id)->get();
        $allKaryawan = collect([$getBawahan, $getAtasan])
            ->flatten()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nama_lengkap' => $item->nama_lengkap,
                ];
            })
            ->unique('id')
            ->values();

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
            $header = OvertimeRequest::on('intilab_apps')->find($request->id);

            if (!$header) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $exist = OvertimeRequestMembers::on('intilab_apps')
                ->leftJoin('intilab_apps.overtime_requests as h', 'h.no_document', '=', 'overtime_request_members.no_document')
                ->where('h.start_date', $request->tanggal_lembur)
                ->whereIn('overtime_request_members.employee_id', $request->data)
                ->whereNull('h.rejected_atasan_by')
                ->where('h.no_document', '!=', $header->no_document)
                ->get();

            if ($exist->count() > 0) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Anggota telah memiliki form lembur pada tanggal tersebut, Mohon di cek kembali',
                ], 400);
            }

            $header->update([
                'department_id' => $this->department,
                'start_date' => $request->tanggal_lembur,
                'end_date' => !empty($request->tanggal_selesai) ? $request->tanggal_selesai : ($request->tanggal_lembur ?? null),
                'start_time' => $request->jam_mulai,
                'end_time' => $request->jam_selesai,
                'description' => $request->keterangan,
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            OvertimeRequestMembers::on('intilab_apps')->where('no_document', $header->no_document)->delete();

            $details = [];
            foreach ($request->data as $detail) {
                $details[] = [
                    'overtime_request_id' => $header->id,
                    'no_document' => $header->no_document,
                    'employee_id' => $detail,
                    'created_by' => $this->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_by' => $this->karyawan,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'is_active' => 1
                ];
            }
            OvertimeRequestMembers::on('intilab_apps')->insert($details);

            $title = 'Request Lembur Kamu Berhasil Diperbaharui!';
            $body = $this->grade === 'MANAGER'
                ? 'Cek secara berkala untuk persetujuan HRD'
                : 'Cek secara berkala untuk persetujuan atasan';

            self::sendNotificationLembur([$this->user_id], $title, $body);

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
            $exist = OvertimeRequestMembers::on('intilab_apps')
                ->leftJoin('intilab_apps.overtime_requests as h', 'h.no_document', '=', 'overtime_request_members.no_document')
                ->where('h.start_date', $request->tanggal_lembur)
                ->whereIn('overtime_request_members.employee_id', $request->data)
                ->whereNull('h.rejected_atasan_by')
                ->get();

            if ($exist->count() > 0) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'Anggota telah memiliki form lembur pada tanggal tersebut, Mohon di cek kembali',
                ], 400);
            }

            $no_document = str_replace('.', '/', microtime(true));
            $status = $this->grade === 'MANAGER' ? 'Approved Atasan' : 'Pending';
            $header = OvertimeRequest::on('intilab_apps')->create([
                'no_document' => $no_document,
                'department_id' => $this->department,
                'start_date' => $request->tanggal_lembur,
                'end_date' => !empty($request->tanggal_selesai) ? $request->tanggal_selesai : ($request->tanggal_lembur ?? null),
                'start_time' => $request->jam_mulai,
                'end_time' => $request->jam_selesai,
                'description' => $request->keterangan,
                'status' => $status,
                'approved_atasan_by' => $this->grade === 'MANAGER' ? $this->karyawan : null,
                'approved_atasan_at' => $this->grade === 'MANAGER' ? Carbon::now()->format('Y-m-d H:i:s') : null,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'is_active' => 1
            ]);

            $details = [];
            foreach ($request->data as $detail) {
                $details[] = [
                    'overtime_request_id' => $header->id,
                    'no_document' => $no_document,
                    'employee_id' => $detail,
                    'created_by' => $this->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_by' => $this->karyawan,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'is_active' => 1
                ];
            }
            OvertimeRequestMembers::on('intilab_apps')->insert($details);

            $sendNotifTo = [];
            if ($this->grade === 'MANAGER') {
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
                ->url('/request/request-lembur')
                ->send();

            if ($this->grade !== 'MANAGER') {
                $atasan = GetAtasan::where('id', $this->user_id)->get()->pluck('id')->toArray();
                Notification::where('id', $atasan)
                    ->title('Lembur divisi ' . $this->department . ' Menunggu Persetujuan!')
                    ->message('Mohon approve sebelum jam 4 sore')
                    ->url('/request/request-lembur')
                    ->send();
            }

            $users = collect($request->data)
                ->filter(fn($id) => (int) $id !== (int) $this->user_id)
                ->values()
                ->toArray();

            $creator = [$this->user_id];

            $title = 'Request Lembur Kamu Berhasil Dibuat!';

            $body = $this->grade === 'MANAGER'
                ? 'Cek secara berkala untuk persetujuan HRD'
                : 'Cek secara berkala untuk persetujuan atasan';

            self::sendNotificationLembur($creator, $title, $body);

            self::sendNotificationLembur(
                $users,
                'WOOHOOO!, Kamu termasuk dalam tim lembur pada ' . Carbon::parse($request->tanggal_lembur)->formatLocalized('%d %B %Y', 'id_ID') . '!',
                'Waktu: ' . $request->jam_mulai . ' - ' . $request->jam_selesai . ' WIB (' . $request->keterangan . ')',
            );

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
        $latestDocument = OvertimeRequest::on('intilab_apps')->where('no_document', 'LIKE', $no_document . '%')->orderBy('no_document', 'DESC')->first();

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
            $formHeader = OvertimeRequest::on('intilab_apps')->where('id', $request->id)->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formHeader->status = 'Approved HRD';
            $formHeader->approved_hrd_by = $this->karyawan;
            $formHeader->approved_hrd_at = Carbon::now()->format('Y-m-d H:i:s');
            $formHeader->rejected_finance_by = null;
            $formHeader->rejected_finance_at = null;
            $formHeader->save();

            $userId = GetAtasan::where('nama_lengkap', $formHeader->created_by)->get()->pluck('id')->toArray();

            $message = 'Form lembur telah di approve';

            Notification::whereIn('id', $userId)
                ->title('Form Lembur')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/request/request-lembur')
                ->send();

            $title = 'Request Lembur Kamu Telah disetujui HRD!';
            $body = 'Cek secara berkala untuk persetujuan Finance';

            self::sendNotificationLembur([$this->user_id], $title, $body);

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
            $formHeader = OvertimeRequest::on('intilab_apps')->where('id', $request->id)->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formHeader->status = 'Rejected HRD';
            $formHeader->rejected_hrd_by = $this->karyawan;
            $formHeader->rejected_hrd_at = Carbon::now()->format('Y-m-d H:i:s');
            $formHeader->approved_atasan_at = null;
            $formHeader->approved_atasan_by = null;
            $formHeader->reject_hrd_reason = $request->keterangan;
            $formHeader->save();

            $message = 'Form lembur telah di reject';
            $userId = GetAtasan::where('nama_lengkap', $formHeader->created_by)->get()->pluck('id')->toArray();

            Notification::whereIn('id', $userId)
                ->title('Form Lembur')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/request/request-lembur')
                ->send();

            $title = 'Request Lembur Kamu Tidak disetujui oleh HRD!';
            $body = 'karena ' . $request->keterangan;

            self::sendNotificationLembur([$this->user_id], $title, $body);

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
            $formHeader = OvertimeRequest::on('intilab_apps')->where('id', $request->id)->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formHeader->status = 'Approved Atasan';
            $formHeader->approved_atasan_by = $this->karyawan;
            $formHeader->approved_atasan_at = Carbon::now()->format('Y-m-d H:i:s');
            $formHeader->save();

            $message = 'Form lembur telah di approve';
            $userId = GetAtasan::where('nama_lengkap', $formHeader->created_by)->get()->pluck('id')->toArray();

            Notification::whereIn('id', $userId)
                ->title('Form Lembur')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/request/request-lembur')
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
            $formHeader = OvertimeRequest::on('intilab_apps')->where('id', $request->id)->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formHeader->status = 'Rejected Atasan';
            $formHeader->rejected_atasan_by = $this->karyawan;
            $formHeader->rejected_atasan_at = Carbon::now()->format('Y-m-d H:i:s');
            $formHeader->reject_atasan_reason = $request->keterangan;
            $formHeader->save();

            $message = 'Form lembur telah di reject';
            $userId = GetAtasan::where('nama_lengkap', $formHeader->created_by)->get()->pluck('id')->toArray();
            Notification::whereIn('id', $userId)
                ->title('Form Lembur')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/request/request-lembur')
                ->send();

            $title = 'Request Lembur Kamu Tidak disetujui HRD!';
            $body = 'Karena ' . $request->keterangan;

            self::sendNotificationLembur([$this->user_id], $title, $body);
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
            $formHeader = OvertimeRequest::on('intilab_apps')->where('id', $request->id)->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formHeader->status = 'Approved Finance';
            $formHeader->approved_finance_by = $this->karyawan;
            $formHeader->approved_finance_at = Carbon::now()->format('Y-m-d H:i:s');
            $formHeader->save();

            $message = 'Form lembur telah di approve';
            $userId = GetAtasan::where('nama_lengkap', $formHeader->created_by)->get()->pluck('id')->toArray();
            Notification::whereIn('id', $userId)
                ->title('Form Lembur')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/request/request-lembur')
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
            $formHeader = OvertimeRequest::on('intilab_apps')->where('id', $request->id)->first();

            if (!$formHeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Form Lembur tidak ditemukan'
                ], 404);
            }

            $formHeader->status = 'Rejected Finance';
            $formHeader->rejected_finance_by = $this->karyawan;
            $formHeader->rejected_finance_at = Carbon::now()->format('Y-m-d H:i:s');
            $formHeader->approved_hrd_at = null;
            $formHeader->approved_hrd_by = null;
            $formHeader->reject_finance_reason = $request->keterangan;
            $formHeader->save();

            $message = 'Form lembur telah di reject';
            $userId = GetAtasan::where('nama_lengkap', $formHeader->created_by)->get()->pluck('id')->toArray();
            Notification::whereIn('id', $userId)
                ->title('Form Lembur')
                ->message($message . ' Oleh ' . $this->karyawan)
                ->url('/request/request-lembur')
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

    public function testNotif()
    {
        try {
            self::sendNotificationLembur([601], 'test', 'test');

            return response()->json([
                'success' => true
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error' => 'Error: ' . $th->getMessage()
            ], 500);
        }
    }

    private function sendNotificationLembur($users, $title, $body)
    {
        $payload = [
            'data[title]' => $title,
            'data[body]'  => $body,
            'data[url]'   => '/form-lembur',
            'data[type]'  => 'lembur',
        ];

        foreach ($users as $index => $userId) {
            $payload["users[$index]"] = $userId;
        }

        Http::asForm()
            ->withHeaders([
                'Accept' => 'application/json',
                'x-slice' => env('APPS_NOTIFICATION_SLICE'),
            ])
            ->withToken(env('APPS_INTERNAL_TOKEN'))
            ->post('https://apps.intilab.com/android-attendance/api/route', $payload);
    }
}
