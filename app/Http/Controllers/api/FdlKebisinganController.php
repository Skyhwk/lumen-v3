<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganKebisingan;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\KebisinganHeader;
use App\Models\WsValueUdara;

use App\Services\NotificationFdlService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use App\Models\AnalystFormula as Formula;
use App\Services\AnalystFormula;

class FdlKebisinganController extends Controller
{
    public function indexAll(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganKebisingan::with('detail')->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.tanggal_sampling', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('tanggal_sampling', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel_lama', function ($query, $keyword) {
                $query->where('no_sampel_lama', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.nama_perusahaan', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.kategori_2', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_2', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.kategori_3', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_3', 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);
    }

    public function index(Request $request)
    {
        $data = DataLapanganKebisingan::with('detail')
            ->where('is_blocked', false)
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
            ->orderBy('no_sampel', 'asc');
        return Datatables::of($data)->make(true);
    }

    // public function approve(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {
    //     if (isset($request->id) && $request->id != null) {
    //         $po = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
    //         if ($po) {
    //             // Decode parameter jika dalam format JSON
    //             $decoded = json_decode($po->parameter, true);

    //             // Pastikan JSON ter-decode dengan benar dan berisi data
    //             if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
    //                 // Ambil elemen pertama dari array hasil decode
    //                 $parts = explode(';', $decoded[0] ?? '');

    //                 // Pastikan elemen kedua tersedia setelah explode
    //                 $parameterValue = $parts[1] ?? 'Data tidak valid';

    //                 // dd($parameterValue); // Output: "Kebisingan"
    //             } else {
    //                 dd("Parameter tidak valid atau bukan JSON");
    //             }
    //         } else {
    //             dd("OrderDetail tidak ditemukan");
    //         }

    //         $param = Parameter::where('nama_lab', $parameterValue)->where('id_kategori', 4)->where('is_active', true)->first();
    //         $cekLap = Kebisinganheader::where('no_sampel', $request->no_sampel)->first();
    //         $cekWs = WsValueUdara::where('no_sampel', $request->no_sampel)->first();
    //         $data = DataLapanganKebisingan::where('id', $request->id)->first();
    //         $no_sample = $data->no_sampel;
    //         $jenis_durasi = explode('-', $data->jenis_durasi_sampling)[0];
    //         $countL = DataLapanganKebisingan::where('no_sampel', $no_sample)
    //             ->select('no_sampel', 'value_kebisingan', 'jenis_durasi_sampling')
    //             ->orderBy('jenis_durasi_sampling', 'asc')
    //             ->orderBy('value_kebisingan')
    //             ->get();
    //         $totalL = $countL->count();
    //         $total = [];
    //         $totSesaat = [];

    //         $jumsuhu = DataLapanganKebisingan::where('no_sampel', $no_sample)->sum('suhu_udara');
    //         $jumkelemb = DataLapanganKebisingan::where('no_sampel', $no_sample)->sum('kelembapan_udara');
    //         $reratasuhu = round(floatval($jumsuhu) / $totalL, 1);
    //         $reratakelemb = round(floatval($jumkelemb) / $totalL, 1);
    //         // dd($reratakelemb);
    //         foreach ($countL as $data) {
    //             $total[] = json_decode($data->value_kebisingan);
    //             $totSesaat = json_decode($data->value_kebisingan);
    //         }


    //         for ($i = 0; $i < count($total); $i++) {
    //             $nilaiMin[$i] = min($total[$i]);
    //             $nilaiMax[$i] = max($total[$i]);

    //             $nilai_min = min($nilaiMin);
    //             $nilai_max = max($nilaiMax);
    //         }

    //         if ($jenis_durasi == "24 Jam") {
    //             $getTotalApprove = DataLapanganKebisingan::where('no_sampel', $no_sample)
    //                 ->select(DB::raw('COUNT(no_sampel) AS total'))
    //                 ->where('is_approve', 1)
    //                 ->first();

    //             $LSTotal = [];
    //             $LMTotal = [];
    //             if ($getTotalApprove->total == 6) {
    //                 DB::beginTransaction();
    //                 try {
                        
    //                     $function = 'DirectKebisingan24L7';
    //                     $data_parsing = $request->all();
    //                     $data_parsing = (object) $data_parsing;
    //                     $data_parsing->total = $total;

    //                     // Mulai Kalkulasi
    //                     $data_kalkulasi = AnalystFormula::where('function', $function)
    //                         ->where('data', $data_parsing)
    //                         ->where('id_parameter', $param->id)
    //                         ->process();


    //                     if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
    //                         return (object) [
    //                             'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
    //                             'status' => 404
    //                         ];
    //                     }

    //                     $data_header = KebisinganHeader::where('no_sampel', $request->no_sampel)->first();
    //                     $ws = WsValueUdara::where('no_sampel', $request->no_sampel)->first();
    //                     $data_lapangan = DataLapanganKebisingan::where('id', $request->id)->first();
    //                     if (empty($data_header)) {
    //                         $data_header = new KebisinganHeader;
    //                         $ws = new WsValueUdara;
    //                     }

    //                     $data_header->no_sampel = $request->no_sampel;
    //                     $data_header->id_parameter = $param->id;
    //                     $data_header->parameter = $param->nama_lab;
    //                     $data_header->ls = $data_kalkulasi['totalLSM'];
    //                     $data_header->lm = $data_kalkulasi['rerataLSM'];
    //                     $data_header->leq_ls = $data_kalkulasi['leqLS'];
    //                     $data_header->leq_lm = $data_kalkulasi['leqLM'];
    //                     $data_header->min = $nilai_min;
    //                     $data_header->max = $nilai_max;
    //                     $data_header->suhu_udara = $reratasuhu;
    //                     $data_header->kelembapan_udara = $reratakelemb;
    //                     $data_header->is_approved = true;
    //                     // $data_header->lhps = 1;
    //                     $data_header->approved_by = $this->karyawan;
    //                     $data_header->approved_at = Carbon::now();
    //                     $data_header->created_by = $this->karyawan;
    //                     $data_header->created_at = Carbon::now();
    //                     $data_header->save();

    //                     $ws->id_kebisingan_header = $data_header->id;
    //                     $ws->no_sampel = $request->no_sampel;
    //                     $ws->id_po = $po->id;
    //                     $ws->hasil1 = $data_kalkulasi['hasil'];
    //                     $ws->satuan = $data_kalkulasi['satuan'] ?? null;
    //                     $ws->save();

    //                     $data_lapangan->is_approve = true;
    //                     $data_lapangan->approved_by = $this->karyawan;
    //                     $data_lapangan->approved_at = Carbon::now();
    //                     $data_lapangan->save();

    //                     DB::commit();

    //                     return response()->json([
    //                         'status' => "Berhasil Di Approve",
    //                     ]);
    //                 } catch (Exception $e) {
    //                     DB::rollBack();
    //                     return response()->json([
    //                         'message' => $e . getMessage(),
    //                         'line' => $e . getLine(),
    //                     ]);
    //                 }
    //             } else if ($getTotalApprove->total == 23) {
    //                 try {
    //                     $data_lapangan = DataLapanganKebisingan::where('id', $request->id)->first();

    //                     $data_lapangan = DataLapanganKebisingan::where('id', $request->id)->first();

    //                     $function = Formula::where('id_parameter', $param->id)->where('is_active', true)->first()->function;
    //                     $data_parsing = $request->all();
    //                     $data_parsing = (object) $data_parsing;
    //                     $data_parsing->total = $total;
    //                     $data_kalkulasi = AnalystFormula::where('function', $function)
    //                         ->where('data', $data_parsing)
    //                         ->where('id_parameter', $param->id)
    //                         ->process();

    //                     $updateHeader = [
    //                         'ls' => $data_kalkulasi['leqLS'],
    //                         'lm' => $data_kalkulasi['leqLM'],
    //                         // 'leq_ls' => $data_kalkulasi['leqLS'],
    //                         // 'leq_lm' => $data_kalkulasi['leqLM'],
    //                         'min' => $nilai_min,
    //                         'max' => $nilai_max,
    //                         // 'suhu_udara' => $request->suhu_udara,
    //                         // 'kelembapan_udara' => $request->kelembapan_udara,
    //                         'suhu_udara' => $reratasuhu,
    //                         'kelembapan_udara' => $reratakelemb,
    //                         'created_by' => $this->karyawan,
    //                         'created_at' => Carbon::now(),

    //                     ];
    //                     $updateWs = [
    //                         'hasil1' => $data_kalkulasi['hasil'],
    //                         'hasil2' => $data_kalkulasi['hasil'],
    //                         'satuan' => $data_kalkulasi['satuan'] ?? null,
    //                         'id_kebisingan_header' => $cekLap->id,
    //                         'id_po' => $po->id
    //                     ];
    //                     // Update or Create untuk Header
    //                     KebisinganHeader::updateOrCreate(
    //                         ['no_sampel' => $request->no_sampel], // Kondisi pencarian
    //                         $updateHeader // Data untuk pembaruan atau pembuatan
    //                     );

    //                     // Update or Create untuk WsValueUdara
    //                     WsValueUdara::updateOrCreate(
    //                         ['no_sampel' => $request->no_sampel], // Kondisi pencarian
    //                         $updateWs // Data untuk pembaruan atau pembuatan
    //                     );
    //                     // $no_sample = $dat->no_sample;
    //                     $data_lapangan->is_approve = true;
    //                     $data_lapangan->approved_by = $this->karyawan;
    //                     $data_lapangan->approved_at = Carbon::now();
    //                     $data_lapangan->save();

    //                     DB::commit();

    //                     return response()->json([
    //                         'status' => "Berhasil Di Approve",
    //                         'data' => $data_lapangan,
    //                     ]);
    //                 } catch (Exception $e) {
    //                     DB::rollBack();
    //                     return response()->json([
    //                         'message' => $e . getMessage(),
    //                         'line' => $e . getLine(),
    //                     ], 500);
    //                 }

    //             } else {
    //                 DB::beginTransaction();
    //                 try {
    //                     $data = DataLapanganKebisingan::where('id', $request->id)->first();

    //                     if ($data) {
    //                         $data->is_approve = true;
    //                         $data->approved_by = $this->karyawan; // Pastikan $this->karyawan valid
    //                         $data->approved_at = Carbon::now();   // Pastikan tipe data untuk approved_at sesuai
    //                         $data->save();
    //                     } else {
    //                         // Tangani jika data tidak ditemukan
    //                         Log::error('Data dengan ID ' . $request->id . ' tidak ditemukan.');
    //                         dd('Data tidak ditemukan');
    //                     }
    //                     DB::commit();

    //                     return response()->json([
    //                         'status' => "Berhasil Di Approve",
    //                         'data' => $data,
    //                     ]);

    //                 } catch (Exception $e) {
    //                     DB::rollBack();
    //                     return response()->json([
    //                         'message' => $e . getMessage(),
    //                         'line' => $e . getLine(),
    //                     ], 500);
    //                 }
    //             }
    //         } else if ($jenis_durasi == "8 Jam") {
    //             // $function = Formula::where('id_parameter', $param->id)->where('is_active', true)->first();
    //             // if($function == null){
    //             //     return response()->json([
    //             //         'message' => "Formula Tidak Ditemukan",
    //             //     ], 404);
    //             // }else{
    //             //     $function = $function->function;
    //             // }
    //             // $data_parsing = $request->all();
    //             // $data_parsing = (object) $data_parsing;
    //             // $data_parsing->total = $total;
    //             // $calculate = AnalystFormula::where('function', $function)
    //             //     ->where('data', $data_parsing)
    //             //     ->where('id_parameter', $param->id)
    //             //     ->process();

    //             // $data_header = KebisinganHeader::where('no_sampel', $request->no_sampel)->first();
    //             // $ws = WsValueUdara::where('no_sampel', $request->no_sampel)->first();
    //             // $data_lapangan = DataLapanganKebisingan::where('id', $request->id)->first();
    //             // if (empty($data_header)) {
    //             //     $data_header = new KebisinganHeader;
    //             //     $ws = new WsValueUdara;
    //             // }
    //             // $data_header->no_sampel = $request->no_sampel;
    //             // // $data_header->id_po = $po->id;
    //             // $data_header->id_parameter = $param->id;
    //             // $data_header->parameter = $param->nama_lab;
    //             // $data_header->ls = null;
    //             // $data_header->lm = null;
    //             // $data_header->min = $nilai_min;
    //             // $data_header->max = $nilai_max;
    //             // $data_header->suhu_udara = $reratasuhu;
    //             // $data_header->kelembapan_udara = $reratakelemb;
    //             // // $data_header->lhps = 1;
    //             // $data_header->is_approved = true;
    //             // $data_header->approved_by = $this->karyawan;
    //             // $data_header->approved_at = Carbon::now();
    //             // $data_header->created_by = $this->karyawan;
    //             // $data_header->created_at = Carbon::now();
    //             // $data_header->save();

    //             // $ws->id_kebisingan_header = $data_header->id;
    //             // $ws->no_sampel = $request->no_sampel;
    //             // $ws->id_po = $po->id;
    //             // $ws->hasil1 = $calculate['hasil'];
    //             // $ws->save();
    //             // $data_lapangan->is_approve = true;
    //             // $data_lapangan->approved_by = $this->karyawan;
    //             // $data_lapangan->approved_at = Carbon::now();
    //             // $data_lapangan->save();
    //             // 19-06-2025 - pembacaan total approve dan setting is_approve jika total approve sudah 7
    //             $getTotalApprove = DataLapanganKebisingan::where('no_sampel', $no_sample)
    //                 ->select(DB::raw('COUNT(no_sampel) AS total'))
    //                 ->where('is_approve', 1)
    //                 ->first();
    //             if($getTotalApprove->total == 7){
    //                 DB::beginTransaction();
    //                 try {
    //                     $function = Formula::where('id_parameter', $param->id)->where('is_active', true)->first();
    //                     if($function == null){
    //                         throw new Exception("Formula Tidak Ditemukan");
    //                     }else{
    //                         $function = $function->function;
    //                     }
    //                     $data_parsing = $request->all();
    //                     $data_parsing = (object) $data_parsing;
    //                     $data_parsing->total = $total;
    //                     $calculate = AnalystFormula::where('function', $function)
    //                         ->where('data', $data_parsing)
    //                         ->where('id_parameter', $param->id)
    //                         ->process();
        
    //                     $data_header = KebisinganHeader::where('no_sampel', $request->no_sampel)->first();
    //                     $ws = WsValueUdara::where('no_sampel', $request->no_sampel)->first();
    //                     $data_lapangan = DataLapanganKebisingan::where('id', $request->id)->first();
    //                     if (empty($data_header)) {
    //                         $data_header = new KebisinganHeader;
    //                         $ws = new WsValueUdara;
    //                     }
    //                     $data_header->no_sampel = $request->no_sampel;
    //                     // $data_header->id_po = $po->id;
    //                     $data_header->id_parameter = $param->id;
    //                     $data_header->parameter = $param->nama_lab;
    //                     $data_header->ls = null;
    //                     $data_header->lm = null;
    //                     $data_header->min = $nilai_min;
    //                     $data_header->max = $nilai_max;
    //                     $data_header->suhu_udara = $reratasuhu;
    //                     $data_header->kelembapan_udara = $reratakelemb;
    //                     // $data_header->lhps = 1;
    //                     $data_header->is_approved = true;
    //                     $data_header->approved_by = $this->karyawan;
    //                     $data_header->approved_at = Carbon::now();
    //                     $data_header->created_by = $this->karyawan;
    //                     $data_header->created_at = Carbon::now();
    //                     $data_header->save();
                    
    //                     $ws->id_kebisingan_header = $data_header->id;
    //                     $ws->no_sampel = $request->no_sampel;
    //                     $ws->id_po = $po->id;
    //                     $ws->hasil1 = $calculate['hasil'];
    //                     $ws->satuan = $calculate['satuan'] ?? null;
    //                     $ws->save();

    //                     $data_lapangan->is_approve = 1;
    //                     $data_lapangan->approved_by = $this->karyawan;
    //                     $data_lapangan->approved_at = Carbon::now();
    //                     $data_lapangan->save();
    //                     DB::commit();
    //                 } catch (Exception $e) {
    //                     DB::rollBack();
    //                     return response()->json([
    //                         'message' => $e->getMessage(),
    //                         'line' => $e->getLine(),
    //                     ], 500);
    //                 }
    //             }else{
    //                 DB::beginTransaction();
    //                 try {
    //                     $data = DataLapanganKebisingan::where('id', $request->id)->first();
    //                     if ($data) {
    //                         $data->is_approve = 1;
    //                         $data->approved_by = $this->karyawan; // Pastikan $this->karyawan valid
    //                         $data->approved_at = Carbon::now();   // Pastikan tipe data untuk approved_at sesuai
    //                         $data->save();

    //                         DB::commit();

    //                         return response()->json([
    //                             'status' => "Berhasil Di Approve",
    //                             'data' => $data,
    //                         ]);
    //                     } else {
    //                         DB::rollBack();
    //                         // Tangani jika data tidak ditemukan
    //                         return response()->json([
    //                             'message' => 'Data dengan ID ' . $request->id . ' tidak ditemukan.'
    //                         ], 404);
    //                     }
                        

    //                 } catch (Exception $e) {
    //                     DB::rollBack();
    //                     return response()->json([
    //                         'message' => $e->getMessage(),
    //                         'line' => $e->getLine(),
    //                     ], 500);
    //                 }
    //             }
    //         } else if ($jenis_durasi == "Sesaat") {
                
    //             $function = Formula::where('id_parameter', $param->id)->where('is_active', true)->first()->function;
    //             $data_parsing = $request->all();
    //                     $data_parsing = (object) $data_parsing;
    //                     $data_parsing->totSesaat = $totSesaat;
    //                     $calculate = AnalystFormula::where('function', $function)
    //                         ->where('data', $data_parsing)
    //                         ->where('id_parameter', $param->id)
    //                         ->process();

    //             $data_header = KebisinganHeader::where('no_sampel', $request->no_sampel)->first();
    //             $ws = WsValueUdara::where('no_sampel', $request->no_sampel)->first();
    //             $data_lapangan = DataLapanganKebisingan::where('id', $request->id)->first();

    //             if (empty($data_header)) {
    //                 $data_header = new KebisinganHeader;
    //                 $ws = new WsValueUdara;
    //             }

    //             $data_header->no_sampel = $request->no_sampel;
    //             // $data_header->id_po = $po->id;
    //             $data_header->id_parameter = $param->id;
    //             $data_header->parameter = $param->nama_lab;
    //             $data_header->min = $nilai_min;
    //             $data_header->max = $nilai_max;
    //             $data_header->ls = null;
    //             $data_header->lm = null;
    //             // $data_header->lhps = 1;
    //             $data_header->suhu_udara = $reratasuhu;
    //             $data_header->kelembapan_udara = $reratakelemb;
    //             $data_header->is_approved = true;
    //             $data_header->approved_by = $this->karyawan;
    //             $data_header->approved_at = Carbon::now();
    //             $data_header->created_by = $this->karyawan;
    //             $data_header->created_at = Carbon::now();
    //             $data_header->save();

    //             $ws->id_kebisingan_header = $data_header->id;
    //             $ws->no_sampel = $request->no_sampel;
    //             $ws->id_po = $po->id;
    //             $ws->hasil1 = $calculate['hasil'];
    //             $ws->save();

    //             $data_lapangan->is_approve = true;
    //             $data_lapangan->approved_by = $this->karyawan;
    //             $data_lapangan->approved_at = Carbon::now();
    //             $data_lapangan->save();
    //             // dd($data);
    //         } else {
    //             DB::beginTransaction();
    //             try {
    //                 $data = DataLapanganKebisingan::where('id', $request->id)->first();

    //                 if ($data) {
    //                     $data->is_approve = true;
    //                     $data->approved_by = $this->karyawan; // Pastikan $this->karyawan valid
    //                     $data->approved_at = Carbon::now();   // Pastikan tipe data untuk approved_at sesuai
    //                     $data->save();
    //                 } else {
    //                     // Tangani jika data tidak ditemukan
    //                     Log::error('Data dengan ID ' . $request->id . ' tidak ditemukan.');
    //                     dd('Data tidak ditemukan');
    //                 }
    //                 app(NotificationFdlService::class)->sendApproveNotification("Kebisingan pada shift ($data->jenis_durasi_sampling)", $data->no_sampel, $this->karyawan, $data->created_by);
    //                 DB::commit();

    //                 return response()->json([
    //                     'status' => "Berhasil Di Approve",
    //                     'data' => $data,
    //                 ]);

    //             } catch (Exception $e) {
    //                 DB::rollBack();
    //                 return response()->json([
    //                     'message' => $e . getMessage(),
    //                     'line' => $e . getLine(),
    //                 ], 500);
    //             }
    //         }
    //         app(NotificationFdlService::class)->sendApproveNotification("Kebisingan pada Shift $data_lapangan->jenis_durasi_sampling", $data_lapangan->no_sampel, $this->karyawan, $data_lapangan->created_by);

    //         DB::commit();

    //         return response()->json([
    //             'message' => "Data Lapangan Kebisingan dengan No Sampel {$request->no_sampel} Telah di Approve oleh {$this->karyawan}",
    //             'cat' => 1
    //         ], 200);
    //     } else {
    //         return response()->json([
    //             'message' => "Data Lapangan Kebisingan dengan No Sampel {$request->no_sampel} Telah di Approve oleh {$this->karyawan}"
    //         ], 401);
    //     }
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => $e->getMessage(),
    //             'line' => $e->getLine(),
    //         ], 500);
    //     }

    // }

    public function approve(Request $request)
    {
        DB::beginTransaction();
        try {
            // ==== Validasi Awal ====
            if (empty($request->id)) {
                return response()->json([
                    'message' => "ID tidak ditemukan.",
                ], 400);
            }

            $po = OrderDetail::where('no_sampel', $request->no_sampel)
                ->where('is_active', true)
                ->first();

            if (!$po) {
                throw new Exception("OrderDetail tidak ditemukan untuk no sampel {$request->no_sampel}");
            }

            // ==== Ambil Parameter ====
            $decoded = json_decode($po->parameter, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new Exception("Parameter tidak valid atau bukan JSON");
            }

            $parts = explode(';', $decoded[0] ?? '');
            $parameterValue = $parts[1] ?? null;
            if (!$parameterValue) {
                throw new Exception("Parameter tidak valid di data order detail");
            }

            $param = Parameter::where('nama_lab', $parameterValue)
                ->where('id_kategori', 4)
                ->where('is_active', true)
                ->first();

            if (!$param) {
                throw new Exception("Parameter {$parameterValue} tidak ditemukan pada kategori 4");
            }

            // ==== Data Lapangan ====
            $dataLapangan = DataLapanganKebisingan::find($request->id);
            if (!$dataLapangan) {
                throw new Exception("Data Lapangan tidak ditemukan");
            }

            $no_sample = $dataLapangan->no_sampel;
            $jenis_durasi = explode('-', $dataLapangan->jenis_durasi_sampling)[0];

            $countL = DataLapanganKebisingan::where('no_sampel', $no_sample)
                ->orderBy('jenis_durasi_sampling', 'asc')
                ->get();

            $totalL = $countL->count();
            if ($totalL == 0) {
                throw new Exception("Tidak ada data lapangan untuk no sampel {$no_sample}");
            }

            // ==== Hitung Rata-rata ====
            $reratasuhu = round($countL->avg('suhu_udara'), 1);
            $reratakelemb = round($countL->avg('kelembapan_udara'), 1);

            // ==== Ambil Nilai Min dan Max ====
            $total = [];
            foreach ($countL as $row) {
                $decodedVal = json_decode($row->value_kebisingan, true);
                if (is_array($decodedVal)) {
                    $total[] = $decodedVal;
                }
            }

            $nilaiMin = collect($total)->map(fn($t) => min($t))->min();
            $nilaiMax = collect($total)->map(fn($t) => max($t))->max();

            // ==== Tentukan Fungsi Formula Berdasarkan Durasi ====
            $dataParsing = (object) $request->all();
            $dataParsing->total = $total;
            $approvedCount = DataLapanganKebisingan::where('no_sampel', $no_sample)
                ->where('is_approve', true)
                ->count();

            $function = null;
            
            if ($jenis_durasi === "24 Jam") {
                if ($approvedCount >= 6) {
                    $function = 'DirectKebisingan24L7';
                }
            } elseif ($jenis_durasi === "8 Jam") {
                if ($approvedCount >= 7) {
                    $function = Formula::where('id_parameter', $param->id)
                        ->where('is_active', true)
                        ->value('function');
                }
            } elseif ($jenis_durasi === "Sesaat") {
                $function = Formula::where('id_parameter', $param->id)
                    ->where('is_active', true)
                    ->value('function');
                $dataParsing->totSesaat = json_decode($dataLapangan->value_kebisingan, true);
            }

            // ==== Jika ada formula, jalankan perhitungan ====
            $calculate = null;
            if ($function) {
                $calculate = AnalystFormula::where('function', $function)
                    ->where('data', $dataParsing)
                    ->where('id_parameter', $param->id)
                    ->process();

                if (!is_array($calculate)) {
                    throw new Exception("Formula tidak valid atau belum diimplementasikan");
                }
            }

            // ==== Update atau Buat Header dan WS ====
            $dataHeader = KebisinganHeader::firstOrNew(['no_sampel' => $no_sample]);
            $ws = WsValueUdara::firstOrNew(['no_sampel' => $no_sample]);

            $dataHeader->fill([
                'id_parameter' => $param->id,
                'parameter' => $param->nama_lab,
                'min' => $nilaiMin,
                'max' => $nilaiMax,
                'suhu_udara' => $reratasuhu,
                'kelembapan_udara' => $reratakelemb,
                'ls' => $calculate['totalLSM'] ?? null,
                'lm' => $calculate['rerataLSM'] ?? null,
                'leq_ls' => $calculate['leqLS'] ?? null,
                'leq_lm' => $calculate['leqLM'] ?? null,
                'is_approved' => true,
                'approved_by' => $this->karyawan,
                'approved_at' => Carbon::now(),
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now(),
            ]);
            $dataHeader->save();

            $ws->fill([
                'id_kebisingan_header' => $dataHeader->id,
                'id_po' => $po->id,
                'hasil1' => $calculate['hasil'] ?? null,
                'hasil2' => $calculate['hasil2'] ?? null,
                'satuan' => $calculate['satuan'] ?? null,
            ]);
            $ws->save();

            // ==== Update status Approve ====
            $dataLapangan->update([
                'is_approve' => true,
                'approved_by' => $this->karyawan,
                'approved_at' => Carbon::now(),
            ]);

            // ==== Kirim Notifikasi ====
            app(NotificationFdlService::class)
                ->sendApproveNotification(
                    "Kebisingan pada Shift ({$dataLapangan->jenis_durasi_sampling})",
                    $dataLapangan->no_sampel,
                    $this->karyawan,
                    $dataLapangan->created_by
                );

            DB::commit();

            return response()->json([
                'status' => "Berhasil Di Approve",
                'message' => "Data Lapangan Kebisingan {$no_sample} berhasil diapprove oleh {$this->karyawan}",
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 500);
        }
    }


    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKebisingan::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve = false;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();

            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKebisingan::where('id', $request->id)->first();
            $foto_lokasi = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kondisi = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lokasi)) {
                unlink($foto_lokasi);
            }
            if (is_file($foto_kondisi)) {
                unlink($foto_kondisi);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            // $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->name;
            //     $txt = "FDL AIR dengan No sample $request->no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data no sample ' . $request->no_sampel . ' telah di hapus'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    public function block(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            if ($request->is_blocked == true) {
                $data = DataLapanganKebisingan::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data no sample ' . $data->no_sampel . ' telah di block untuk user'
                ], 200);
            } else {
                $data = DataLapanganKebisingan::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data no sample ' . $data->no_sampel . ' telah di unblock untuk user'
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Melakukan Blocked'
            ], 401);
        }
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganKebisingan::where('no_sampel', $request->no_sampel_lama)->where('no_sampel_lama', null)->get();

                KebisinganHeader::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                WsValueUdara::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                foreach ($data as $item) {
                    $item->no_sampel = $request->no_sampel_baru;
                    $item->no_sampel_lama = $request->no_sampel_lama;
                    $item->updated_by = $this->karyawan;
                    $item->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $item->save(); // Save for each item
                }

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru,
                    'error' => $e->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'No Sampel tidak boleh kosong'
            ], 401);
        }
    }

    public function detail(Request $request)
    {
        $data = DataLapanganKebisingan::with('detail')->where('id', $request->id)->first();

        $this->resultx = 'get Detail sample lapangan Kebisingan success';

        return response()->json([
            'id' => $data->id,
            'no_sample' => $data->no_sampel,
            'no_order' => $data->detail->no_order,
            'sampler' => $data->created_by,
            'jenis_sampel' => explode('-', $data->detail->kategori_3)[1],
            'id_sub_kategori' => explode('-', $data->detail->kategori_3)[0],
            'jam' => $data->waktu,
            'corp' => $data->detail->nama,
            'keterangan' => $data->keterangan,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'titik_koordinat' => $data->titik_koordinat,
            'message' => $this->resultx,
            'info_tambahan' => $data->informasi_tambahan,
            'keterangan' => $data->keterangan,
            'sumber_kebisingan' => $data->sumber_kebisingan,
            'jenis_frekuensi_kebisingan' => $data->jenis_frekuensi_kebisingan,
            'jenis_kategori_kebisingan' => $data->jenis_kategori_kebisingan,
            'jenis_durasi_sampling' => $data->jenis_durasi_sampling,
            'suhu_udara' => $data->suhu_udara,
            'kelembapan_udara' => $data->kelembapan_udara,
            'value_kebisingan' => $data->value_kebisingan,
            'foto_lok' => $data->foto_lokasi_sample,
            'foto_lain' => $data->foto_lain,
            'status' => '200'
        ], 200);
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKebisingan::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Kebisingan pada Shift $data->jenis_durasi_sampling", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganKebisingan::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}