<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\DasarTargetPenjadwalan;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

use App\Services\KalkulasiTargetPenjadwalanService;

class DasarTargetPenjadwalanController extends Controller
{
    public function index(Request $request)
    {
        $data = DasarTargetPenjadwalan::where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {

            if ($request->id) {
                $data = DasarTargetPenjadwalan::find($request->id);
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now();
            } else {
                $data = new DasarTargetPenjadwalan();
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now();
            }
            $data->persentase_awal = $request->persentase_awal;
            $data->persentase_akhir = $request->persentase_akhir;
            $data->color = $request->color;
            $data->keterangan = $request->keterangan;
            $data->save();

            DB::commit();
            return response()->json(['message' => 'Data Berhasil Disimpan']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Data Gagal Disimpan: ' . $e->getMessage()], 500);
        }
    }

    public function delete($id)
    {
        $data = DasarTargetPenjadwalan::find($id);
        if (!$data) return response()->json(['message' => 'Data Tidak Ditemukan'], 404);

        $data->is_active = false;
        $data->deleted_by = $this->karyawan;
        $data->deleted_at = Carbon::now();
        $data->save();

        return response()->json(['message' => 'Data Berhasil Dihapus']);
    }

    // protected $service;

    // public function __construct(KalkulasiTargetPenjadwalanService $service)
    // {
    //     $this->service = $service;
    // }

    // public function test()
    // {
    //     return $this->service->execute();
    // }
}