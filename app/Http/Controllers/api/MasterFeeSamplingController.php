<?php

namespace App\Http\Controllers\api;

use App\Models\Withdraw;
use App\Models\MasterFeeSampling;
use App\Services\GenerateToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Datatables;
use Carbon\Carbon;


class MasterFeeSamplingController extends Controller
{

    public function index(Request $request)
    {
        try {
            $masterFee = MasterFeeSampling::where('is_active', true);

            return datatables()->of($masterFee)->make(true);
            
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function store(Request $request)
    {
        $warna = '';

        switch ($request->kategori) {
            case 'Team Leader':
                $warna = 'Merah';
                break;
            case 'Senior Sampler':
                $warna = 'Biru Tua';
                break;
            case 'Junior Sampler':
                $warna = 'Biru Muda';
                break;
            case 'Senior Trainee':
                $warna = 'Hijau Tua';
                break;
            case 'Junior Trainee':
                $warna = 'Hijau Muda';
                break;
            case 'Senior Helper':
                $warna = 'Oren Tua';
                break;
            case 'Junior Helper':
                $warna = 'Oren Muda';
                break;
            case 'Kemungkinan Pelatihan':
                $warna = 'Pink';
                break;
            default:
                $warna = 'null';
                break;
        }

        DB::beginTransaction();
        try {
            MasterFeeSampling::Insert([
                'kategori' => $request->kategori,
                'warna' => $warna,
                'titik_1' => floatval(preg_replace('/[Rp., ]/', '', $request->titik_1)),
                'titik_2' => floatval(preg_replace('/[Rp., ]/', '', $request->titik_2)),
                'titik_3' => floatval(preg_replace('/[Rp., ]/', '', $request->titik_3)),
                'hari_libur' => floatval(preg_replace('/[Rp., ]/', '', $request->hari_libur)),
                'sampling_24jam' => floatval(preg_replace('/[Rp., ]/', '', $request->sampling_24jam)),
                'sampling_luar_kota' => floatval(preg_replace('/[Rp., ]/', '', $request->sampling_luar_kota)),
                'sampling_luar_kota_24jam' => floatval(preg_replace('/[Rp., ]/', '', $request->sampling_luar_kota_24jam)),
                'isokinetik' => floatval(preg_replace('/[Rp., ]/', '', $request->isokinetik)),
                'keterangan' => $request->keterangan,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now(),
            ]);
            

            DB::commit();

            return response()->json([
                'message' => "Fee Sampling " . $request->kategori . " Has Been created."
            ]);
            
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function update(Request $request)
    {
        DB::beginTransaction();
        try {
            $fee = MasterFeeSampling::find($request->id);

            if (!$fee) {
                return response()->json([
                    'message' => 'Data tidak ditemukan.',
                ], 404);
            }

            $fee->update([
                'titik_1' => floatval(preg_replace('/[Rp., ]/', '', $request->titik_1)),
                'titik_2' => floatval(preg_replace('/[Rp., ]/', '', $request->titik_2)),
                'titik_3' => floatval(preg_replace('/[Rp., ]/', '', $request->titik_3)),
                'hari_libur' => floatval(preg_replace('/[Rp., ]/', '', $request->hari_libur)),
                'sampling_24jam' => floatval(preg_replace('/[Rp., ]/', '', $request->sampling_24jam)),
                'sampling_luar_kota' => floatval(preg_replace('/[Rp., ]/', '', $request->sampling_luar_kota)),
                'sampling_luar_kota_24jam' => floatval(preg_replace('/[Rp., ]/', '', $request->sampling_luar_kota_24jam)),
                'isokinetik' => floatval(preg_replace('/[Rp., ]/', '', $request->isokinetik)),
                'keterangan' => $request->keterangan,
                'updated_by' => $this->karyawan,
                'updated_at' => Carbon::now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => "Fee Sampling " . $request->kategori . " has been updated successfully."
            ]);
            
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            $fee = MasterFeeSampling::find($request->id);

            if (!$fee) {
                return response()->json([
                    'message' => 'Data tidak ditemukan.',
                ], 404);
            }

            $fee->update([
                'is_active' => false,
                'deleted_by' => $this->karyawan,
                'deleted_at' => Carbon::now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => "Fee Sampling " . $fee->kategori . " has been deleted successfully."
            ]);
            
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

}