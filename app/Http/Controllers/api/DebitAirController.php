<?php

namespace App\Http\Controllers\api;


use App\Models\DataLapanganAir;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DebitAirController extends Controller
{
    public function index(Request $request)
    {
        $data = DataLapanganAir::with('detail')
            ->where('is_approve', 1)
            ->whereDate('created_at', '>=', '2025-01-01')
            ->where('debit_air', 'Data By Customer(Email)');

        return Datatables::of($data)->make(true);
    }

    public function update(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DataLapanganAir::where('id', $request->id)
                ->where('no_sampel', $request->no_sampel)
                ->first();

            if (!$data) {
                return response()->json([
                    'message' => 'Data tidak ditemukan',
                    'status' => 404,
                    'success' => false
                ], 404);
            }

            $data->debit_air = 'Data By Customer(' . $request->debit_air . ')';
            $data->save();

            DB::commit();

            return response()->json([
                'message' => 'Data berhasil diupdate',
                'status' => 201,
                'success' => true
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => $th->getMessage(),
                'line' => $th->getLine()
            ], 500);
        }
    }
}
