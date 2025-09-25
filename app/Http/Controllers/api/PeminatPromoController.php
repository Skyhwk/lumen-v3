<?php

namespace App\Http\Controllers\api;

use App\Models\PromoLeads;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;


class PeminatPromoController extends Controller
{
    
    public function index(Request $request)
    {
        $data = PromoLeads::select('promo', DB::raw('COUNT(*) as total'))
            ->groupBy('promo');

        return DataTables::of($data)
            ->addIndexColumn()
            ->make(true);
    }

    public function show(Request $request)
    {
        $data = PromoLeads::where('promo', $request->promo);

        return DataTables::of($data)->make(true);
    }
}