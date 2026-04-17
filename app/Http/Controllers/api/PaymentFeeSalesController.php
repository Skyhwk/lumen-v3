<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use DataTables;
use Carbon\Carbon;

use App\Models\{
    SaldoFeeSales,
    MutasiFeeSales,
    WithdrawalFeeSales
};

class PaymentFeeSalesController extends Controller
{
    public function index(Request $request)
    {
        $withdrawalFeeSales = WithdrawalFeeSales::with('sales')
        ->whereIn('status', $request->status)
        ->where('is_active', true)
        ->whereNull('transfer_date')
        ->latest();
        // $withdrawalFeeSales = WithdrawalFeeSales::with('sales')->where('is_active', true)->latest();

        return DataTables::of($withdrawalFeeSales)
            ->filterColumn('sales.nama_lengkap', function ($query, $keyword) {
                $query->whereHas('sales', function ($query) use ($keyword) {
                    $query->where('nama_lengkap', 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);
    }

    public function setDate(Request $request)
    {
        $withdrawalFeeSales = WithdrawalFeeSales::find($request->id);
        $withdrawalFeeSales->transfer_date = $request->date;
        $withdrawalFeeSales->save();

        return response()->json(['message' => 'Withdrawal Fee Sales Successfully Set Date Transfer'], 200);
    }
}
