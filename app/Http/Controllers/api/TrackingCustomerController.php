<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Datatables;

use App\Models\MasterPelanggan;
use App\Models\MasterKaryawan;
use App\Models\DFUS;

class TrackingCustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = MasterPelanggan::where('is_active', true)->orderByDesc('id');
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

        switch ($jabatan) {
            case 24: // Sales Staff
                $customers->where('sales_id', $this->user_id);
                break;

            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();
                array_push($bawahan, $this->user_id);

                $customers->whereIn('sales_id', $bawahan);
                break;
        }

        return Datatables::of($customers)->make(true);
    }

    public function followupHistory(Request $request)
    {
        $dfus = DFUS::with('keteranganTambahan')->where('id_pelanggan', $request->id_pelanggan)->orderByDesc('id');

        return Datatables::of($dfus)
            ->addColumn('log_webphone', fn($row) => $row->getLogWebphoneAttribute()->toArray())
            ->make(true);
    }
}
