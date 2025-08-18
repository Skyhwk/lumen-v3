<?php

namespace App\Http\Controllers\api;

use App\Models\MasterFeeSampling;
use Illuminate\Http\Request;
use App\Models\SamplingPlan;
use App\Models\MasterKaryawan;
use App\Models\Jadwal;
use App\Models\JadwalLibur;
use App\Models\MasterDriver;
use App\Models\PraNoSample;
use App\Models\QuotationKontrakH;
use App\Models\MasterCabang;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Jobs\RenderSamplingPlan;
use App\Services\JadwalServices;
use App\Services\GetAtasan;
use App\Services\Notification;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\RenderSamplingPlan as RenderSamplingPlanService;
use App\Jobs\RenderAndEmailJadwal;
use App\Models\JobTask;


class LevelSamplerController extends Controller
{



    public function getsamplerApi(Request $request)
    {
        try {
            $samplers = MasterKaryawan::with('jabatan');
            if ($request->mode == 'add') {
                $samplers->whereIn('id_jabatan', [94]); // 'Sampler'
            } else {
                $samplers->whereIn('id_jabatan', [70, 75, 94, 110]); // 'Sampler', 'K3 Staff','Technical Assurance Staff','Sampling Admin Staff'
            }

            $samplers = $samplers->where('is_active', true)
                ->orderBy('nama_lengkap')
                ->get();

            $privateSampler = MasterKaryawan::with('jabatan')
                ->whereIn('id', [21, 56, 311, 531, 39, 95, 112, 377, 531, 35])
                ->where('is_active', true)
                ->orderBy('nama_lengkap')
                ->get();

            // Ambil semua warna unik dari samplers
            $warnaArray = $samplers->pluck('warna')->unique()->filter();
            $label = MasterFeeSampling::whereIn('warna', $warnaArray)
                ->pluck('kategori', 'warna');

            // Ambil semua warna unik dari privateSampler
            $warnaPrivateArray = $privateSampler->pluck('warna')->unique()->filter();
            $labelPrivate = MasterFeeSampling::whereIn('warna', $warnaPrivateArray)
                ->pluck('kategori', 'warna');

            // Merge kedua label
            $allLabels = $label->merge($labelPrivate);

            $allSamplers = $samplers->merge($privateSampler);
            $allSamplers = $allSamplers->sortBy('nama_lengkap')->values();

            // Tambahkan label ke setiap sampler
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

    public function updateLevelSampling(Request $request)
    {
        $sampler = MasterKaryawan::where('id', $request->id)->first();
        if (!$sampler) {
            return response()->json([
                'message' => 'Sampler not found',
            ], 404);
        }

        DB::beginTransaction();
        try {
            $warnaSampler = MasterFeeSampling::where('id', $request->id_label)->first();
            $sampler->warna = $warnaSampler->warna;
            $sampler->save();
            DB::commit();

            return response()->json([
                'message' => 'Level sampling updated successfully',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update level sampling: ' . $th->getMessage(),
                'status' => '500'
            ], 500);
        }


    }

}
