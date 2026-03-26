<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

use App\Models\SummaryFeeSales;

class SummaryFeeSalesController extends Controller
{
    public function index(Request $request)
    {
        $summary = SummaryFeeSales::with('sales')->where('tahun', $request->tahun);
        
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
        switch ($jabatan) {
            case 24: // Sales Staff
                $summary->where('sales_id', $this->user_id);
                break;

            case 148: // Customer Relation Officer
                $summary->where('sales_id', $this->user_id);
                break;
        }

        return Datatables::of($summary)->make(true);
    }
}
