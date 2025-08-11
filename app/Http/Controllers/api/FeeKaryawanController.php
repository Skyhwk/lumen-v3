<?php

namespace App\Http\Controllers\api;

use App\Models\MasterFee;
use App\Models\FeeKaryawan;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class FeeKaryawanController extends Controller
{
    public function indexMF()
    {
        $data = MasterFee::where('is_active', true);

        return Datatables::of($data)->make(true);
    }
    
    public function indexFE()
    {
        $data = FeeKaryawan::where('fee_karyawan.is_active', true)
        ->leftJoin('master_karyawan', 'fee_karyawan.nik_karyawan', '=', 'master_karyawan.nik_karyawan')
        ->leftJoin('master_fee', 'fee_karyawan.id_master_fee', '=', 'master_fee.id')
        ->select('fee_karyawan.*', 'master_karyawan.jabatan', 'master_fee.tipe', 'master_fee.nominal');

        return Datatables::of($data)->make(true);
    }

    public function getKalkulasiFK()
    {
        $data = FeeKaryawan::where('fee_karyawan.is_active', true)
        ->leftJoin('master_karyawan', 'fee_karyawan.nik_karyawan', '=', 'master_karyawan.nik_karyawan')
        ->leftJoin('master_fee', 'fee_karyawan.id_master_fee', '=', 'master_fee.id')
        ->select('fee_karyawan.*', 'master_karyawan.jabatan', 'master_fee.tipe', 'master_fee.nominal');

        return Datatables::of($data)->make(true);
    }

    public function storeMF(Request $request)
    {
        try{
            $existingMasterFee = MasterFee::where('is_active', true)->pluck('tipe')->toArray();

            $masterFee = new MasterFee();
            // $masterFee = fill($request->all());
            $masterFee->created_by = $this->karyawan;
            $masterFee->nominal = str_replace(['Rp', '.', ','], '', $request->nominal);
            $masterFee->created_at = DATE('Y-m-d H:i:s');
            $masterFee->tipe = $request->tipe; 


            if(in_array($request->tipe, $existingMasterFee)) {
                if($request->id) {
                    $oldMasterFee = MasterFee::findorFail($request->id);
                    $oldMasterFee->updated_at = DATE('Y-m-d H:i:s');
                    $oldMasterFee->updated_by = $this->karyawan;
                    $oldMasterFee->is_active = false;
                    $oldMasterFee->save();

                    $masterFee->previous_id = $request->id;

                    $message = 'Master Fee data updated successfully';
                } else {
                    return response()->json([
                        'message' => 'Tipe '. $request->tipe . ' Sudah Ada.!',
                    ], 401);
                }

            } else {
                $message = 'Master Fee data inserted successfully';
            }

            $masterFee->save();

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 201);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function storeFE(Request $request)
    {
        try{
            $existingFeeKaryawan = FeeKaryawan::where('is_active', true)->pluck('karyawan')->toArray();

            $feeKaryawan = new FeeKaryawan();
            // $feeKaryawan = fill($request->all());
            $feeKaryawan->created_by = $this->karyawan;
            $feeKaryawan->created_at = DATE('Y-m-d H:i:s');
            $feeKaryawan->id_master_fee = $request->tipe_id; 


            if($request->id && in_array($request->karyawan, $existingFeeKaryawan)) {
                
                    $oldFeeKaryawan = FeeKaryawan::findorFail($request->id);
                    $oldFeeKaryawan->updated_at = DATE('Y-m-d H:i:s');
                    $oldFeeKaryawan->updated_by = $this->karyawan;
                    $oldFeeKaryawan->is_active = false;
                    $oldFeeKaryawan->save();

                    $feeKaryawan->previous_id = $request->id;
                    $feeKaryawan->karyawan = $request->karyawan;
                    $feeKaryawan->nik_karyawan = $oldFeeKaryawan->nik_karyawan;

                    $message = 'Fee Karyawan data updated successfully';
                

            } else {
                if (isset($request->karyawan) && strpos($request->karyawan, '-') !== false) {
                    $user = explode('-', $request->karyawan);
                    $feeKaryawan->karyawan = $user[1]; 
                    $feeKaryawan->nik_karyawan = $user[0]; 
                } else {
                    return response()->json(['message' => 'Format karyawan tidak valid.'], 400);
                }

                $message = 'Fee Karyawan data inserted successfully';
            }

            $feeKaryawan->save();

            return response()->json([
                'success' => true,
                'message' => $message,
            ], 201);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function deleteMF(Request $request)
    {
        try {
        $masterFee = MasterFee::findOrFail($request->id);
        $masterFee->is_active = false;
        $masterFee->deleted_at = DATE('Y-m-d H:i:s');
        $masterFee->deleted_by = $this->karyawan;
        $masterFee->save();

        return response()->json([
            'success' => true,
            'message' => 'Master Fee data deleted successfully'
        ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
    public function deleteFE(Request $request)
    {
        try {
        $masterFee = FeeKaryawan::findOrFail($request->id);
        $masterFee->is_active = false;
        $masterFee->deleted_at = DATE('Y-m-d H:i:s');
        $masterFee->deleted_by = $this->karyawan;
        $masterFee->save();

        return response()->json([
            'success' => true,
            'message' => 'Fee   Karyawan data deleted successfully'
        ], 200);
        } catch (\Throwable $th){
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function getHistory(Request $request)
    {
        
        $data = MasterFee::where('tipe', $request->tipe)
            ->orderBy('created_at', 'desc')
            ->get();

        return Datatables::of($data)->make(true);

    }

    public function getFeeKaryawan()
    {
        $existingKaryawan = FeeKaryawan::where('is_active', true)->pluck('karyawan')->toArray();

        $karyawan = MasterKaryawan::where('is_active', true)
            ->where('role', '!=', 1)
            ->whereNotIn('nama_lengkap', $existingKaryawan)
            ->select('nik_karyawan', 'nama_lengkap')
            ->get();

        $tipe = MasterFee::where('is_active', true)->get();
        
            return response()->json([
                'success' => true,
                'data' => $karyawan,
                'tipe' => $tipe,
                'message' => 'Available karyawan data retrieved successfully',
            ], 201);
    }  

    public function kalkulasiFeeKaryawan() 
    {
        $FeeKaryawan = FeeKaryawan::where('nik_karyawan', $request->nik_karyawan)
            ->where('is_active', true)
            ->first();
        $masterFee = MasterFee::where('id', $feeKaryawan->id_master_fee)
            ->where('is-active', true)
            ->first();
        $kalkulasi = KalkulasiFee::where('karyawan_id', $cek->karyawan_id)
            ->where('is_active', true)
            ->orderBy('id', 'desc')
            ->first();
        $firstDate = DATE('Y-m-01');
        $lastDate = DATE('Y-m-d');

        // if($kalkulasi){
        //     $firstDate = $kalkulasi->$tanggal_pencairan
        // }
    }
    
}