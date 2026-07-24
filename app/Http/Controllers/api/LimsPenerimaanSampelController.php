<?php

namespace App\Http\Controllers\api;
date_default_timezone_set('Asia/Jakarta');

use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use App\Http\Controllers\Controller;
use App\Models\ScanSampelTc;
use App\Models\Lims\OrderDetail;
use Exception;
use Illuminate\Support\Facades\DB;

class LimsPenerimaanSampelController extends Controller
{
    public function index(Request $request)
    {
        $date = Carbon::parse($request->date);

        // Dapatkan semua no_sampel dari ScanSampelTc untuk bulan/tahun ini
        $scanNoSampel = ScanSampelTc::whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->pluck('no_sampel')
            ->toArray();

        // Cari no_sampel yang juga ada di LIMS order_detail
        $validNoSampels = [];
        if (!empty($scanNoSampel)) {
            $validNoSampels = OrderDetail::whereIn('no_sampel', $scanNoSampel)
                ->pluck('no_sampel')
                ->toArray();
        }

        // Query ScanSampelTc hanya untuk yang valid di LIMS
        $data = ScanSampelTc::whereIn('no_sampel', $validNoSampels)
            ->whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->orderBy('id', 'desc');

        return Datatables::of($data)
            ->make(true);
    }
}
