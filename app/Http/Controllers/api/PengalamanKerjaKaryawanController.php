<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Models\MasterCabang;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\PengalamanKerjaKaryawan;
use App\Models\User;
use App\Models\MedicalCheckup;
use Validator;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Exception;

class PengalamanKerjaKaryawanController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterKaryawan::with(['pengalaman_kerja'])->where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function showById(Request $request) 
    {
        $data = PengalamanKerjaKaryawan::where('karyawan_id',$request->karyawan_id)->where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = PengalamanKerjaKaryawan::create([
                'karyawan_id' => $request->karyawan_id,
                'nama_perusahaan' => $request->nama_perusahaan,
                'lokasi_perusahaan' => $request->lokasi_perusahaan,
                'posisi_kerja' => $request->posisi_kerja,
                'tgl_mulai_kerja' => $request->tgl_mulai_kerja,
                'tgl_berakhir_kerja' => $request->tgl_berakhir_kerja,
                'alasan_keluar' => $request->alasan_keluar,
                'created_by' => $this->karyawan,
                'created_at' => date('Y-m-d H:i:s'),
                'is_active' => true
            ]);
            
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

            $data = PengalamanKerjaKaryawan::find($request->id);
            $data->nama_perusahaan = $request->nama_perusahaan;
            $data->lokasi_perusahaan = $request->lokasi_perusahaan;
            $data->posisi_kerja = $request->posisi_kerja;
            $data->tgl_mulai_kerja = $request->tgl_mulai_kerja;
            $data->tgl_berakhir_kerja = $request->tgl_berakhir_kerja;
            $data->alasan_keluar = $request->alasan_keluar;
            $data->updated_by = $this->karyawan;
            $data->updated_at = date('Y-m-d H:i:s');
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

            $data = PengalamanKerjaKaryawan::find($request->id);
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