<?php

namespace App\Http\Controllers\api;

use App\Helpers\HelperSatuan;
use App\Models\DetailLingkunganHidup;
use App\Models\DirectLainHeader;
use App\Models\HistoryAppReject;

use App\Models\LingkunganHeader;
use App\Models\PartikulatHeader;
use App\Models\MasterBakumutu;
use App\Models\OrderDetail;


use App\Models\Subkontrak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MdlUdara;
use App\Models\Parameter;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class TqcUdaraLingkunganHidupController extends Controller

{
    public function index(Request $request)
    {
        $data = OrderDetail::where('is_active', true)
            ->where('status', 1)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', ["11-Udara Ambient"])
            ->orderBy('tanggal_terima');

        return Datatables::of($data)->make(true);
    }

    public function approveData(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = OrderDetail::where('id', $request->id)->first();
            if ($data) {
                $data->status = 2;
                $data->save();
                HistoryAppReject::insert([
                    'no_lhp' => $data->cfr,
                    'no_sampel' => $data->no_sampel,
                    'kategori_2' => $data->kategori_2,
                    'kategori_3' => $data->kategori_3,
                    'menu' => 'TQC Udara',
                    'status' => 'approve',
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->karyawan
                ]);
                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Data tqc no sample ' . $data->no_sampel . ' berhasil diapprove'
                ]);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage()
            ]);
        }
    }

    public function rejectData(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = OrderDetail::where('id', $request->id)->first();
            if ($data) {
                $data->status = 0;
                $data->save();
                DB::commit();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Data tqc no sample ' . $data->no_sampel . ' berhasil direject'
                ]);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage()
            ]);
        }
    }


    public function detail(Request $request)
    {
        try {
            $directData = DirectLainHeader::with(['ws_udara'])
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approve', 1)
                ->where('status', 0)
                ->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
                ->addSelect(DB::raw("'direct' as data_type"))
                ->get();

            $lingkunganData = LingkunganHeader::with('ws_udara', 'ws_value_linkungan')
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approved', 1)
                ->where('status', 0)
                ->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approved', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
                ->addSelect(DB::raw("'lingkungan' as data_type"))
                ->get();
            $subkontrak = Subkontrak::with(['ws_value_linkungan'])
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approve', 1)
                ->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'lhps as status', 'is_active')
                ->addSelect(DB::raw("'subKontrak' as data_type"))
                ->get();
            $partikulat = PartikulatHeader::with(['ws_udara'])
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approve', 1)
                ->where('is_active', 1)
                ->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'lhps as status', 'is_active')
                ->addSelect(DB::raw("'partikulat' as data_type"))
                ->get();


            $combinedData = collect()
                ->merge($lingkunganData)
                ->merge($subkontrak)
                ->merge($directData)
                ->merge($partikulat);


            $processedData = $combinedData->map(function ($item) {
                switch ($item->data_type) {
                    case 'lingkungan':
                        $item->source = 'Lingkungan';
                        break;
                    case 'subKontrak':
                        $item->source = 'Subkontrak';
                        break;
                    case 'direct':
                        $item->source = 'Direct Lain';
                        break;
                    case 'partikulat':
                        $item->source = 'Partikulat';
                        break;
                }
                return $item;
            });
            // $id_regulasi = explode("-", json_decode($request->regulasi)[0])[0];
            $id_regulasi = $request->regulasi;
            $getSatuan = new HelperSatuan;

            $parameters = $processedData->map(fn($item) => ['id' => $item->id_parameter ?: Parameter::where(['id_kategori' => 4, 'nama_lab' => $item->parameter, 'is_active' => true])->first()->id, 'parameter' => $item->parameter]);
            $mdlUdara = MdlUdara::whereIn('parameter_id', $parameters->pluck('id'))->get();
            
            $getHasilUji = function ($index, $parameterId, $hasilUji) use ($mdlUdara) {
                if ($hasilUji && $hasilUji !== "-" && !str_contains($hasilUji, '<')) {
                    $colToSearch = "hasil" . ($index ?: 1);
                    $mdlUdara = $mdlUdara->where('parameter_id', $parameterId)->whereNotNull($colToSearch)->first();
                    if ($mdlUdara && (float) $mdlUdara->$colToSearch > (float) $hasilUji) {
                        $hasilUji = "<" . $mdlUdara->$colToSearch;
                    }
                }

                return $hasilUji;
            };

            foreach ($processedData as $item) {
                $dataLapangan = DetailLingkunganHidup::where('no_sampel', $item->no_sampel)
                    ->select('durasi_pengambilan')
                    ->where('parameter', $item->parameter)
                    ->first();
                $bakuMutu = MasterBakumutu::where("parameter", $item->parameter)
                    ->where('id_regulasi', $id_regulasi)
                    ->where('is_active', 1)
                    ->select('baku_mutu', 'satuan', 'method')
                    ->first();

                $item->durasi = $dataLapangan->durasi_pengambilan ?? null;
                $item->satuan = $bakuMutu->satuan ?? null;
                $item->baku_mutu = $bakuMutu->baku_mutu ?? null;
                $item->method = $bakuMutu->method ?? null;
                $item->nama_header = $bakuMutu->nama_header ?? null;

                $hasil = $item->ws_udara ?? $item->ws_value_linkungan ?? null;
                if ($hasil != null) {
                    $hasil = $hasil->toArray();
                    $index = $getSatuan->udara($item->satuan);
                    $nilai = null;

                    if ($index == null) {
                        for ($i = 0; $i <= 16; $i++) {
                            $key = $i === 0 ? 'f_koreksi_c' : "f_koreksi_c$i";
                            if (isset($hasil[$key]) && !empty($hasil[$key])) {
                                $nilai = $hasil[$key];
                                break;
                            }
                        }
                        if (empty($nilai)) {
                            for ($i = 0; $i <= 16; $i++) {
                                $key = $i === 0 ? 'C' : "C$i";
                                if (isset($hasil[$key]) && !empty($hasil[$key])) {
                                    $nilai = $hasil[$key];
                                    break;
                                }
                            }
                        }

                        if (empty($nilai)) {
                            for ($i = 1; $i <= 17; $i++) {
                                $key = "f_koreksi_$i";
                                if (isset($hasil[$key]) && !empty($hasil[$key])) {
                                    $nilai = $hasil[$key];
                                    break;
                                }
                            }
                        }

                        if (empty($nilai)) {
                            for ($i = 1; $i <= 17; $i++) {
                                $key = "hasil$i";
                                if (isset($hasil[$key]) && !empty($hasil[$key])) {
                                    $nilai = $hasil[$key];
                                    break;
                                }
                            }
                        }
                        
                    } else {
                        $fKoreksiKey = "f_koreksi_c$index";
                        $hasilKey    = "C$index";
                        $fKoreksiHasil = "f_koreksi_$index";
                        $fhasil = "hasil$index";
                        $nilai = null;

                        if($index == 17) {
                            $nilai = $hasil[$fKoreksiKey] ?? $hasil[$hasilKey] ??  $hasil[$fKoreksiHasil] ??  $hasil[$fhasil] ?? null;
                            if($nilai == null) {
                                $nilai = $hasil['f_koreksi_c2'] ?? $hasil['C2'] ??  $hasil['f_koreksi_2'] ??  $hasil['hasil2'] ?? '-';
                            }
                        } else if ($index == 16) {
                            $nilai = $hasil[$fKoreksiKey] ?? $hasil[$hasilKey] ??  $hasil[$fKoreksiHasil] ??  $hasil[$fhasil] ?? null;
                            if($nilai == null) {
                                $nilai = $hasil['f_koreksi_c1'] ?? $hasil['C1'] ??  $hasil['f_koreksi_1'] ??  $hasil['hasil1'] ?? '-';
                            }
                        } else if ($index == 15) {
                            $nilai = $hasil[$fKoreksiKey] ?? $hasil[$hasilKey] ??  $hasil[$fKoreksiHasil] ??  $hasil[$fhasil] ?? null;
                            if($nilai == null) {
                                $nilai = $hasil['f_koreksi_c3'] ?? $hasil['C3'] ??  $hasil['f_koreksi_3'] ??  $hasil['hasil3'] ?? '-';
                            }
                        } else {
                            $nilai = $hasil[$fKoreksiKey] ?? $hasil[$hasilKey] ??  $hasil[$fKoreksiHasil] ??  $hasil[$fhasil] ?? null;
                            if($nilai == null) {
                                $nilai = $hasil['f_koreksi_c1'] ?? $hasil['C1'] ??  $hasil['f_koreksi_1'] ??  $hasil['hasil1'] ?? '-';
                            }
                        }
                    }

                    $item->nilai_uji = $getHasilUji($index, $item->id_parameter ?: Parameter::where(['id_kategori' => 4, 'nama_lab' => $item->parameter])->first()->id, $nilai);
                } else {
                    $item->nilai_uji = '-';
                }
            }


            return Datatables::of($processedData)->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function detailLapangan(Request $request)
    {
        try {
            $data = DetailLingkunganHidup::where('no_sampel', $request->no_sampel)->first();
            if ($data) {
                return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
            } else {
                return response()->json(['message' => 'Data lapangan tidak ditemukan', 'success' => false, 'status' => 404]);
            }
        } catch (\Exception $ex) {
            dd($ex);
        }
    }

    public function handleApproveSelected(Request $request)
    {
        DB::beginTransaction();
        try {
            OrderDetail::whereIn('no_sampel', $request->no_sampel_list)
                ->update([
                    'status' => 2,
                ]);

            DB::commit();
            return response()->json([
                'message' => 'Data berhasil diapprove.',
                'success' => true,
                'status' => 200,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mengapprove data: ' . $th->getMessage(),
                'success' => false,
                'status' => 500,
            ], 500);
        }
    }

    public function handleRejectSelected(Request $request)
    {
        DB::beginTransaction();
        try {

            OrderDetail::whereIn('no_sampel', $request->no_sampel_list)
                ->update([
                    'status' => 0,
                ]);

            DB::commit();
            return response()->json([
                'message' => 'Data berhasil direject.',
                'success' => true,
                'status' => 200,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mereject data: ' . $th->getMessage(),
                'success' => false,
                'status' => 500,
            ], 500);
        }
    }
}
