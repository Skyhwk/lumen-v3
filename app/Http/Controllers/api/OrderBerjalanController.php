<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use App\Models\OrderBerjalan;

class OrderBerjalanController extends Controller {
    public function index(Request $request)
    {
        $query = OrderBerjalan::query();

        if ($request->filled('periode')) {
            $periode = $request->periode;
            $query->where('tgl_order', 'like', "{$periode}%");
        }

        return DataTables::of($query)->make(true);
    }

    public function detail(Request $request) {
        $order = OrderBerjalan::where('id', $request->id)->first();
        return response()->json($order);
    }
}