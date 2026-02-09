<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\{
    MasterKaryawan,
    MasterTargetSales,
    MasterFeeSales
};

class FeeSalesController extends Controller
{
    private $indoMonth = [
        '01' => 'januari',
        '02' => 'februari',
        '03' => 'maret',
        '04' => 'april',
        '05' => 'mei',
        '06' => 'juni',
        '07' => 'juli',
        '08' => 'agustus',
        '09' => 'september',
        '10' => 'oktober',
        '11' => 'november',
        '12' => 'desember',
    ];

    private $idJabatanSales = [
        24, // Sales Officer
        148, // Customer Relation Officer
    ];

    public function getSalesList(Request $request)
    {
        $currentUser = $request->attributes->get('user')->karyawan;

        $sales = MasterKaryawan::where('is_active', true)
            ->whereIn('id_jabatan', $this->idJabatanSales)
            ->orWhere('nama_lengkap', 'Novva Novita Ayu Putri Rukmana')
            ->when(in_array($currentUser->id_jabatan, $this->idJabatanSales) || $currentUser->nama_lengkap == 'Novva Novita Ayu Putri Rukmana', fn($q) => $q->where('id', $currentUser->id))
            ->orderBy('nama_lengkap', 'asc')
            ->get();

        return response()->json([
            'sales' => $sales,
            'message' => 'Sales list retrieved successfully',
        ], 200);
    }

    public function getFeeSales(Request $request)
    {
        $targetSales = MasterTargetSales::where([
            'karyawan_id' => $request->salesId,
            'tahun' => $request->year,
            'is_active' => true
        ])->whereNotNull($this->indoMonth[$request->month])->latest()->first();

        if (!$targetSales) return response()->json(['message' => 'Target Sales not found'], 404);

        $masterFeeSales = MasterFeeSales::where([
            'sales_id' => $request->salesId,
            'period' => $request->year . '-' . $request->month,
            'is_active' => true
        ])->latest()->first();

        return response()->json([
            'targetSales' => $targetSales,
            'feeSales' => $masterFeeSales,
            'message' => 'Target Sales retrieved successfully',
        ], 200);
    }
}
