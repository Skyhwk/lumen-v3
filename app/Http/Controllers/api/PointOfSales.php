<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderHeader;
use App\Models\AllQuote;

use Illuminate\Support\Facades\DB;


class PointOfSales extends Controller
{
    public function index(Request $request)
    {
        $dataOrder = OrderHeader::where('is_active', true)->whereYear('created_at', date('Y'))->count();
        $ordertoday = OrderHeader::where('is_active', true)->whereDate('created_at', date('Y-m-d'))->get()->sum('biaya_akhir');

        // $data = OrderHeader::where('order_header.is_active', true)
        // ->whereRaw('SUBSTRING(all_quot.periode_kontrak, 1, 4) = ?', [date('Y')])
        // ->join('all_quot', 'all_quot.no_document', '=', 'order_header.no_document')
        // ->select('all_quot.periode_kontrak', \DB::raw('SUM(all_quot.grand_total) as total_penjualan'))
        // ->groupBy('all_quot.periode_kontrak')
        // ->get();

        $data = DB::table('all_qt_active')
            ->whereRaw('SUBSTRING(periode_kontrak, 1, 4) = ?', [date('Y')])
            ->select('periode_kontrak', \DB::raw('SUM(biaya_akhir) as total_penjualan'))
            ->groupBy('periode_kontrak')
            ->get();

        return response()->json([
            'dataOrder' => $dataOrder,
            'data' => $data,
            'ordertoday' => $ordertoday
        ]);
    }
}
