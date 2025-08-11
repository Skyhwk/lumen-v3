<?php

namespace App\Http\Controllers\api;

use App\Models\PencadanganUpah;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class PencadanganUpahController extends Controller
{
    public function index()
    {
        $data = PencadanganUpah::where('pencadangan_upah.is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingKaryawan = PencadanganUpah::where('is_active', true)->pluck('nik_karyawan')->toArray();

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
            $pencadanganUpah = PencadanganUpah::findOrFail($request->id);
        $pencadanganUpah->is_active = false;
        $pencadanganUpah->deleted_at = DATE('Y-m-d H:i:s');
        $pencadanganUpah->deleted_by = $this->karyawan;
        $pencadanganUpah->save();

        return response()->json([
            'success' => true,
            'message' => 'Pencadangan Upah data deleted successfully'
        ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try{
            if (strlen(str_replace(['Rp', '.', ','], '', $request->nominal)) > 10 ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }
            $existingKaryawan = PencadanganUpah::where('is_active', true)->pluck('nik_karyawan')->toArray();
            $pencadanganUpah = new PencadanganUpah();
            // $pencadanganUpah = fill($request->all());
            $pencadanganUpah->created_by = $this->karyawan;
            $pencadanganUpah->created_at = DATE('Y-m-d H:i:s');
            $pencadanganUpah->tenor = $request->tenor;
            $pencadanganUpah->tenor_berjalan = '-'.$request->tenor;
            $pencadanganUpah->bulan_efektif = $request->bulan_efektif;
            $pencadanganUpah->nominal = str_replace(['Rp', '.', ','], '', $request->nominal);
            $pencadanganUpah->nominal_berjalan = '-'.$pencadanganUpah->nominal;

            if($request->id && in_array($request->nik_karyawan, $existingKaryawan)) {
                $oldpencadanganUpah = PencadanganUpah::findorFail($request->id);
                $oldpencadanganUpah->updated_at = DATE('Y-m-d H:i:s');
                $oldpencadanganUpah->updated_by = $this->karyawan;
                $oldpencadanganUpah->is_active = false;
                $oldpencadanganUpah->save();

                $pencadanganUpah->previous_id = $request->id;
                $pencadanganUpah->karyawan = $oldpencadanganUpah->karyawan; 
                $pencadanganUpah->nik_karyawan = $oldpencadanganUpah->nik_karyawan;

                $message = 'Pencadangan Upah data updated successfully';

            } else {
                $karyawan = MasterKaryawan::where('nik_karyawan', $request->nik_karyawan)->first();
                $pencadanganUpah->nik_karyawan = $karyawan->nik_karyawan;
                $pencadanganUpah->karyawan = $karyawan->nama_lengkap; 

                $message = 'Pencadangan Upah data inserted successfully';
                
            }

            $pencadanganUpah->save();

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
        
        $data = PencadanganUpah::where('nik_karyawan', $request->nik_karyawan)
            ->orderBy('created_at', 'desc')
            ->get();

        return Datatables::of($data)->make(true);

    }
}