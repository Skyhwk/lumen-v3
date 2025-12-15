<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use DataTables;
use Carbon\Carbon;

use App\Models\{
    SaldoFeeSales,
    WithdrawalFeeSales
};

class WithdrawalFeeSalesController extends Controller
{
    public function index()
    {
        $withdrawalFeeSales = WithdrawalFeeSales::with('sales')->latest();

        return DataTables::of($withdrawalFeeSales)
            ->filterColumn('sales.nama_lengkap', function ($query, $keyword) {
                $query->whereHas('sales', function ($query) use ($keyword) {
                    $query->where('nama_lengkap', 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);
    }

    public function approve(Request $request)
    {
        $withdrawalFeeSales = WithdrawalFeeSales::find($request->id);

        $withdrawalFeeSales->status = 'Approved';
        $withdrawalFeeSales->approved_by = $this->karyawan;
        $withdrawalFeeSales->approved_at = Carbon::now();

        $saldoFeeSales = SaldoFeeSales::where('sales_id', $withdrawalFeeSales->sales_id)->first();
        $saldoFeeSales->amount -= $withdrawalFeeSales->amount;
        $saldoFeeSales->save();
 
        $withdrawalFeeSales->save();

        return response()->json(['message' => 'Withdrawal Fee Sales approved successfully'], 200);
    }

    public function reject(Request $request)
    {
        $withdrawalFeeSales = WithdrawalFeeSales::find($request->id);

        $withdrawalFeeSales->status = 'Rejected';
        $withdrawalFeeSales->rejected_by = $this->karyawan;
        $withdrawalFeeSales->rejected_at = Carbon::now();
        $withdrawalFeeSales->reject_reason = $request->reason;

        $withdrawalFeeSales->save();

        return response()->json(['message' => 'Withdrawal Fee Sales rejected successfully'], 200);
    }
}
