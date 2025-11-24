<?php

namespace App\Http\Controllers\api;

use App\Helpers\HelperSatuan;
use App\Models\DetailMicrobiologi;
use App\Models\HistoryAppReject;

use App\Models\MicrobioHeader;
use App\Models\MasterBakumutu;
use App\Models\OrderDetail;
use App\Models\ParameterFdl;
use App\Models\WsValueUdara;

use App\Models\LhpsMicrobiologiHeader;
use App\Models\LhpsMicrobiologiDetailSampel;
use App\Models\LhpsMicrobiologiDetailParameter;

use App\Models\Subkontrak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

use Carbon\Carbon;
use Yajra\Datatables\Datatables;


class TqcUdaraMicrobiologiController extends Controller
{
    public function index()
    {
        $parameterFdl = ParameterFdl::where('is_active', true)->where('kategori', '4-Udara')->where('nama_fdl', 'microbiologi')->select('parameters')->first();
        $parameterList = json_decode($parameterFdl->parameters);
        $kategori3List = [
            '27-Udara Lingkungan Kerja',
            '12-Udara Angka Kuman',
            '33-Mikrobiologi Udara',
        ];
        $data = OrderDetail::select(
            'cfr',
            DB::raw('MAX(id) as max_id'),
            'nama_perusahaan',
            'no_quotation',
            'no_order',
            DB::raw('GROUP_CONCAT(DISTINCT no_sampel SEPARATOR ", ") as no_sampel'),
            DB::raw('GROUP_CONCAT(DISTINCT kategori_3 SEPARATOR ", ") as kategori_3'),
            DB::raw('GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ", ") as tanggal_sampling'),
            DB::raw('GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ", ") as tanggal_terima'),
            'kategori_1',
            'konsultan',
            'regulasi',
            'parameter',
        )
            ->where('is_active', true)
            ->where('status', 1)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', $kategori3List)
            ->where(function ($query) use ($parameterList) {
                foreach ($parameterList as $param) {
                    $query->orWhere('parameter', 'LIKE', "%;$param%");
                }
            })
            ->groupBy('cfr', 'nama_perusahaan', 'no_quotation', 'no_order', 'kategori_1', 'konsultan', "regulasi", "parameter")
            ->orderBy('max_id', 'desc');

        return Datatables::of($data)
            ->filter(function ($query) {
                foreach (request('columns', []) as $col) {
                    $name = $col['data'] ?? null;
                    $search = $col['search']['value'] ?? null;

                    if ($search && in_array($name, [
                        'no_sampel',
                        'kategori_3',
                        'tanggal_sampling',
                        'tanggal_terima',
                    ])) {
                        $query->whereRaw("EXISTS (
                            SELECT 1 FROM order_detail od
                            WHERE od.cfr = order_detail.cfr
                            AND od.{$name} LIKE ?
                        )", ["%{$search}%"]);
                    }
                }
            })
            ->make(true);
    }

    public function getDetail(Request $request)
    {
        $orderDetails = OrderDetail::where('cfr', $request->cfr)
            ->where('status', 1)
            ->where('is_active', 1)
            ->get();

        $data      = [];
        $getSatuan = new HelperSatuan;

        foreach ($orderDetails as $orderDetail) {
            $header = MicrobioHeader::with('ws_value')
                ->where('no_sampel', $orderDetail->no_sampel)
                ->get();

            if ($header->isEmpty()) {
                continue;
            }

            // 2. Ambil id_regulasi dari field regulasi di OrderDetail
            $id_regulasi = null;

            if (! empty($orderDetail->regulasi)) {
                $regArr = json_decode($orderDetail->regulasi, true);

                if (is_array($regArr) && count($regArr) > 0) {
                    $first       = $regArr[0];              // "143-Peraturan ...."
                    $parts       = explode('-', $first, 2); // ["143", "Peraturan ...."]
                    $id_regulasi = (int) $parts[0];
                }
            }

            foreach ($header as $item) {
                $bakuMutu = MasterBakumutu::where("id_parameter", $item->id_parameter)
                    ->where('id_regulasi', $id_regulasi)
                    ->where('is_active', 1)
                    ->select('baku_mutu', 'satuan', 'method', 'nama_header')
                    ->first();

                $ws = $item->ws_udara; 

                $nilai = '-';

                if ($ws) {
                    $hasil = $ws->toArray();

                    $index = $getSatuan->udara($bakuMutu->satuan ?? null);

                    if ($index === null) {
                        // cari f_koreksi_1..17 dulu
                        for ($i = 1; $i <= 17; $i++) {
                            $key = "f_koreksi_$i";
                            if (isset($hasil[$key]) && $hasil[$key] !== '' && $hasil[$key] !== null) {
                                $nilai = $hasil[$key];
                                break;
                            }
                        }

                        // kalau masih kosong, cari hasil1..17
                        if ($nilai === '-' || $nilai === null || $nilai === '') {
                            for ($i = 1; $i <= 17; $i++) {
                                $key = "hasil$i";
                                if (isset($hasil[$key]) && $hasil[$key] !== '' && $hasil[$key] !== null) {
                                    $nilai = $hasil[$key];
                                    break;
                                }
                            }
                        }
                    } else {
                        $fKoreksiHasil = "f_koreksi_$index";
                        $fhasil        = "hasil$index";

                        if (isset($hasil[$fKoreksiHasil]) && $hasil[$fKoreksiHasil] !== '' && $hasil[$fKoreksiHasil] !== null) {
                            $nilai = $hasil[$fKoreksiHasil];
                        } elseif (isset($hasil[$fhasil]) && $hasil[$fhasil] !== '' && $hasil[$fhasil] !== null) {
                            $nilai = $hasil[$fhasil];
                        }
                    }
                }

                $data[] = [
                    'no_sampel'   => $orderDetail->no_sampel,
                    'parameter'   => $item->parameter,
                    'baku_mutu'   => $bakuMutu->baku_mutu ?? null,
                    'satuan'      => $bakuMutu->satuan ?? null,
                    'method'      => $bakuMutu->method ?? null,
                    'nama_header' => $bakuMutu->nama_header ?? null,
                    'nilai_uji'   => $nilai,
                    'verifikator' => $item->approved_by ?? null,
                ];
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Berhasil mendapatkan data',
            'data'    => $data,
        ], 200);

    }
    // public function getDetail(Request $request)
    // {
    //     // Ambil semua order detail berdasarkan CFR
    //     $orderDetails = OrderDetail::where('cfr', $request->cfr)
    //         ->where('status', 1)
    //         ->where('is_active', 1)
    //         ->get();

    //     // =============================
    //     // 1. Kumpulkan ID Regulasi
    //     // =============================
    //     $idRegulasi = $orderDetails->flatMap(function ($detail) {
    //         $reg = json_decode($detail->regulasi, true);
    //         if (is_array($reg) && isset($reg[0])) {
    //             return [ explode('-', $reg[0])[0] ];
    //         }
    //         return [];
    //     })->unique()->values()->toArray();


    //     // =============================
    //     // 2. Persiapan data output
    //     // =============================
    //     $data = [];

    //     foreach ($orderDetails as $orderDetail) {

    //         $MicrobiologiHeader = MicrobioHeader::where('no_sampel', $orderDetail->no_sampel)->first();

    //         // history sampel
    //         $lhpsMicrobiologiDetail = LhpsMicrobiologiDetailSampel::where(
    //             'lokasi_keterangan',
    //             $orderDetail->keterangan_1
    //         )
    //         ->pluck('hasil_uji')
    //         ->toArray();

    //         // baku mutu berdasarkan parameter dan semua regulasi yang dikumpulkan
    //         $bakuMutu = MasterBakumutu::where("id_parameter", $MicrobiologiHeader->id_parameter)
    //             ->whereIn('id_regulasi', $idRegulasi)
    //             ->where('is_active', 1)
    //             ->select('baku_mutu', 'satuan', 'method', 'nama_header')
    //             ->first();

    //         $data[] = [
    //             'no_sampel'     => $orderDetail->no_sampel,
    //             'history'       => $lhpsMicrobiologiDetail,
    //             'parameter'     => optional($MicrobiologiHeader)->parameter,
    //             'hasil'         => optional(WsValueUdara::where('no_sampel', $orderDetail->no_sampel)->orderByDesc('id')->first())->hasil9,
    //             'analyst'       => optional($MicrobiologiHeader)->created_by,
    //             'approved_by'   => optional($MicrobiologiHeader)->approved_by,
    //             'satuan'        => $bakuMutu->satuan ?? null,
    //             'baku_mutu'     => $bakuMutu->baku_mutu ?? null,
    //             'method'        => $bakuMutu->method ?? null,
    //             'nama_header'   => $bakuMutu->nama_header ?? null,
    //         ];
    //     }

    //     return response()->json([
    //         'data' => $data,
    //         'message' => 'Data retrieved successfully',
    //     ], 200);
    // }


    public function handleApproveSelected(Request $request){
        OrderDetail::whereIn('no_sampel', $request->no_sampel_list)->update(['status' => 2]);

        return response()->json([
            'message' => 'Data berhasil diapprove.',
            'success' => true,
        ], 200);
    }

    public function handleRejectSelected(Request $request){
        OrderDetail::whereIn('no_sampel', $request->no_sampel_list)->update(['status' => 0]);

        return response()->json([
            'message' => 'Data berhasil direject.',
            'success' => true,
        ], 200);
    }
}
