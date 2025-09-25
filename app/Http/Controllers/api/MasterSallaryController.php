<?php

namespace App\Http\Controllers\api;

use App\Models\MasterSallary;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class MasterSallaryController extends Controller
{
    public function index()
    {
        $data = MasterSallary::where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingKaryawan = MasterSallary::where('is_active', true)->pluck('nik_karyawan')->toArray();

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
            $masterSallary = MasterSallary::findOrFail($request->id);
            $masterSallary->is_active = false;
            $masterSallary->deleted_at = DATE('Y-m-d H:i:s');
            $masterSallary->deleted_by = $this->karyawan;
            $masterSallary->save();

            return response()->json([
                'success' => true,
                'message' => 'Master Sallary data deleted successfully'
            ], 200);
            } catch (\Throwable $th){
                return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try{
            if (strlen(str_replace(['Rp', '.', ','], '', $request->gaji_pokok)) > 10 || strlen(str_replace(['Rp', '.', ','], '', $request->tunjangan_kerja)) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }

            $existingKaryawan = MasterSallary::where('is_active', true)->pluck('nik_karyawan')->toArray();

            $masterSallary = new MasterSallary();
            // $masterSallary = fill($request->all());
            $masterSallary->created_by = $this->karyawan;
            $masterSallary->created_at = DATE('Y-m-d H:i:s');
            $masterSallary->gaji_pokok = str_replace(['Rp', '.', ','], '', $request->gaji_pokok);
            $masterSallary->bulan_efektif = $request->bulan_efektif;
            $masterSallary->tunjangan_kerja = str_replace(['Rp', '.', ','], '', $request->tunjangan_kerja);

            if($request->id && in_array($request->nik_karyawan, $existingKaryawan)) {
                $oldMasterSallary = MasterSallary::findorFail($request->id);
                $oldMasterSallary->updated_at = DATE('Y-m-d H:i:s');
                $oldMasterSallary->updated_by = $this->karyawan;
                $oldMasterSallary->is_active = false;
                $oldMasterSallary->save();

                $masterSallary->previous_id = $request->id;
                $masterSallary->karyawan = $oldMasterSallary->karyawan; 
                $masterSallary->nik_karyawan = $oldMasterSallary->nik_karyawan;

                $message = 'Master Sallary data updated successfully';

            } else {
                $karyawan = MasterKaryawan::where('nik_karyawan', $request->nik_karyawan)->first();
                $masterSallary->nik_karyawan = $karyawan->nik_karyawan;
                $masterSallary->karyawan = $karyawan->nama_lengkap; 

                $message = 'Master Sallary data inserted successfully';
                
            }

            $masterSallary->save();

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
        
        $data = MasterSallary::where('nik_karyawan', $request->nik_karyawan)
            ->orderBy('created_at', 'desc')
            ->get();

        return Datatables::of($data)->make(true);

    }
}