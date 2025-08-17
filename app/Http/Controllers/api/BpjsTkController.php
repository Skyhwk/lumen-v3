<?php

namespace App\Http\Controllers\api;

use App\Models\BpjsTk;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class BpjsTkController extends Controller
{
    public function index()
    {
        $data = BpjsTk::where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan()
    {
        $existingKaryawan = BpjsTk::where('is_active', true)->pluck('nik_karyawan')->toArray();

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
            $bpjsTk = BpjsTk::findOrFail($request->id);
        $bpjsTk->is_active = false;
        $bpjsTk->deleted_at = DATE('Y-m-d H:i:s');
        $bpjsTk->deleted_by = $this->karyawan;
        $bpjsTk->save();

        return response()->json([
            'success' => true,
            'message' => 'data BPJS TK deleted successfully'
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

            $existingKaryawan = BpjsTk::where('is_active', true)->pluck('nik_karyawan')->toArray();
            $bpjsTk = new BpjsTk();
            // $bpjsTk = fill($request->all());
            $bpjsTk->created_by = $this->karyawan;
            $bpjsTk->no_bpjs_tk = $request->no_bpjs_tk;
            $bpjsTk->bulan_efektif = $request->bulan_efektif;
            $bpjsTk->gaji_pokok = str_replace(['Rp', '.', ','], '', $request->gaji_pokok);
            $bpjsTk->potongan_karyawan = $request->potongan_karyawan / 100;
            $bpjsTk->nominal_potongan_karyawan = $bpjsTk->potongan_karyawan * $bpjsTk->gaji_pokok;
            $bpjsTk->potongan_kantor = $request->potongan_kantor / 100;
            $bpjsTk->nominal_potongan_kantor = $bpjsTk->potongan_kantor * $bpjsTk->gaji_pokok;
            $bpjsTk->created_at = DATE('Y-m-d H:i:s');

            if($request->id && in_array($request->nik_karyawan, $existingKaryawan)) {
                $oldBpjsTk = BpjsTk::findorFail($request->id);
                $oldBpjsTk->updated_at = DATE('Y-m-d H:i:s');
                $oldBpjsTk->updated_by = $this->karyawan;
                $oldBpjsTk->is_active = false;
                $oldBpjsTk->save();

                $bpjsTk->previous_id = $request->id;
                $bpjsTk->karyawan = $oldBpjsTk->karyawan; 
                $bpjsTk->nik_karyawan = $oldBpjsTk->nik_karyawan;

                $message = 'BPJS TK data updated successfully';

            } else {
                $karyawan = MasterKaryawan::where('nik_karyawan', $request->nik_karyawan)->first();
                $bpjsTk->nik_karyawan = $karyawan->nik_karyawan;
                $bpjsTk->karyawan = $karyawan->nama_lengkap; 

                $message = 'BPJS TK data inserted successfully';
                
            }

            $bpjsTk->save();

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
        
        $data = BpjsTk::where('nik_karyawan', $request->nik_karyawan)
            ->orderBy('created_at', 'desc')
            ->get();

        return Datatables::of($data)->make(true);

    }
}