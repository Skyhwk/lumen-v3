<?php

namespace App\Http\Controllers\api;

use App\Models\FormPSKL;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\MasterKaryawan;
use App\Services\Notification;
use App\Services\GetBawahan;
class PermohonanPsklController extends Controller
{
    public function index(Request $request)
    {
        $query = FormPSKL::where('is_active', 1)
            ->when($request->status == 'atas', 
                    fn($q) => $q->whereIn('status', ['WAITING PROCESS', 'PROCESSED', "REJECTED", "REOPEN", "PENDING" , 'SOLVED']),
                    fn($q) => $q->whereIn('status', ['DONE'])
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

            $message = 'Form PSKL telah di process';
            Notification::where('nama_lengkap', $data->created_by)
                    ->title('Form PSKL Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/form-pskl')
                    ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
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
            $data->reject_notes = $request->alasan_reject;
            $data->status = 'REJECTED';
            $data->save();

            $message = 'Form PSKL telah di reject';
            Notification::where('nama_lengkap', $data->created_by)
                    ->title('Form PSKL Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/form-pskl')
                    ->send();
            

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage()], 400);
        }
    }

    public function pending(Request $request) 
    {
        DB::beginTransaction();
        try {
            $data = FormPSKL::where('id', $request->id)->first();
            $data->pending_by = $this->karyawan;
            $data->pending_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->pending_notes = $request->alasan_pending;
            $data->status = 'PENDING';
            $data->save();

            $message = 'Form PSKL telah di pending';
            Notification::where('nama_lengkap', $data->created_by)
                    ->title('Form PSKL Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/form-pskl')
                    ->send();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage()], 400);
        }
    }

    public function solve(Request $request) 
    {
        DB::beginTransaction();
        try {
            $data = FormPSKL::where('id', $request->id)->first();
            $data->solved_by = $this->karyawan;
            $data->solved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->status = 'SOLVED';
            $data->save();

            $message = 'Form PSKL telah di solve';
            Notification::where('nama_lengkap', $data->created_by)
                    ->title('Form PSKL Update')
                    ->message($message . ' Oleh ' . $this->karyawan)
                    ->url('/form-pskl')
                    ->send();
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $th->getMessage()], 400);
        }
    }
}