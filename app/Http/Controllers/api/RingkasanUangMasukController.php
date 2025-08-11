<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SalesIn;
use App\Models\OrderHeader;

class RingkasanUangMasukController extends Controller
{
    public function index()
    {
        $data = SalesIn::where('is_active', true)->whereYear('created_at', date('Y'))->get();
        $dataOrder = OrderHeader::where('is_active', true)->whereYear('created_at', date('Y'))->count();

        return response()->json([
            'data' => $data,
            'dataOrder' => $dataOrder
        ]);
    }
}
