<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ViewCustomerPoints;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;

class ListCustomerTierController extends Controller
{
    public function index(Request $request)
    {
        $customerTiers = ViewCustomerPoints::select('pelanggan_id', 'nama_pelanggan', 'sales_penanggung_jawab', 'points_balance', 'tier_points', 'tier_name');

        // Kolom untuk keamanan agar hanya kolom ini yang bisa diorder
        $orderableColumns = [
            'pelanggan_id',
            'nama_pelanggan',
            'sales_penanggung_jawab',
            'points_balance',
            'tier_points',
            'tier_name'
        ];

        // Cek apakah ada parameter order dan order[0][name]
        $orderName = $request->input('order.0.name');
        $orderDir = $request->input('order.0.dir', 'asc');

        // Validasi kolom order, apply sorting bila valid
        if ($orderName && in_array($orderName, $orderableColumns)) {
            $customerTiers = $customerTiers->orderBy($orderName, $orderDir === 'desc' ? 'desc' : 'asc');
        }

        return Datatables::of($customerTiers)->make(true);
    }

}