<?php

namespace App\Http\Controllers\api;

// namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Models
use App\Models\OrderDetail;
use App\Models\ScanSampelTc;
use App\Models\ScanSampelAnalis;

// external package
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class SummaryParameterController extends Controller
{
    public function index(Request $request)
    {
        // dd($request->all());
        $data = DB::table('summary_parameter as sp')
            ->leftJoin('master_harga_parameter as mhp', 'sp.id_parameter', '=', 'mhp.id_parameter')
            ->select('sp.*', 'mhp.harga as harga')
            ->where('sp.tahun', $request->year)
            ->get();
        return DataTables::of($data)->make(true);
    }

    public function detailSummary(Request $request)
    {
        try {

            $models = [
                \App\Models\Colorimetri::class,
                \App\Models\Titrimetri::class,
                \App\Models\Gravimetri::class,
                \App\Models\Subkontrak::class,
            ];

            $date = Carbon::createFromFormat('Y-m', $request->periode);

            $parameterName = explode(';', $request->parameter)[1];

            // =========================
            // GET ORDER DETAIL
            // =========================

            $data = OrderDetail::with([
                    'scan_tc:no_sampel,created_at',
                    'scan_analis:no_sampel,created_at',
                    'TrackingSatu:no_sample,ftc_verifier,ftc_laboratory',
                    'wsValueAir'
                ])
                ->where(
                    'parameter',
                    'like',
                    '%"' . $request->parameter . '"%'
                )
                ->select(
                    'id',
                    'no_sampel',
                    'kategori_1',
                    'kategori_3',
                    'tanggal_sampling',
                    'tanggal_terima'
                )
                ->whereMonth('tanggal_sampling', $date->month)
                ->whereYear('tanggal_sampling', $date->year)
                ->where('is_active', 1)
                ->get();

            // =========================
            // AMBIL SEMUA NO SAMPEL
            // =========================

            $noSampels = $data->pluck('no_sampel')->unique()->values();

            // =========================
            // LOAD SEMUA DATA ANALIS SEKALI
            // =========================

            $analisMap = collect();

            foreach ($models as $model) {

                $rows = $model::whereIn('no_sampel', $noSampels)
                    ->where('parameter', $parameterName)
                    ->where('is_active', 1)
                    ->select(
                        'no_sampel',
                        'created_at',
                        'approved_at'
                    )
                    ->get();

                foreach ($rows as $row) {

                    // gunakan no_sampel sebagai key
                    if (!$analisMap->has($row->no_sampel)) {

                        $analisMap[$row->no_sampel] = [
                            'tanggal_input_analis' => $row->created_at,
                            'tanggal_approved_analis' => $row->approved_at,
                        ];
                    }
                }
            }

            // =========================
            // MAPPING KE DATA
            // =========================

            foreach ($data as $item) {

                $item->tanggal_input_analis = null;
                $item->tanggal_approved_analis = null;

                if ($analisMap->has($item->no_sampel)) {

                    $item->tanggal_input_analis =
                        $analisMap[$item->no_sampel]['tanggal_input_analis'];

                    $item->tanggal_approved_analis =
                        $analisMap[$item->no_sampel]['tanggal_approved_analis'];
                }
            }

            return response()->json([
                'data' => $data,
                'message' => 'Data berhasil diambil.',
                'status' => true
            ], 200);

        } catch (\Throwable $th) {

            return response()->json([
                'error' => $th->getMessage(),
                'status' => false
            ], 401);
        }
    }
}
