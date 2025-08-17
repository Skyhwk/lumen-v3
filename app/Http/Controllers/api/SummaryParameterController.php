<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SummaryParameterController extends Controller
{
    public function index(Request $request)
    {
        // dd($request->all());
        $data = DB::table('summary_parameter as sp')
            ->leftJoin('master_harga_parameter as mhp', 'sp.id_parameter', '=', 'mhp.id_parameter')
            ->select('sp.*', 'mhp.harga as harga')
            ->where('sp.tahun', $request->year)
            ->get();
        return DataTables::of($data)->make(true);
    }
}
