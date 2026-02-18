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

        $query = DB::table('order_header as oh')
            ->select(
                DB::raw('MAX(oh.id) as last_id'),
                DB::raw("SUBSTRING_INDEX(oh.wilayah, '-', -1) as wilayah"),

                DB::raw('JSON_ARRAYAGG(
                    JSON_OBJECT(
                        "no_document", oh.no_document,
                        "nama_perusahaan", oh.nama_perusahaan
                    )
                ) as quotations'),

                DB::raw('COUNT(oh.id) as total_order'),
            )
            ->where('oh.flag_status', 'ordered')
            ->whereYear('oh.tanggal_order', $tahun)
            ->where('oh.is_active', true)
            ->groupBy(DB::raw("SUBSTRING_INDEX(oh.wilayah, '-', -1)"))
            ->orderBy('last_id', 'desc');
        


        return DataTables::of($query)
            ->editColumn('quotations', function ($row) {
                return $row->quotations ? json_decode($row->quotations) : [];
            })
            ->make(true);
    }
}
