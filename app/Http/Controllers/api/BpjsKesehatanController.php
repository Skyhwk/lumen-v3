<?php

namespace App\Http\Controllers\api;

use App\Models\BpjsKesehatan;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class BpjsKesehatanController extends Controller
{
    public function index()
    {
        $data = BpjsKesehatan::where('bpjs_kesehatan.is_active', true)
        ->rightJoin('master_karyawan', 'bpjs_kesehatan.nik_karyawan', '=', 'master_karyawan.nik_karyawan')
        ->select('bpjs_kesehatan.*', 'master_karyawan.jabatan')
        ->get();

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingKaryawan = BpjsKesehatan::where('is_active', true)->pluck('nik_karyawan')->toArray();

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
        $BpjsKesehatan = BpjsKesehatan::findOrFail($request->id);
        $BpjsKesehatan->is_active = false;
        $BpjsKesehatan->deleted_at = DATE('Y-m-d H:i:s');
        $BpjsKesehatan->deleted_by = $this->karyawan;
        $BpjsKesehatan->save();

        return response()->json([
            'success' => true,
            'message' => 'BPJS Kesehatan data deleted successfully'
        ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try{
            if (strlen(str_replace(['Rp', '.', ','], '', $request->gaji_pokok)) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nominal Terlalu Besar',
                ], 401);
            }
            $existingKaryawan = BpjsKesehatan::where('is_active', true)->pluck('nik_karyawan')->toArray();

            $BpjsKesehatan = new BpjsKesehatan();
            // $BpjsKesehatan = fill($request->all());
            $BpjsKesehatan->created_by = $this->karyawan;
            $BpjsKesehatan->no_bpjs = $request->no_bpjs;
            $BpjsKesehatan->bulan_efektif = $request->bulan_efektif;
            $BpjsKesehatan->gaji_pokok = str_replace(['Rp', '.', ','], '', $request->gaji_pokok);
            $BpjsKesehatan->potongan_karyawan = $request->potongan_karyawan / 100;
            $BpjsKesehatan->nominal_potongan_karyawan = $BpjsKesehatan->potongan_karyawan * $BpjsKesehatan->gaji_pokok;
            $BpjsKesehatan->potongan_kantor = $request->potongan_kantor / 100;
            $BpjsKesehatan->nominal_potongan_kantor = $BpjsKesehatan->potongan_kantor * $BpjsKesehatan->gaji_pokok;
            $BpjsKesehatan->created_at = DATE('Y-m-d H:i:s');

            if($request->id && in_array($request->nik_karyawan, $existingKaryawan)) {
                $oldBpjsKesehatan = BpjsKesehatan::findorFail($request->id);
                $oldBpjsKesehatan->updated_at = DATE('Y-m-d H:i:s');
                $oldBpjsKesehatan->updated_by = $this->karyawan;
                $oldBpjsKesehatan->is_active = false;
                $oldBpjsKesehatan->save();

                $BpjsKesehatan->previous_id = $request->id;
                $BpjsKesehatan->karyawan = $oldBpjsKesehatan->karyawan; 
                $BpjsKesehatan->nik_karyawan = $oldBpjsKesehatan->nik_karyawan;

                $message = 'BPJS Kesehatan data updated successfully';

            } else {
                $karyawan = MasterKaryawan::where('nik_karyawan', $request->nik_karyawan)->first();
                $BpjsKesehatan->nik_karyawan = $karyawan->nik_karyawan;
                $BpjsKesehatan->karyawan = $karyawan->nama_lengkap; 

                $message = 'BPJS Kesehatan data inserted successfully';
                
            }

            $BpjsKesehatan->save();

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
        
        $data = BpjsKesehatan::where('nik_karyawan', $request->nik_karyawan)
            ->orderBy('created_at', 'desc')
            ->get();

        return Datatables::of($data)->make(true);

    }
}