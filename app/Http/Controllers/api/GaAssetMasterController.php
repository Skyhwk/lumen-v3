<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAssetCabangAlias;
use App\Models\MasterCabang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GaAssetMasterController extends Controller
{
    public function listCabang()
    {
        $rows = MasterCabang::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'kode_cabang', 'nama_cabang']);

        return response()->json(['data' => $rows]);
    }

    public function listCabangAliases()
    {
        $rows = GaAssetCabangAlias::query()
            ->orderBy('alias')
            ->get(['id', 'alias', 'id_cabang']);

        return response()->json(['data' => $rows]);
    }

    public function storeCabangAlias(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'alias' => 'required|string|max:191',
            'id_cabang' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid'], 422);
        }

        $alias = strtoupper(trim($request->alias));
        $model = GaAssetCabangAlias::updateOrCreate(
            ['alias' => $alias],
            ['id_cabang' => (int) $request->id_cabang]
        );

        return response()->json([
            'message' => 'Alias cabang disimpan',
            'data' => $model,
        ]);
    }
}
