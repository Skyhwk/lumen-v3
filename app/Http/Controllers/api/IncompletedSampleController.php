<?php

namespace App\Http\Controllers\api;

use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Services\GetBawahan;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class IncompletedSampleController extends Controller
{
    public function index(Request $request){
        // $jabatan = 1;
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        $data = OrderDetail::with('orderHeader')->where('tanggal_sampling' ,'<', Carbon::now()->format('Y-m-d'))
            ->whereNull('tanggal_terima')
            ->where('is_active', 1);

        if(in_array($jabatan, [15,21])) {
            $getBawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->toArray();

            $data->whereHas('orderHeader', function ($q) use ($getBawahan) {
                $q->whereIn('sales_id', $getBawahan);
            });
        }else if(in_array($jabatan, [156,157])) {
            $data->whereHas('orderHeader', function ($q) {
                $q->where('sales_id', $this->user_id);
            });
        }

        return Datatables::of($data)
            ->addColumn('sales_penanggung_jawab', function ($data) {
                $dataKaryawan = MasterKaryawan::where('id', $data->orderHeader->sales_id)->first() ?? null;
                return $dataKaryawan->nama_lengkap ?? null;
            })
            ->make(true);
    }
}