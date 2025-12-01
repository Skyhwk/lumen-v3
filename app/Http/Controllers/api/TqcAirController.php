<?php

namespace App\Http\Controllers\api;

use App\Models\HistoryAppReject;
use App\Models\OrderDetail;
use App\Models\LhpsAirHeader;
use App\Models\HistoryWsValueAir;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class TqcAirController extends Controller
{
    public function index(Request $request)
    {
        $data = OrderDetail::with('wsValueAir', 'dataLapanganAir')->where('is_active', true)->where('status', 1)->where('kategori_2', '1-Air')->orderBy('id', 'desc');
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
                    'menu' => 'TQC Air',
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

    public function trenData(Request $request)
    {

        try {
            $data_order = OrderDetail::with('wsValueAir', 'dataLapanganAir')->where('no_sampel', $request->no_sampel)->where('is_active', true)->first();

            if ($data_order != null) {
                $dataLhps = LhpsAirHeader::with('lhpsAirDetail')
                    ->where('nama_pelanggan', $data_order->nama_perusahaan)
                    ->where('sub_kategori', explode('-', $data_order->kategori_3)[1])
                    ->where('deskripsi_titik', 'like', '%' . $data_order->keterangan_1 . '%')
                    ->whereNotNull('tanggal_sampling')
                    ->when($data_order->tanggal_terima != null, fn($query) => $query->where('tanggal_sampling', '<=', $data_order->tanggal_terima))
                    ->where('is_active', true)
                    ->get();

                $data = [];
                foreach (json_decode($data_order->parameter) as $key => $value) {
                    $hasil1 = '';
                    $hasil2 = '';
                    $hasil3 = '';
                    $add_by = '';
                    $approve_by = '';
                    $hasil_koreksi = '';
                    $hasil_json = '';

                    $parameter = explode(';', $value)[1];
                    if ($data_order->wsValueAir != null) {
                        foreach ($data_order->wsValueAir as $key2 => $value2) {
                            // var_dump($value2->para);
                            if ($value2->id_colorimetri != null && $value2->colorimetri->parameter == $parameter && $value2->colorimetri->lhps == 1) {
                                $hasil1 = $value2->hasil;
                                $hasil2 = $value2->hasil_2;
                                $hasil3 = $value2->hasil_3;
                                $hasil_koreksi = $value2->faktor_koreksi;
                                $add_by = $value2->colorimetri->created_by;
                                $approve_by = $value2->colorimetri->approved_by;
                                $hasil_json = $value2->hasil_json;
                            } else if ($value2->id_gravimetri != null && $value2->gravimetri->parameter == $parameter && $value2->gravimetri->lhps == 1) {
                                $hasil1 = $value2->hasil;
                                $hasil2 = $value2->hasil_2;
                                $hasil3 = $value2->hasil_3;
                                $hasil_koreksi = $value2->faktor_koreksi;
                                $add_by = $value2->gravimetri->created_by;
                                $approve_by = $value2->gravimetri->approved_by;
                                $hasil_json = $value2->hasil_json;
                            } else if ($value2->id_titrimetri != null && $value2->titrimetri->parameter == $parameter && $value2->titrimetri->lhps == 1) {
                                $hasil1 = $value2->hasil;
                                $hasil2 = $value2->hasil_2;
                                $hasil3 = $value2->hasil_3;
                                $hasil_koreksi = $value2->faktor_koreksi;
                                $add_by = $value2->titrimetri->created_by;
                                $approve_by = $value2->titrimetri->approved_by;
                                $hasil_json = $value2->hasil_json;
                            } else if ($value2->id_subkontrak != null && $value2->subkontrak->parameter == $parameter && $value2->subkontrak->lhps == 1) {
                                $hasil1 = $value2->hasil;
                                $hasil2 = $value2->hasil_2;
                                $hasil3 = $value2->hasil_3;
                                $hasil_koreksi = $value2->faktor_koreksi;
                                $add_by = $value2->subkontrak->created_by;
                                $approve_by = $value2->subkontrak->approved_by;
                                $hasil_json = $value2->hasil_json;
                            }
                        }
                    }

                    if ($data_order->dataLapanganAir != null) {
                        if ($parameter == 'Suhu') {
                            $hasil1 = $data_order->dataLapanganAir->suhu_air;
                        } else if ($parameter == 'pH') {
                            $hasil1 = $data_order->dataLapanganAir->ph;
                        }
                    }

                    $trend = [];
                    if ($dataLhps->isNotEmpty()) {
                        foreach ($dataLhps as $key3 => $value3) {
                            if ($value3->lhpsAirDetail != null) {
                                foreach ($value3->lhpsAirDetail as $key4 => $value4) {
                                    if ($value4->parameter_lab == $parameter) {
                                        array_push($trend, $value4->hasil_uji);
                                    }
                                }
                            }
                        }
                    }

                    array_push($data, (object) [
                        'no_sample' => $data_order->no_sampel,
                        'parameter' => $parameter,
                        'add_by' => $add_by,
                        'approve_by' => $approve_by,
                        'hasil1' => $hasil1,
                        'hasil2' => $hasil2,
                        'hasil3' => $hasil3,
                        'hasil_koreksi' => $hasil_koreksi,
                        'trend_hasil' => $trend,
                        'hasil_json' => $hasil_json != '' ? json_decode($hasil_json, true) : null
                    ]);
                }

                return response()->json([
                    'status' => 'true',
                    'data' => $data
                ], 201);

            }
        } catch (\Throwable $th) {
            dd($th);
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan ' . $th->getMessage()
            ]);
        }
    }

    public function getHistoryHasil(Request $request)
    {
        // dd($request->all());
        $data = HistoryWsValueAir::where('no_sampel', $request->no_sampel)
            ->where('parameter', $request->parameter)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $data,
            'success' => true,
            'message' => 'Data berhasil diambil'
        ], 200);
    }
}