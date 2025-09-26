<?php

namespace App\Http\Controllers\api;

use App\Models\PengajuanFeeSampling;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Illuminate\Database\Eloquent\Collection;

class FeeSamplingController extends Controller
{
    public function index(Request $request)
    {
        $data = PengajuanFeeSampling::where('is_approve_finance', 1)
        ->whereIn('status_payment',[ "Approved by finance" ,'Approved by expanse'])->get();
        foreach ($data as $key => $value) {
            $value->detail_fee = json_decode($value->detail_fee);
        }
        return Datatables::of($data)->make(true);
    }
    public function handleReject (Request $request)
    {
        DB::beginTransaction();
        try {
        $data = PengajuanFeeSampling::where('id', $request->id)->first();
        if($data) {
            $data->is_reject_expanse = 1;
            $data->is_approve_finance = 0;
            $data->alasan_reject_expanse = $request->alasan_reject;
            $data->status_payment = "Rejected by expanse";
            $data->save();
        } else {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan', 'status' => 400], 400);
        }
      
        DB::commit();
        return response()->json(['success' => true, 'message' => 'Data berhasil di reject', 'status' => 200], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage(), 'status' => 500], 500);
        }
     
    }

    public function handleApprove (Request $request)
    {
        DB::beginTransaction();
        try {  
            $data = PengajuanFeeSampling::where('id', $request->id)->first();
            if($data) {
                $data->is_approve_expanse = 1;
                $data->status_payment = 'Approved by expanse';

                $data->save();
            } else {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan', 'status' => 400], 400);
            }
        
        DB::commit();
        return response()->json(['success' => true, 'message' => 'Data berhasil di approve', 'status' => 200], 200);

        } catch (\Throwable $th) {
                DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage(), 'status' => 500], 500);
        }
      
    }
    public function handleUpdateTransfer (Request $request)
    {
        DB::beginTransaction();
        try {  
            $data = PengajuanFeeSampling::where('id', $request->id)->first();
            if($data) {
                $data->transfer_date = $request->transfer_date;
                $data->transfered_by = $this->karyawan;
                $data->status_payment = 'PAID';

                $data->save();
            } else {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan', 'status' => 400], 400);
            }
        
        DB::commit();
        return response()->json(['success' => true, 'message' => 'Taggal Transfer berhasil di perbarui', 'status' => 200], 200);

        } catch (\Throwable $th) {
                DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage(), 'status' => 500], 500);
        }
      
    }

  
}