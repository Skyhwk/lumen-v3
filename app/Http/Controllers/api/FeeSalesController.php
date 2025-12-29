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
    private $indonesianMonthStr = [
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
        // 15, // Sales Manager
        // 21, // Sales Supervisor
        // 22, // Sales Admin Supervisor
        // 23, // Senior Sales Admin Staff
        24, // Sales Officer
        // 25, // Sales Admin Staff
        // 140, // Sales Assistant Manager
        // 145, // Sales Intern
        // 147, // Sales & Marketing Manager
        // 154, // Senior Sales Manager
        // 155, // Sales Executive
        // 156, // Sales Staff
        148, // Customer Relation Officer
        // 157, // Customer Relationship Officer Manager
    ];

    public function getSalesList(Request $request)
    {
        $currentUser = $request->attributes->get('user')->karyawan;

        $sales = MasterKaryawan::whereIn('id_jabatan', $this->idJabatanSales)
            ->when(in_array($currentUser->id_jabatan, $this->idJabatanSales) || $currentUser->nama_lengkap == 'Novva Novita Ayu Putri Rukmana', fn($q) => $q->where('id', $currentUser->id))
            ->where('is_active', true)
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
        ])->whereNotNull($this->indonesianMonthStr[$request->month])->latest()->first();

        if (!$targetSales) return response()->json(['message' => 'Target Sales not found'], 404);

        $masterFeeSales = MasterFeeSales::where([
            'sales_id' => $request->salesId,
            'period' => $request->year . '-' . $request->month,
        ])->latest()->first();

        return response()->json([
            'targetSales' => $targetSales,
            'feeSales' => $masterFeeSales,
            'message' => 'Target Sales retrieved successfully',
        ], 200);
    }
}
