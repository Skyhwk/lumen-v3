<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;




class RegionOrderController extends Controller
{
    public function index(Request $request)
    {
        $tahun = $request->tahun;
        $search = $request->input('search.value');

        $subSql = "
            SELECT 
                MAX(oh.id) as id,
                MAX(oh.id) as last_id,
                SUBSTRING_INDEX(oh.wilayah, '-', -1) as wilayah,
                JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'no_document', oh.no_document,
                        'nama_perusahaan', oh.nama_perusahaan
                    )
                ) as quotations,
                COUNT(oh.id) as total_order
            FROM order_header oh
            WHERE oh.flag_status = 'ordered'
                AND YEAR(oh.tanggal_order) = ?
                AND oh.is_active = 1
            GROUP BY SUBSTRING_INDEX(oh.wilayah, '-', -1)
        ";

        $bindings = [$tahun];

        if ($search) {
            $searchLower = '%' . strtolower($search) . '%';
            $subSql .= ""; // search ditaruh di outer query
        }

        $query = DB::table(DB::raw("($subSql) as sub"))
            ->select('*')
            ->addBinding($bindings, 'where');

        if ($search) {
            $searchLower = '%' . strtolower($search) . '%';
            $query->whereRaw('LOWER(sub.wilayah) LIKE ?', [$searchLower])
                ->orWhereRaw('LOWER(sub.quotations) LIKE ?', [$searchLower]);
        }

        return DataTables::of($query)
        ->order(function ($query) use ($request) {

            $orderColumn = $request->input(
                'columns.' . $request->input('order.0.column') . '.data'
            );

            $orderDir = $request->input('order.0.dir');

            $allowed = ['wilayah', 'total_order'];

            if (in_array($orderColumn, $allowed)) {
                $query->orderBy("sub.$orderColumn", $orderDir);
            } else {
                $query->orderByDesc('sub.total_order'); // default
            }
        })
        ->editColumn('quotations', function ($row) {
            return $row->quotations ? json_decode($row->quotations) : [];
        })
        ->make(true);
    }
}
