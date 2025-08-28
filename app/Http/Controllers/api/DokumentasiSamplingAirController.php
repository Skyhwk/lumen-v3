<?php

namespace App\Http\Controllers\api;

use App\Models\{DataLapanganAir, DataLapanganKecerahan, DataLapanganLapisanMinyak}; // Air Model
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DokumentasiSamplingAirController extends Controller
{
    public function index(Request $request)
    {
        // $data = DB::table('order_detail')
        //     ->select(
        //         'order_detail.no_sampel',
        //         'order_detail.no_order',
        //         'order_detail.no_quotation',
        //         'order_detail.tanggal_sampling',
        //         'order_detail.nama_perusahaan'
        //     )
        //     ->whereDate('tanggal_sampling', '<=', Carbon::now()->format('Y-m-d'))
        //     ->where('kategori_2', '1-Air')
        //     ->where('order_detail.is_active', true);
        // return Datatables::of($data)
        //     ->orderColumn('tanggal_sampling', function ($query, $order) {
        //         $query->orderBy('tanggal_sampling', $order);
        //     })
        //     ->orderColumn('created_at', function ($query, $order) {
        //         $query->orderBy('created_at', $order);
        //     })
        //     ->orderColumn('no_sampel', function ($query, $order) {
        //         $query->orderBy('no_sampel', $order);
        //     })
        //     ->filter(function ($query) use ($request) {
        //         if ($request->has('columns')) {
        //             $columns = $request->get('columns');
        //             foreach ($columns as $column) {
        //                 if (isset($column['search']) && !empty($column['search']['value'])) {
        //                     $columnName = $column['name'] ?: $column['data'];
        //                     $searchValue = $column['search']['value'];

        //                     // Skip columns that aren't searchable
        //                     if (isset($column['searchable']) && $column['searchable'] === 'false') {
        //                         continue;
        //                     }

        //                     $query->where($columnName, 'like', '%' . $searchValue . '%');
        //                 }
        //             }
        //         }
        //     })
        //     ->make(true);



        $data = OrderDetail::whereDate('tanggal_sampling', '<=', Carbon::now()->format('Y-m-d'))
            ->where('kategori_2', '1-Air')
            ->where('order_detail.is_active', true)
            ->whereNotNull('tanggal_terima')
            ->orderBy('tanggal_sampling', 'desc');

        return Datatables::of($data)
            ->orderColumn('tanggal_terima', function ($query, $order) {
                $query->orderBy('tanggal_terima', $order);
            })
            ->orderColumn('created_at', function ($query, $order) {
                $query->orderBy('created_at', $order);
            })
            ->orderColumn('no_sampel', function ($query, $order) {
                $query->orderBy('no_sampel', $order);
            })
            ->filter(function ($query) use ($request) {
                if ($request->has('columns')) {
                    $columns = $request->get('columns');
                    foreach ($columns as $column) {
                        if (isset($column['search']) && !empty($column['search']['value'])) {
                            $columnName = $column['name'] ?: $column['data'];
                            $searchValue = $column['search']['value'];

                            if (isset($column['searchable']) && $column['searchable'] === 'false') {
                                continue;
                            }

                            $query->where($columnName, 'like', '%' . $searchValue . '%');
                        }
                    }
                }
            })
            ->make(true);


        // $dplAir = DataLapanganAir::with('detail')->orderBy('created_at', 'desc')->get();
        // $dplKecerahan = DataLapanganKecerahan::with('detail')->orderBy('created_at', 'desc')->get();
        // $dplLapisanMinyak = DataLapanganLapisanMinyak::with('detail')->orderBy('created_at', 'desc')->get();

        // $dpl = $dplAir->merge($dplKecerahan)->merge($dplLapisanMinyak)->values();

        // dd($dpl);

        // return Datatables::of($dpl)->make(true);
    }

    public function showDokumentasi(Request $request)
    {
        $data = [];
        $dlp_air = DataLapanganAir::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_air as $d) {
            $data['data_lapangan_air'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_kondisi_sampel' => $d->foto_kondisi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_kecerahan = DataLapanganKecerahan::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_kecerahan as $d) {
            $data['data_lapangan_kecerahan'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_aktifitas_sampling' => $d->foto_aktifitas_sampling
                ]
            ];
        }

        $dlp_lapisan_minyak = DataLapanganLapisanMinyak::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_lapisan_minyak as $d) {
            $data['data_lapangan_lapisan_minyak'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_selatan' => $d->foto_lokasi_selatan,
                    'foto_lokasi_utara' => $d->foto_lokasi_utara,
                    'foto_lokasi_timur' => $d->foto_lokasi_timur,
                    'foto_lokasi_barat' => $d->foto_lokasi_barat
                ]
            ];
        }

        return response()->json([
            'data' => $data
        ], 200);
    }
}
