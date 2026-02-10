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

class WithdrawalFeeSalesController extends Controller
{
    public function index()
    {
        $withdrawalFeeSales = WithdrawalFeeSales::with('sales')->where('is_active', true)->latest();

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
        $timestamp = Carbon::now();

        $withdrawalFeeSales = WithdrawalFeeSales::where(['id' => $request->id, 'is_active' => true])->latest()->first();
        $withdrawalFeeSales->status = 'Approved';
        $withdrawalFeeSales->approved_by = $this->karyawan;
        $withdrawalFeeSales->approved_at = $timestamp;

        $mutasiFeeSales = new MutasiFeeSales();

        $mutasiFeeSales->sales_id = $withdrawalFeeSales->sales_id;
        $mutasiFeeSales->batch_number = str_replace('.', '/', microtime(true));
        $mutasiFeeSales->mutation_type = 'Debit';
        $mutasiFeeSales->amount = $withdrawalFeeSales->amount;
        $mutasiFeeSales->description = 'Withdrawal Approved by Finance';
        $mutasiFeeSales->status = 'Done';
        $mutasiFeeSales->created_by = $this->karyawan;
        $mutasiFeeSales->created_at = $timestamp;

        $mutasiFeeSales->save();

        $withdrawalFeeSales->save();

        return response()->json(['message' => 'Withdrawal Fee Sales approved successfully'], 200);
    }

    public function reject(Request $request)
    {
        $timestamp = Carbon::now();

        $withdrawalFeeSales = WithdrawalFeeSales::where(['id' => $request->id, 'is_active' => true])->latest()->first();
        $withdrawalFeeSales->status = 'Rejected';
        $withdrawalFeeSales->rejected_by = $this->karyawan;
        $withdrawalFeeSales->rejected_at = $timestamp;
        $withdrawalFeeSales->reject_reason = $request->reason;

        $mutasiFeeSales = new MutasiFeeSales();

        $mutasiFeeSales->sales_id = $withdrawalFeeSales->sales_id;
        $mutasiFeeSales->batch_number = str_replace('.', '/', microtime(true));
        $mutasiFeeSales->mutation_type = 'Kredit';
        $mutasiFeeSales->amount = $withdrawalFeeSales->amount;
        $mutasiFeeSales->description = 'Withdrawal Rejected by Finance, Balance Restored';
        $mutasiFeeSales->status = 'Done';
        $mutasiFeeSales->created_by = $this->karyawan;
        $mutasiFeeSales->created_at = $timestamp;

        $mutasiFeeSales->save();

        $saldoFeeSales = SaldoFeeSales::where('sales_id', $withdrawalFeeSales->sales_id)->first();
        $saldoFeeSales->active_balance += $withdrawalFeeSales->amount;
        $saldoFeeSales->updated_by = 'System';
        $saldoFeeSales->updated_at = $timestamp;
        $saldoFeeSales->save();

        $withdrawalFeeSales->save();

        return response()->json(['message' => 'Withdrawal Fee Sales rejected successfully'], 200);
    }
}
