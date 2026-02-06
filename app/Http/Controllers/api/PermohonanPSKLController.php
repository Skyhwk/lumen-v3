<?php

namespace App\Http\Controllers\api;

use App\Models\FormPSKL;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\MasterKaryawan;

class PermohonanPsklController extends Controller
{
    public function index(Request $request)
    {
        $query = FormPSKL::where('is_active', 1)
            ->when($request->status == 'atas', 
                fn($q) => $q->whereIn('status', ['WAITING PROCESS', 'PROCESSED', "REJECTED"]),
                fn($q) => $q->where('status', 'DONE')
            );

        return Datatables::of($query)->make(true);
    }

    public function process(Request $request){
        DB::beginTransaction();
        try {
            $data = FormPSKL::where('id', $request->id)->first();
            $data->processed_by = $this->karyawan;
            $data->processed_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->status = 'PROCESSED';
            $data->save();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Form PSKL berhasil diproses'], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage()], 400);
        }
        
    }

    public function reject(Request $request) 
    {
        DB::beginTransaction();
        try {
            $data = FormPSKL::where('id', $request->id)->first();
            $data->rejected_by = $this->karyawan;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->is_rejected = true;
            $data->catatan_reject = $request->alasan_reject;
            $data->status = 'REJECTED';
            $data->save();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Form PSKL berhasil di reject']);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage()], 400);
        }
    }

    public function done(Request $request) 
    {
        DB::beginTransaction();
        try {
            $data = FormPSKL::where('id', $request->id)->first();
            $data->done_by = $this->karyawan;
            $data->tanggal_selesai = Carbon::now()->format('Y-m-d H:i:s');
            $data->status = 'DONE';
            $data->save();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Form PSKL berhasil di selesaikan']);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage()], 400);
        }
    }
}