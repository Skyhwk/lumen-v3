<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

use App\Models\Canvasing;

class CanvasingController extends Controller
{
    public function indexUnprocessed(Request $request){
        $data = Canvasing::where('is_active', 1)
            ->where('is_processed', 0);

        return dataTables::of($data)->make(true);
    }
    public function indexProcessed(Request $request){
        $data = Canvasing::where('is_active', 1)
            ->where('is_processed', 1);

        return dataTables::of($data)->make(true);
    }

    public function process(Request $request){
        DB::beginTransaction();
        try {
            $data = Canvasing::where('id', $request->id)->first();
            
            $data->is_processed = 1;
            $data->processed_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->processed_by = $this->karyawan;
            $data->keterangan = $request->keterangan;
            $data->save();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data Canvasing berhasil diproses',
            ], 201);
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