<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Datatables;

use App\Models\MasterPelanggan;
use App\Models\KontakPelanggan;
use App\Models\AlamatPelanggan;
use App\Models\PicPelanggan;
use App\Models\MasterKaryawan;
use App\Models\HargaTransportasi;
use App\Models\HistoryPerubahanSales;
use App\Models\KontakPelangganBlacklist;
use App\Models\MasterPelangganBlacklist;
use App\Models\OrderHeader;
use App\Models\request_quotationKontrakD;
use Illuminate\Support\Carbon;

date_default_timezone_set('Asia/Jakarta');

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $tahun = (int) ($request->tahun ?? Carbon::now()->year);

        $monthCases = [];
        $totalExprParts = [];

        for ($m = 1; $m <= 12; $m++) {
            $expr = "
            SUM(
                CASE 
                    WHEN MONTH(COALESCE(qkd.periode_kontrak, qn.created_at)) = $m
                    THEN oh.biaya_akhir
                    ELSE 0
                END
            )
        ";

            $monthCases[] = DB::raw("$expr as bulan_$m");
            $totalExprParts[] = $expr;
        }

        $totalTahunExpr = implode(" + ", $totalExprParts);

        $query = DB::table('order_header as oh')
            ->leftJoin('request_quotation_kontrak_H as qk', 'qk.no_document', '=', 'oh.no_document')
            ->leftJoin('request_quotation_kontrak_D as qkd', 'qkd.id_request_quotation_kontrak_h', '=', 'qk.id')
            ->leftJoin('request_quotation as qn', 'qn.no_document', '=', 'oh.no_document')
            ->where('oh.is_active', 1)
            ->whereBetween(
                DB::raw('COALESCE(qkd.periode_kontrak, qn.created_at)'),
                ["$tahun-01-01", "$tahun-12-31"]
            )
            ->where(function ($w) {
                $w
                    // ✅ kalau ada kontrak header, harus ordered
                    ->where(function ($x) {
                        $x->whereNotNull('qk.id')
                            ->where('qk.flag_status', 'ordered')
                            // kalau ada qkd juga, pastiin ordered juga
                            ->where(function ($y) {
                                $y->whereNull('qkd.id');
                            });
                    })
                    // ✅ kalau nggak ada kontrak header, fallback ke non-kontrak
                    ->orWhereNull('qk.id');
            })
            ->select(array_merge(
                [
                    'oh.id_pelanggan',
                    DB::raw('MAX(oh.nama_perusahaan) as nama_perusahaan'),
                    DB::raw('MAX(oh.wilayah) as wilayah'),
                ],
                $monthCases,
                [DB::raw("($totalTahunExpr) as total_tahun")]
            ))
            ->groupBy('oh.id_pelanggan')
            ->orderBy('total_tahun', 'desc');


        return \DataTables::of($query)->make(true);
    }
}
