<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use DataTables;

use App\Models\{
    LimitWithdraw,
    MasterKaryawan,
    SaldoFeeSales,
    MutasiFeeSales,
    WithdrawalFeeSales
};
use Illuminate\Support\Carbon;

class SaldoFeeSalesController extends Controller
{
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

    public function getSaldoFeeSales(Request $request)
    {
        $saldoFeeSales = SaldoFeeSales::where('sales_id', $request->salesId)->latest()->first();
        if (!$saldoFeeSales) return response()->json(['message' => 'Saldo Fee Sales not found'], 404);

        $limit = 0;
        $limitWithdraw = LimitWithdraw::where('user_id', $request->salesId)->latest()->first();
        if ($limitWithdraw) {
            $usedLimit = WithdrawalFeeSales::where('sales_id', $request->salesId)
                ->whereIn('status', ['Pending', 'Approved'])
                ->where(fn($q) => $q->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month))
                ->sum('amount');
            $limit = $limitWithdraw->limit - $usedLimit;
        }

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
            'limitWithdraw' => $limit,
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
        $limitWithdraw = LimitWithdraw::where('user_id', $request->sales_id)->latest()->first();
        if (!$limitWithdraw) return response()->json(['message' => 'Limit penarikan belum diatur'], 404);
        $usedLimit = WithdrawalFeeSales::where('sales_id', $request->sales_id)
            ->whereIn('status', ['Pending', 'Approved'])
            ->where(fn($q) => $q->whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month))
            ->sum('amount');
        $limit = $limitWithdraw->limit - $usedLimit;

        if ($request->amount > $limit) return response()->json(['message' => 'Permintaan anda melebihi batas penarikan pada periode ini'], 400);

        $withdrawalFeeSales = new WithdrawalFeeSales();

        $withdrawalFeeSales->sales_id = $request->sales_id;
        $withdrawalFeeSales->batch_number = str_replace('.', '/', microtime(true));;
        $withdrawalFeeSales->amount = $request->amount;
        $withdrawalFeeSales->description = $request->description;
        $withdrawalFeeSales->status = 'Pending';
        $withdrawalFeeSales->created_by = $this->karyawan;
        $withdrawalFeeSales->updated_by = $this->karyawan;

        $withdrawalFeeSales->save();

        $saldoFeeSales = SaldoFeeSales::where('sales_id', $withdrawalFeeSales->sales_id)->first();
        $saldoFeeSales->active_balance -= $withdrawalFeeSales->amount;
        $saldoFeeSales->save();

        return response()->json(['message' => 'Withdrawal Fee Sales requested successfully'], 201);
    }
}