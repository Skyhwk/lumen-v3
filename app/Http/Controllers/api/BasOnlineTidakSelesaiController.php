<?php

namespace App\Http/Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class BasOnlineTidakSelesaiController extends Controller
{
    /**
     * Menampilkan data sampel yang tidak selesai.
     *
     * Kolom detail_bas_documents bertipe JSON array.
     * Setiap update BAS menambahkan entry baru ke akhir array.
     * Maka index terakhir = dokumen BAS terbaru.
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('sampel_tidak_selesai as sts')
                ->leftJoin('persiapan_sampel_header as psh', 'psh.id', '=', 'sts.id_persiapan')
                ->select([
                    'psh.no_quotation as no_qt',
                    'sts.no_order',
                    'psh.nama_perusahaan',
                    'psh.tanggal_sampling',
                    'psh.sampler_jadwal',
                    'psh.detail_bas_documents',
                    DB::raw("GROUP_CONCAT(CONCAT(sts.no_sampel, ' (', COALESCE(NULLIF(sts.keterangan, ''), sts.alasan, '-'), ')') SEPARATOR ', ') AS nosampel_tidak_selesai")
                ])
                ->where(function ($q) {
                    $q->whereNull('sts.alasan')
                        ->orWhere('sts.alasan', '!=', 'Sample di pick up');
                })
                ->where(function ($q) {
                    $q->where('psh.is_active', 1)
                        ->orWhereNull('sts.id_persiapan');
                });

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
                'psh.sampler_jadwal',
                'psh.detail_bas_documents',
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
                ->addColumn('latest_bas_filename', function ($row) {
                    if (empty($row->detail_bas_documents)) {
                        return null;
                    }
                    $docs = json_decode($row->detail_bas_documents, true);
                    if (!is_array($docs) || count($docs) === 0) {
                        return null;
                    }
                    $last = end($docs);
                    return $last['filename'] ?? null;
                })
                ->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line'    => $ex->getLine()
            ], 500);
        }
    }
}
