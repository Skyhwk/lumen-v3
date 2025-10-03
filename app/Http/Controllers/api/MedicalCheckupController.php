<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\MasterCabang;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
// use App\Models\RekamMedisKaryawan;
use App\Models\User;
use App\Models\MedicalCheckup;
use Validator;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Exception;

class MedicalCheckupController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterKaryawan::with(['medical'])->where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function showById(Request $request) 
    {
        $data = MedicalCheckup::where('karyawan_id', $request->karyawan_id)->where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $allowedBloodTypes = ['A', 'B', 'AB', 'O'];
            
            if (!in_array($request->golongan_darah, $allowedBloodTypes)) {
                return response()->json(['messages' => 'Invalid blood type.'], 400);
            }  
         
            $data = [
                'karyawan_id' => $request->karyawan_id,
                'tinggi_badan' => $request->tinggi_badan,
                'berat_badan' => $request->berat_badan,
                // 'keterangan_mata' => $request->keterangan_mata ?? null, 
                // 'rate_mata' => $request->rate_mata ?? null,    
                'golongan_darah' => $request->golongan_darah,
                'penyakit_bawaan_lahir' => $request->penyakit_bawaan_lahir ?? null,
                'penyakit_kronis' => $request->penyakit_kronis ?? null,
                'riwayat_kecelakaan' => $request->riwayat_kecelakaan ?? null,
                'created_by' => $this->karyawan,
                'created_at' => date('Y-m-d H:i:s'),
                'is_active' => true,
            ];

            if (!empty($request->keterangan_mata)) {
                $data['keterangan_mata'] = $request->keterangan_mata;
                $data['rate_mata'] = $request->rate_mata ?? null; 
            }

            $medicalCheckup = MedicalCheckup::create($data);
            
            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            
            DB::beginTransaction();
            
            $allowedBloodTypes = ['A', 'B', 'AB', 'O'];
            
            $data = MedicalCheckup::find($request->id);

            if (!in_array($request->golongan_darah, $allowedBloodTypes)) {
                return response()->json(['messages' => 'Invalid blood type.'], 400);
            }        

            $data->tinggi_badan = $request->tinggi_badan ?? $data->tinggi_badan;
            $data->berat_badan = $request->berat_badan ?? $data->berat_badan;
            // $data->keterangan_mata = $request->keterangan_mata ?? $data->keterangan_mata;
            // $data->rate_mata = $request->rate_mata ?? $data->rate_mata;
            $data->golongan_darah = $request->golongan_darah ?? $data->golongan_darah;
            $data->penyakit_bawaan_lahir = $request->penyakit_bawaan_lahir ?? $data->penyakit_bawaan_lahir;
            $data->penyakit_kronis = $request->penyakit_kronis ?? $data->penyakit_kronis;
            $data->riwayat_kecelakaan = $request->riwayat_kecelakaan ?? $data->riwayat_kecelakaan;
            $data->updated_by = $this->karyawan;
            $data->updated_at = date('Y-m-d H:i:s');

            if (!empty($request->keterangan_mata)) {
                $data['keterangan_mata'] = $request->keterangan_mata;
                $data['rate_mata'] = $request->rate_mata ?? null;
            }

            $data->save();


            DB::commit();
            return response()->json(['message' => 'Data berhasil diupdate', 'data' => $data], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = MedicalCheckup::find($request->id);
            $data->is_active = false;
            $data->deleted_by = $this->karyawan;
            $data->deleted_at = date('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil dihapus'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}