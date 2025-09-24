<?php

namespace App\Http\Controllers\api;

use App\Models\PPH21;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class PPH21Controller extends Controller
{
    public function index()
    {
        $data = PPH21::where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingKaryawan = PPH21::where('is_active', true)->pluck('nik_karyawan')->toArray();

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
        $data = PPH21::findOrFail($request->id);
        $data->is_active = false;
        $data->deleted_at = DATE('Y-m-d H:i:s');
        $data->deleted_by = $this->karyawan;
        $data->save();

        return response()->json([
            'success' => true,
            'message' => 'Data PPH deleted successfully'
        ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try{
            if (strlen(str_replace(['Rp', '.', ','], '', $request->pajak_bulanan)) > 10 || strlen(str_replace(['Rp', '.', ','], '', $request->pajak_tahunan)) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }
            // dd(str_replace(['Rp', '.', ','], '', $request->total_denda));
            $existingKaryawan = PPH21::where('is_active', true)->pluck('nik_karyawan')->toArray();

            if($request->id && in_array($request->nik_karyawan, $existingKaryawan)) {
                $updatedData = PPH21::findorFail($request->id);
                $updatedData->updated_at = DATE('Y-m-d H:i:s');
                $updatedData->updated_by = $this->karyawan;
                $updatedData->pajak_bulanan = str_replace(['Rp', '.', ','], '', $request->pajak_bulanan);
                $updatedData->pajak_tahunan = str_replace(['Rp', '.', ','], '', $request->pajak_tahunan);
                $updatedData->bulan_mulai_pemotongan = $request->bulan_mulai_pemotongan;
                $updatedData->save();

                $message = 'PPH data updated successfully';

            } else {
                $inputData = new PPH21();
                $inputData->created_by = $this->karyawan;
                $inputData->created_at = DATE('Y-m-d H:i:s');
                $inputData->pajak_bulanan = str_replace(['Rp', '.', ','], '', $request->pajak_bulanan);
                $inputData->pajak_tahunan = str_replace(['Rp', '.', ','], '', $request->pajak_tahunan);
                $inputData->bulan_mulai_pemotongan = $request->bulan_mulai_pemotongan;
                $karyawan = MasterKaryawan::where('nik_karyawan', $request->nik_karyawan)->first();
                $inputData->nik_karyawan = $karyawan->nik_karyawan;
                $inputData->karyawan = $karyawan->nama_lengkap; 

                $message = 'PPH data inserted successfully';
                
                $inputData->save();
            }


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
        
        $data = PPH21::where('nik_karyawan', $request->nik_karyawan)
            ->orderBy('created_at', 'asc')
            ->get();

        return Datatables::of($data)->make(true);

    }
}