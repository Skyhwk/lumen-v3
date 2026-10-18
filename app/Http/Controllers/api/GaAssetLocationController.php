<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterCabang;

class GaAssetLocationController extends Controller
{
    public function branches()
    {
        $rows = MasterCabang::query()
            ->where('is_active', 1)
            ->orderBy('nama_cabang')
            ->get(['id', 'kode_cabang', 'nama_cabang']);

        return response()->json(['data' => $rows]);
    }
}
