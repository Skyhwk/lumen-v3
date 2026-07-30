<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\RejectedFdl;
use Illuminate\Http\Request;
use Yajra\DataTables\Datatables;    

class RejectedFdlController extends Controller
{
    public function index(Request $request)
    {
        $data = RejectedFdl::with('order_detail')->orderBy('id', 'desc');

        return Datatables::of($data)->make(true);
    }

    public function check(Request $request)
    {
        try {
            $fdl = RejectedFdl::find($request->id);
            if (!$fdl) {
                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }

            $fdl->is_checked = 1;
            $fdl->save();

            return response()->json(['message' => 'Berhasil update'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }
}