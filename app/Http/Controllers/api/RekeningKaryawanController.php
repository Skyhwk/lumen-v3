<?php

namespace App\Http\Controllers\api;

use App\Models\RekeningKaryawan;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class RekeningKaryawanController extends Controller
{
    public function index()
    {
        $data = RekeningKaryawan::where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingKaryawan = RekeningKaryawan::where('is_active', true)->pluck('nik_karyawan')->toArray();

        $karyawan = MasterKaryawan::where('is_active', true)
            ->whereNotIn('nik_karyawan', $existingKaryawan)
            ->select('nik_karyawan', 'nama_lengkap')
            ->get();
        
            return response()->json([
                'success' => true,
                'data' => $karyawan,
                'message' => 'Available karyawan data retrieved successfully',
            ], 201);
    }

    public function delete(Request $request){
        try {
            $data = RekeningKaryawan::findOrFail($request->id);
            $data->is_active = false;
            $data->deleted_at = DATE('Y-m-d H:i:s');
            $data->deleted_by = $this->karyawan;
            $data->save();

            return response()->json([
                'success' => true,
                'message' => 'BPJS TK data deleted successfully'
            ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try{
            $existingKaryawan = RekeningKaryawan::where('is_active', true)->pluck('nik_karyawan')->toArray();
            $data = new RekeningKaryawan();
            // $data = fill($request->all());
            $data->created_by = $this->karyawan;
            $data->created_at = DATE('Y-m-d H:i:s');
            $data->no_rekening = $request->no_rekening;
            $data->nama_bank = $request->nama_bank;

            if($request->id && in_array($request->nik_karyawan, $existingKaryawan)) {
                $oldData = RekeningKaryawan::findorFail($request->id);
                $oldData->updated_at = DATE('Y-m-d H:i:s');
                $oldData->updated_by = $this->karyawan;
                $oldData->is_active = false;
                $oldData->save();

                $data->previous_id = $request->id;
                $data->karyawan = $oldData->karyawan; 
                $data->nik_karyawan = $oldData->nik_karyawan;

                $message = 'BPJS TK data updated successfully';

            } else {
                $karyawan = MasterKaryawan::where('nik_karyawan', $request->nik_karyawan)->first();
                $data->nik_karyawan = $karyawan->nik_karyawan;
                $data->karyawan = $karyawan->nama_lengkap; 

                $message = 'BPJS TK data inserted successfully';
            }

            $data->save();

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 201);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function getHistory(Request $request)
    {
        
        $data = RekeningKaryawan::where('nik_karyawan', $request->nik_karyawan)
            ->orderBy('created_at', 'desc')
            ->get();

        return Datatables::of($data)->make(true);

    }
}