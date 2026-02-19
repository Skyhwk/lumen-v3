<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;




class SummaryCustomerController extends Controller
{
    public function index(Request $request)
    {
        $tahun = request()->tahun;
        $search = $request->input('search.value');

        $subSql = "
            SELECT 
                MAX(oh.id) as id,
                MAX(oh.id) as last_id,
                oh.id_pelanggan,
                mp.nama_pelanggan,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'no_document', oh.no_document,
                        'biaya_akhir', (oh.biaya_akhir - IFNULL(oh.total_ppn, 0))
                    )
                ) as quotations,
                COUNT(oh.id) as total_order,
                SUM(oh.biaya_akhir - IFNULL(oh.total_ppn, 0)) as total_biaya
            FROM order_header oh
            INNER JOIN master_pelanggan mp ON mp.id_pelanggan = oh.id_pelanggan
            WHERE oh.flag_status = 'ordered'
                AND YEAR(oh.tanggal_order) = ?
                AND oh.is_active = 1
                AND mp.is_active = 1
            GROUP BY oh.id_pelanggan, mp.nama_pelanggan
        ";

        $bindings = [$tahun];

        $query = DB::table(DB::raw("($subSql) as sub"))
            ->select('*')
            ->addBinding($bindings, 'where')
            ->orderByDesc('total_biaya');

        if ($search) {
            $searchLower = '%' . strtolower($search) . '%';
            $query->where(function ($q) use ($searchLower) {
                $q->whereRaw('LOWER(sub.nama_pelanggan) LIKE ?', [$searchLower])
                ->orWhereRaw('LOWER(sub.id_pelanggan) LIKE ?', [$searchLower]);
            });
        }

        return DataTables::of($query)
            ->skipTotalRecords()
            ->editColumn('quotations', function ($row) {
                $data = json_decode($row->quotations, true) ?? [];
                usort($data, function ($a, $b) {
                    return $b['biaya_akhir'] <=> $a['biaya_akhir'];
                });
                return $data;
            })
            ->make(true);
    }

}