<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\PerbantuanSampler;
use App\Models\MasterKaryawan;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PerbantuanSamplerController extends Controller
{
    public function index()
    {
        try {
            $data = PerbantuanSampler::with('users')->where('is_active', true);
            return Datatables::of($data)->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(), 
                'line' => $e->getLine(), 
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function getKaryawan()
    {
        $existingKaryawan = PerbantuanSampler::where('is_active', true)->pluck('id')->toArray();

        $karyawan = MasterKaryawan::where('is_active', true)
            ->whereNotIn('id', $existingKaryawan)
            ->select('id', 'nama_lengkap')
            ->get();
        
            return response()->json([
                'success' => true,
                'data' => $karyawan,
                'message' => 'Available karyawan data retrieved successfully',
            ], 201);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $userId = $request->user_id;
            $namaLengkap = $request->nama_lengkap;

            // jika user_id bukan angka → berarti new tags
            if (!is_numeric($userId)) {
                $userId = str_replace(".", "", microtime(true));

                // rapikan nama manual
                $namaLengkap = Str::title(Str::lower($namaLengkap ?? $userId));
            } 

            // CEK DUPLIKASI USER
            $exists = PerbantuanSampler::where('nama_lengkap', $namaLengkap)
                ->where('is_active', true)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Karyawan sudah terdaftar sebagai perbantuan',
                ], 401);
            }

            // SIMPAN DATA
            $data = new PerbantuanSampler();
            $data->user_id = $userId;
            $data->nama_lengkap = $namaLengkap;
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$namaLengkap} berhasil ditambahkan",
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {

            // CEK DUPLIKASI USER
            $exists = PerbantuanSampler::where('id', $request->id)
                ->where('is_active', true)
                ->first();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Karyawan tidak ditemukan',
                ], 422);
            }

            $exists->is_active = false;
            $exists->deleted_by = $this->karyawan;
            $exists->deleted_at = Carbon::now()->format('Y-m-d H:i:s');

            $exists->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Data Berhasil dihapus",
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}