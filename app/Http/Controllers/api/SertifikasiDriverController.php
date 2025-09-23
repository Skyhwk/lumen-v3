<?php

namespace App\Http\Controllers\api;


use App\Models\MasterDriver;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;
use Carbon\Carbon;





class SertifikasiDriverController extends Controller
{
    public function index()
    {
        $data = MasterDriver::where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function getSampler(Request $request)
    {
        try {
            $driverUserIds = MasterDriver::where('is_active', 1)
                ->pluck('user_id')
                ->toArray();

            $samplers = MasterKaryawan::with('jabatan');

            if ($request->mode == 'add') {
                $samplers->whereIn('id_jabatan', [94]);
            } else {
                $samplers->whereIn('id_jabatan', [70, 75, 94, 110]);
            }

            $samplers = $samplers->where('is_active', true)
                ->whereNotIn('id', $driverUserIds)
                ->orderBy('nama_lengkap')
                ->get();

            $privateSampler = MasterKaryawan::with('jabatan')
                ->whereIn('id', [21, 56, 311, 531, 39, 95, 112, 377, 531, 35, 171])
                ->where('is_active', true)
                ->whereNotIn('id', $driverUserIds)
                ->orderBy('nama_lengkap')
                ->get();

            $allSamplers = $samplers->merge($privateSampler);
            $allSamplers = $allSamplers->sortBy('nama_lengkap')->values();

            return Datatables::of($allSamplers)->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => '401'
            ], 401);
        }
    }

    public function createDriver(Request $request)
    {
        if (strlen($request->nomor_sim) > 20) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nomor SIM tidak boleh lebih dari 20 karakter.'
            ], 422);
        }

        try {
            $get = MasterKaryawan::where('nama_lengkap', $request->nama_driver)->first();

            if (!$get) {
                return response()->json([
                    'message' => 'Data Nama Tersebut Tidak Ditemukan',
                ], 404);
            }

            $cek = MasterDriver::where('user_id', $get->user_id)->first();

            if ($cek) {
                MasterDriver::where('user_id', $get->user_id)->update([
                    'tipe_sim' => $request->tipe_sim,
                    'nomor_sim' => $request->nomor_sim,
                    'expired' => $request->expired,
                    'is_active' => true,
                    'updated_by' => $this->karyawan,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'deleted_at' => null,
                    'deleted_by' => null
                ]);

                return response()->json([
                    'message' => 'Data Driver Berhasil Di Tambahkan!',
                ], 200);
            } else {
                $data = MasterDriver::create([
                    'user_id' => $get->user_id,
                    'nama_driver' => $request->nama_driver,
                    'tipe_sim' => $request->tipe_sim,
                    'nomor_sim' => $request->nomor_sim,
                    'expired' => $request->expired,
                    'is_active' => true,
                    'created_by' => $this->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s')
                ]);

                return response()->json([
                    'message' => 'Data Driver Berhasil Di Tambahkan',

                ], 200);

            }
        } catch (\Throwable $th) {
            // return response()->json([
            //     'message' => 'Gagal membuat data driver!, Silahkan hubungi IT',
            //     'status' => 'Error'
            // ],500);
            return response()->json([
                'message' => 'Failed to create driver: ' . $th->getMessage(),
                'status' => '500'
            ], 500);
        }
    }

    public function updateDriver(Request $request)
    {
        try {
            $data = MasterDriver::where('id', $request->id)->update([
                'tipe_sim' => $request->tipe_sim,
                'nomor_sim' => $request->nomor_sim,
                'expired' => $request->expired,
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            return response()->json([
                'message' => 'Data Driver Berhasil Di Update!',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Gagal mengupdate data driver!, Silahkan hubungi IT',
                'status' => 'Error'
            ], 500);
            // return response()->json([
            //     'message' => 'Failed to update driver: ' . $th->getMessage(),
            //     'status' => '500'
            // ], 500);
        }


    }

    public function deleteDriver(Request $request)
    {
        try {
            $data = MasterDriver::where('id', $request->id)->update([
                'is_active' => false,
                'deleted_by' => $this->karyawan,
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            return response()->json([
                'message' => 'Data Driver Berhasil Di Hapus!',
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Gagal menghapus data driver!, Silahkan hubungi IT',
                'status' => 'Error'
            ], 500);

            // return response()->json([
            //     'message' => 'Failed to delete driver: ' . $th->getMessage(),
            //     'status' => '500'
            // ], 500);

        }

    }
}


