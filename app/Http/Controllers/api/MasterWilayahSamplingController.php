<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Datatables;
use App\Models\MasterCabang;
use App\Models\HargaTransportasi;
use App\Models\MasterWilayahSampling;

class MasterWilayahSamplingController extends Controller
{
    public function index(Request $request)
    {
        $data = MasterWilayahSampling::with('cabang')
            ->where('id_cabang', $request->id_cabang)
            ->where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        $data = MasterWilayahSampling::updateOrCreate(
            ['id' => $request->id],
            [
                'id_cabang' => $request->id_cabang,
                'wilayah' => $request->wilayah,
                'status_wilayah' => $request->status_wilayah,
                'created_by' => $this->karyawan,
                'updated_by' => $this->karyawan,
            ]
        );

        return response()->json(['data' => $data, 'message' => 'Saved successfully', 'success' => true], 200);
    }

    public function getCabang()
    {
        $data = MasterCabang::where('is_active', true)->get();

        return response()->json(['data' => $data, 'status' => 200, 'success' => true], 200);
    }

    public function getWilayah()
    {
        $data = HargaTransportasi::distinct('wilayah')->where('is_active', true)->pluck('wilayah')->toArray();

        return response()->json(['data' => $data, 'status' => 200, 'success' => true], 200);
    }

    public function destroy(Request $request)
    {
        $data = MasterWilayahSampling::where('id', $request->id)->first();
        $data->is_active = false;
        $data->save();

        return response()->json(['message' => 'Deleted successfully', 'success' => true], 200);
    }
}
