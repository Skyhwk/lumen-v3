<?php

namespace App\Http\Controllers\api;

use App\Models\MasterFeeSampling;
use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Models\HistoryLevelSampler;
use App\Models\MasterFee;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;



class LevelSamplerController extends Controller
{
    public function getsamplerApi(Request $request)
    {
        try {
            // $samplers = MasterKaryawan::with('jabatan');
            // if ($request->mode == 'add') {
            //     $samplers->whereIn('id_jabatan', [94]); // 'Sampler'
            // } else {
            //     $samplers->whereIn('id_jabatan', [70, 75, 94, 110]); // 'Sampler', 'K3 Staff','Technical Assurance Staff','Sampling Admin Staff'
            // }

            // $samplers = $samplers->where('is_active', true)
            //     ->orderBy('nama_lengkap')
            //     ->whereNotNull('warna')
            //     ->get();

            // $privateSampler = MasterKaryawan::with('jabatan')
            //     ->whereIn('id', [21, 56, 311, 531, 39, 95, 112, 377, 531, 35])
            //     ->where('is_active', true)
            //     ->whereNotNull('warna')
            //     ->orderBy('nama_lengkap')
            //     ->get();

            // // Ambil semua warna unik dari samplers
            
            $allSamplers = MasterKaryawan::whereNotNull('warna')->where('is_active', true)->get();
            
            $warnaArray = $allSamplers->pluck('warna')->unique()->filter();
            $label = MasterFeeSampling::whereIn('warna', $warnaArray)
                ->pluck('kategori', 'warna');

            // // Merge kedua label
            $allLabels = $label;

            // $allSamplers = $samplers->merge($privateSampler);
            // $allSamplers = $allSamplers->sortBy('nama_lengkap')->values();

            // // Tambahkan label ke setiap sampler
            $allSamplers->transform(function ($sampler) use ($allLabels) {
                $sampler->kategori_label = $allLabels->get($sampler->warna, 'Tidak ada kategori');
                return $sampler;
            });

            return Datatables::of($allSamplers)
                ->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'status' => '401'
            ], 401);
        }
    }

    public function showLabel()
    {
        $labels = MasterFeeSampling::select('id', 'kategori')->get();
        return response()->json(['data' => $labels], 200);
    }

    public function getSamplerNew()
    {
        $samplers = MasterKaryawan::with('jabatan')
            ->where('id_jabatan', 94) // 'Sampler'
            ->where('is_active', true)
            ->whereNull('warna')
            ->orderBy('nama_lengkap')
            ->get();

        $perbantuan_sampler = DB::table('perbantuan_sampler')->where('is_active', true)->get()->pluck('user_id');
        $sampler_perbantuan = MasterKaryawan::whereIn('id', $perbantuan_sampler)->whereNull('warna')->where('is_active', true)->get();

        $samplers = $samplers->merge($sampler_perbantuan)->values();

        return response()->json(['data' => $samplers], 200);
    }

    public function updateLevelSampling(Request $request)
    {
        $sampler = MasterKaryawan::where('id', $request->id)->first();
        if (!$sampler) {
            return response()->json([
                'message' => 'Sampler not found',
            ], 404);
        }
        
        $history = new HistoryLevelSampler();

        $history->user_id = $sampler->id;
        $history->created_at = Carbon::now()->format('Y-m-d H:i:s');
        $history->created_by = $this->karyawan;
        $history->old_warna = $sampler->warna;
        $history->old_level = MasterFeeSampling::where('warna', $sampler->warna)->first()->kategori ?? null;

        DB::beginTransaction();
        try {
            $warnaSampler = MasterFeeSampling::where('id', $request->id_label)->first();
            $sampler->warna = $warnaSampler->warna;
            $sampler->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $sampler->updated_by = $this->karyawan;
            $sampler->save();

            $history->new_warna = $sampler->warna;
            $history->new_level = $warnaSampler->kategori;
            $history->save();

            DB::commit();

            return response()->json([
                'message' => 'Level Sampler Berhasil Di Update!',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update level sampler: ' . $th->getMessage(),
                'status' => '500'
            ], 500);
        }


    }

}
