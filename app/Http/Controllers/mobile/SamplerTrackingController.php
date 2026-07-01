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
}