<?php

namespace App\Http\Controllers\api;

use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class TcIncompletedSampleController extends Controller
{
    public function index(Request $request){
        $data = OrderDetail::with('orderHeader')
            ->where('tanggal_sampling' ,'<', Carbon::now()->format('Y-m-d'))
            ->whereNull('tanggal_terima')
            ->where('is_active', 1);

        return Datatables::of($data)
            ->addColumn('sales_penanggung_jawab', function ($data) {
                $dataKaryawan = MasterKaryawan::where('id', $data->orderHeader->sales_id)->first() ?? null;
                return $dataKaryawan->nama_lengkap ?? null;
            })
            ->make(true);
    }
}