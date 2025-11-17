<?php

namespace App\Http\Controllers\api;

use App\Helpers\HelperSatuan;
use App\Models\DetailMicrobiologi;
use App\Models\HistoryAppReject;

use App\Models\MicrobioHeader;
use App\Models\MasterBakumutu;
use App\Models\OrderDetail;
use App\Models\ParameterFdl;


use App\Models\Subkontrak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class TqcUdaraMicrobiologiController extends Controller
{
    public function index(Request $request)
    {
        $parameterFdl = ParameterFdl::where('is_active', true)->where('kategori', '4-Udara')->where('nama_fdl', 'microbiologi')->select('parameters')->first();
        $parameterList = json_decode($parameterFdl->parameters);
        $kategori3List = [
            '27-Udara Lingkungan Kerja',
            '12-Udara Angka Kuman',
            '33-Mikrobiologi Udara',
        ];

        $data = OrderDetail::where('is_active', true)
            ->where('status', 1)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', $kategori3List)
            ->where(function ($query) use ($parameterList) {
                foreach ($parameterList as $param) {
                    $query->orWhere('parameter', 'LIKE', "%;$param%");
                }
            })
            ->get();

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
            $microbio = MicrobioHeader::with(['ws_udara'])
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approved', 1)
                ->where('status', 0)
                ->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approved', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
                ->addSelect(DB::raw("'microbio' as data_type"))
                ->get();

            // $id_regulasi = explode("-", json_decode($request->regulasi)[0])[0];
            $id_regulasi = $request->regulasi;
            $getSatuan = new HelperSatuan;
            foreach ($microbio as $item) {

                $dataLapangan = DetailMicrobiologi::where('no_sampel', $item->no_sampel)
                    ->select('shift_pengambilan')
                    ->where('parameter', $item->parameter)
                    ->first();

                $bakuMutu = MasterBakumutu::where("id_parameter", $item->id_parameter)
                    ->where('id_regulasi', $id_regulasi)
                    ->where('is_active', 1)
                    ->select('baku_mutu', 'satuan', 'method', 'nama_header')
                    ->first();

                $item->durasi = $dataLapangan->shift_pengambilan ?? null;
                $item->satuan = $bakuMutu->satuan ?? null;
                $item->baku_mutu = $bakuMutu->baku_mutu ?? null;
                $item->method = $bakuMutu->method ?? null;
                $item->nama_header = $bakuMutu->nama_header ?? null;

                $hasil = $item->ws_udara ?? null;
                if ($hasil != null) {
                    $hasil = $hasil->toArray();
                    $index = $getSatuan->udara($item->satuan);
                    $nilai = null;
                    if ($index == null) {
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
                        $fKoreksiHasil = "f_koreksi_$index";
                        $fhasil = "hasil$index";
                        $nilai = null;

                        if($index == 17) {
                            $nilai =   $hasil[$fKoreksiHasil] ??  $hasil[$fhasil] ?? null;
                            if($nilai == null) {
                                $nilai = $hasil['f_koreksi_2'] ??  $hasil['hasil2'] ?? '-';
                            }
                        } else if ($index == 16) {
                            $nilai =   $hasil[$fKoreksiHasil] ??  $hasil[$fhasil] ?? null;
                            if($nilai == null) {
                                $nilai =$hasil['f_koreksi_1'] ??  $hasil['hasil1'] ?? '-';
                            }
                        } else if ($index == 15) {
                            $nilai =   $hasil[$fKoreksiHasil] ??  $hasil[$fhasil] ?? null;
                            if($nilai == null) {
                                $nilai = $hasil['f_koreksi_3'] ??  $hasil['hasil3'] ?? '-';
                            }
                        } else {
                            $nilai =   $hasil[$fKoreksiHasil] ??  $hasil[$fhasil] ?? null;
                            if($nilai == null) {
                                $nilai = $hasil['f_koreksi_1'] ??  $hasil['hasil1'] ?? '-';
                            }
                        }
                    }

                    $item->nilai_uji = $nilai;
                } else {
                    $item->nilai_uji = '-';
                }
            }

            return Datatables::of($microbio)->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function detailLapangan(Request $request)
    {
        try {
            $data = DetailMicrobiologi::where('no_sampel', $request->no_sampel)->first();
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
