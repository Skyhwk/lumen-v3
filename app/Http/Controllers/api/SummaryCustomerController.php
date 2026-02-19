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

        $query = DB::table('order_header as oh')
            ->join('master_pelanggan as mp', 'mp.id_pelanggan', '=', 'oh.id_pelanggan')
            ->select(
                DB::raw('MAX(oh.id) as last_id'), // 👈 tambahkan ini
                'oh.id_pelanggan',
                'mp.nama_pelanggan',
                DB::raw('JSON_ARRAYAGG(oh.no_document) as no_documents'),
                DB::raw('COUNT(oh.id) as total_order'),
                DB::raw('SUM(oh.biaya_akhir) as total_biaya')
            )
            ->where('oh.flag_status', 'ordered')
            ->whereYear('oh.tanggal_order', $tahun)
            ->where('oh.is_active', true)
            ->where('mp.is_active', true)
            ->groupBy('oh.id_pelanggan', 'mp.nama_pelanggan')
            ->orderBy('total_biaya', 'desc');

        return DataTables::of($query)
        ->editColumn('no_documents', function ($row) {
            return json_decode($row->no_documents);
        })
        ->make(true);
    }

}