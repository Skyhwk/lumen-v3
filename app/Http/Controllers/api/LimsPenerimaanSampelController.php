<?php

namespace App\Http\Controllers\api;
date_default_timezone_set('Asia/Jakarta');

use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use App\Http\Controllers\Controller;
use App\Models\{OrderDetail, ScanSampelTc};
use Exception;
use Illuminate\Support\Facades\DB;

class LimsPenerimaanSampelController extends Controller
{
    public function index(Request $request)
    {
        $date = Carbon::parse($request->date);

        $data = ScanSampelTc::whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->orderBy('id', 'desc');

        return Datatables::of($data)
            ->make(true);
    }
}
