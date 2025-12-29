<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use DataTables;

use App\Models\{
    MasterKaryawan,
    SaldoFeeSales,
    MutasiFeeSales,
    WithdrawalFeeSales
};

class SaldoFeeSalesController extends Controller
{
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

    public function getSaldoFeeSales(Request $request)
    {
        $saldoFeeSales = SaldoFeeSales::where('sales_id', $request->salesId)->latest()->first();
        if (!$saldoFeeSales) return response()->json(['message' => 'Saldo Fee Sales not found'], 404);

        $pendingWithdrawal = WithdrawalFeeSales::where('sales_id', $request->salesId)->where('status', 'pending');

        $mutasiStats = MutasiFeeSales::where('sales_id', $request->salesId)
            ->whereMonth('created_at', $request->month)
            ->whereYear('created_at', $request->year)
            ->selectRaw("SUM(CASE WHEN mutation_type = 'Debit' THEN amount ELSE 0 END) as total_debit")
            ->selectRaw("SUM(CASE WHEN mutation_type = 'Kredit' THEN amount ELSE 0 END) as total_credit")
            ->first();

        $saldoFeeSales->total_debit = $mutasiStats->total_debit;
        $saldoFeeSales->total_credit = $mutasiStats->total_credit;

        return response()->json([
            'saldoFeeSales' => $saldoFeeSales,
            'pendingWithdrawal' => [
                'amount' => $pendingWithdrawal->sum('amount'),
                'count' => $pendingWithdrawal->count(),
            ],
            'message' => 'Saldo Fee Sales retrieved successfully',
        ], 200);
    }

    public function getMutasiSaldo(Request $request)
    {
        $mutasiSaldo = MutasiFeeSales::where('sales_id', $request->salesId)
            ->whereMonth('created_at', $request->month)
            ->whereYear('created_at', $request->year)
            ->latest();

        return DataTables::of($mutasiSaldo)->make(true);
    }

    public function getWithdrawal(Request $request)
    {
        $withdrawalFeeSales = WithdrawalFeeSales::where('sales_id', $request->salesId)
            ->whereMonth('created_at', $request->month)
            ->whereYear('created_at', $request->year)
            ->latest();

        return DataTables::of($withdrawalFeeSales)->make(true);
    }

    public function requestWithdrawal(Request $request)
    {
        $withdrawalFeeSales = new WithdrawalFeeSales();

        $withdrawalFeeSales->sales_id = $request->sales_id;
        $withdrawalFeeSales->batch_number = str_replace('.', '/', microtime(true));;
        $withdrawalFeeSales->amount = $request->amount;
        $withdrawalFeeSales->description = $request->description;
        $withdrawalFeeSales->status = 'Pending';
        $withdrawalFeeSales->created_by = $this->karyawan;
        $withdrawalFeeSales->updated_by = $this->karyawan;

        $withdrawalFeeSales->save();

        return response()->json(['message' => 'Withdrawal Fee Sales requested successfully'], 201);
    }
}
