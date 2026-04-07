<?php

namespace App\Http\Controllers\api;

use App\Models\DataPerbantuan;
use App\Models\MasterKaryawan;

use App\Http\Controllers\Controller;
use App\Services\GetBawahan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

class FollowUpPerbantuanSalesController extends Controller
{
    private function applyJabatanFilter($query, $request)
    {
        $user = $request->attributes->get('user');
        $jabatan = $user->karyawan->id_jabatan;
        $userId = $user->id; 

        if (in_array($jabatan, [24, 86, 148])) {
            return $query->where($query->getModel()->getTable() . '.sales_id', $userId);
        } 
        
        if (in_array($jabatan, [21, 15, 154, 157])) {
            $bawahan = GetBawahan::where('id', $userId)->pluck('id')->toArray();
            $bawahan[] = $userId;
            return $query->whereIn($query->getModel()->getTable() . '.sales_id', $bawahan);
        }

        return $query;
    }
    
    public function index(Request $request)
    {
        $query = DataPerbantuan::with('sales')->where('is_checked', $request->is_checked);
        $data = $this->applyJabatanFilter($query, $request)->get();

        return Datatables::of($data)->make(true);
    }

    public function updateChecked(Request $request, $id)
    {
        DB::beginTransaction();
        try {

            $data = DataPerbantuan::where('id', $id)->where('is_active', 1)->first();

            if (!$data) {
                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }

            $data->is_checked = true;
            $data->keterangan = $request->keterangan;
            $data->checked_at = Carbon::now();
            $data->save();

            DB::commit();

            return response()->json([
                'message' => 'Data berhasil diupdate',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}