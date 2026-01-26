<?php

namespace App\Http\Controllers\api;

use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class TcIncompletedSampleController extends Controller
{
    public function index(Request $request){
        $data = OrderDetail::where('tanggal_sampling' ,'<', Carbon::now()->format('Y-m-d'))
            ->whereNull('tanggal_terima')
            ->where('is_active', 1);

        return Datatables::of($data)->make(true);
    }
}