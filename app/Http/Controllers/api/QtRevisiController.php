<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use App\Models\OrderHeader;
class QtRevisiController extends Controller
{
    public function index(Request $request){
        $tahun = request()->tahun;
        
        $data = OrderHeader::with(['quotationNonKontrak', 'quotationKontrakH', 'sales'])->where('is_revisi', true)
        ->whereYear('tanggal_penawaran', $tahun)
        ->where('is_active', 1)
        ->orderBy('tanggal_penawaran', 'desc');

        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
        switch ($jabatan) {
            case 24: // Sales Staff
                $data->where('sales_id', $this->user_id);
                break;
            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                    ->pluck('id')
                    ->toArray();
                array_push($bawahan, $this->user_id);
                $data->whereIn('sales_id', $bawahan);
                break;
        }

        return DataTables::of($data)->make(true);
    }
}