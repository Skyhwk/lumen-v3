<?php

namespace App\Http\Controllers\mobile;

use Illuminate\Http\Request;

class SamplerTrackingController extends \App\Http\Controllers\api\SamplerTrackingController
{
    public function index(Request $request)
    {
        $data = $this->service->listByDate(
            $request->tanggal,
            null,
            $this->karyawan
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
    public function updateRouteOrder(Request $request)
    {
        $this->validate($request, [
            'tanggal' => 'required',
            'reason' => 'required',
            'items' => 'required|array',
            'items.*.session_id' => 'required',
            'items.*.route_order' => 'nullable',
        ]);

        $payload = $request->all();
        $payload['sampler_name'] = $this->karyawan;

        $data = $this->service->updateRouteOrder($payload, $this->karyawan);

        return response()->json([
            'success' => true,
            'message' => 'Urutan tujuan sampling berhasil disimpan.',
            'data' => $data,
        ]);
    }
}