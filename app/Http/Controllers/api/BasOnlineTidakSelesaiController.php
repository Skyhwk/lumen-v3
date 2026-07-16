<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class BasOnlineTidakSelesaiController extends Controller
{
    /**
     * Menampilkan data sampel yang tidak selesai.
     * 
     * Query:
     * SELECT 
     *     psh.no_quotation AS no_qt,
     *     sts.no_order, 
     *     psh.nama_perusahaan,
     *     psh.tanggal_sampling,
     *     psh.sampler_jadwal,
     *     GROUP_CONCAT(sts.no_sampel SEPARATOR ', ') AS nosampel_tidak_selesai
     * FROM persiapan_sampel_header psh
     * INNER JOIN sampel_tidak_selesai sts ON psh.id = sts.id_persiapan 
     * WHERE psh.is_active = 1 
     * GROUP BY psh.id, psh.no_quotation, sts.no_order, psh.nama_perusahaan, 
     *          psh.tanggal_sampling, psh.sampler_jadwal
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('persiapan_sampel_header as psh')
                ->join('sampel_tidak_selesai as sts', 'psh.id', '=', 'sts.id_persiapan')
                ->select([
                    'psh.no_quotation as no_qt',
                    'sts.no_order',
                    'psh.nama_perusahaan',
                    'psh.tanggal_sampling',
                    'psh.sampler_jadwal',
                    DB::raw("GROUP_CONCAT(sts.no_sampel SEPARATOR ', ') AS nosampel_tidak_selesai")
                ])
                ->where('psh.is_active', 1);

            // Filter berdasarkan range tanggal (dari frontend)
            if ($request->has('periode_awal') && $request->has('periode_akhir')) {
                $query->whereBetween('psh.tanggal_sampling', [
                    $request->periode_awal,
                    $request->periode_akhir
                ]);
            }

            $query->groupBy([
                    'psh.id',
                    'psh.no_quotation',
                    'sts.no_order',
                    'psh.nama_perusahaan',
                    'psh.tanggal_sampling',
                    'psh.sampler_jadwal'
                ]);

            return DataTables::of($query)
                ->filterColumn('no_qt', function ($query, $keyword) {
                    $query->where('psh.no_quotation', 'like', "%{$keyword}%");
                })
                ->filterColumn('no_order', function ($query, $keyword) {
                    $query->where('sts.no_order', 'like', "%{$keyword}%");
                })
                ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                    $query->where('psh.nama_perusahaan', 'like', "%{$keyword}%");
                })
                ->filterColumn('tanggal_sampling', function ($query, $keyword) {
                    $query->where('psh.tanggal_sampling', 'like', "%{$keyword}%");
                })
                ->filterColumn('sampler_jadwal', function ($query, $keyword) {
                    $query->where('psh.sampler_jadwal', 'like', "%{$keyword}%");
                })
                ->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine()
            ], 500);
        }
    }
}
