<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use DataTables;

use App\Models\{
    LimitWithdraw,
    MasterFeeSales,
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
        148,
        21, // Sales Supervisor
    ];

    public function getSalesList(Request $request)
    {
        $currentUser = $request->attributes->get('user')->karyawan;

        $sales = MasterKaryawan::where('is_active', true)
            ->whereIn('id_jabatan', $this->idJabatanSales)
            ->when(
                !in_array($currentUser->id_jabatan, $this->idJabatanSales),
                function ($q) use ($currentUser) {
                    // Jika user BUKAN sales, maka tampilkan semua sales; jika sales, filter ke sales sendiri
                    return $q;
                },
                function ($q) use ($currentUser) {
                    // Jika user sales, hanya tampilkan sales-nya sendiri
                    return $q->where('id', $currentUser->id);
                }
            )
            ->orderBy('nama_lengkap', 'asc')
            ->get();
 

        return response()->json([
            'sales' => $sales,
            'message' => 'Sales list retrieved successfully',
        ], 200);
    }


    public function getSaldoFeeSales(Request $request)
    {
        $period = Carbon::now();
        $saldoFeeSales = SaldoFeeSales::where(['sales_id' => $request->salesId, 'is_active' => true])->latest()->first();
        if (!$saldoFeeSales) return response()->json(['message' => 'Saldo Fee Sales not found'], 404);

        $limit = 0;
        $limitWithdraw = LimitWithdraw::where(['user_id' => $request->salesId, 'is_active' => true])->latest()->first();
        if ($limitWithdraw) {
            $usedLimit = WithdrawalFeeSales::where(['sales_id' => $request->salesId, 'is_active' => true])
                ->whereIn('status', ['Pending', 'Approved', 'Transfered'])
                ->whereYear('created_at', $period->year)
                ->whereMonth('created_at', $period->month)
                ->sum('amount');
            $limit = $limitWithdraw->limit - $usedLimit;
        }

        $pendingWithdrawal = WithdrawalFeeSales::where(['sales_id' => $request->salesId, 'status' => 'Pending', 'is_active' => true]);

        $mutasiStats = MutasiFeeSales::where(['sales_id' => $request->salesId, 'is_active' => true])
            // ->where('period', $request->year . '-' . $request->month)
            ->selectRaw("SUM(CASE WHEN mutation_type = 'Debit' THEN amount ELSE 0 END) as total_debit")
            ->selectRaw("SUM(CASE WHEN mutation_type = 'Kredit' THEN amount ELSE 0 END) as total_credit")
            ->first();

        $saldoFeeSales->total_debit = $mutasiStats->total_debit;
        $saldoFeeSales->total_credit = $mutasiStats->total_credit;

        $masterQuery = [
            'sales_id' => $request->salesId,
            'is_active' => true,
        ];

        $estimatedFee = (float) MasterFeeSales::where($masterQuery)->sum('estimated_fee');
        $claimedFeeMaster = (float) MasterFeeSales::where($masterQuery)->sum('claimed_fee');

        $withdrawnFee = (float) WithdrawalFeeSales::where([
            'sales_id' => $request->salesId,
            'is_active' => true,
            'rejected_by' => null,
        ])->sum('amount');

        $rekapJoin = function ($isLunas) use ($request) {
            return DB::table('rekap_fee_sales as r')
                ->join('master_fee_sales as m', 'm.id', '=', 'r.fee_sales_id')
                ->where('m.sales_id', $request->salesId)
                ->where('m.is_active', true)
                ->where('r.is_lunas', $isLunas);
        };

        $paidRekapFee = (float) $rekapJoin(1)->sum(DB::raw('r.total_revenue * m.basis'));
        $unpaidRekapFee = (float) $rekapJoin(0)->sum(DB::raw('r.total_revenue * m.basis'));

        $feeMenungguPelunasan = round($unpaidRekapFee, 2);
        $feeSudahLunas = round($paidRekapFee, 2);
        $feeSudahDikredit = round((float) $mutasiStats->total_credit, 2);
        // Total yang bisa dipertanggungjawabkan: sudah masuk mutasi + yang belum lunas
        $totalFee = round($feeSudahDikredit + $feeMenungguPelunasan, 2);
        $feeRekapBruto = round($feeSudahLunas + $feeMenungguPelunasan, 2);
        $feeLunasBelumDikredit = round(max(0, $feeSudahLunas - $feeSudahDikredit), 2);
        $saldoAktif = (float) $saldoFeeSales->active_balance;
        $sudahDicairkan = round($withdrawnFee, 2);

        return response()->json([
            'saldoFeeSales' => $saldoFeeSales,
            'limitWithdraw' => $limit,
            'pendingWithdrawal' => [
                'amount' => $pendingWithdrawal->sum('amount'),
                'count' => $pendingWithdrawal->count(),
            ],
            'summary' => [
                'total_fee' => $totalFee,
                'fee_sudah_dikredit' => $feeSudahDikredit,
                'fee_sudah_lunas' => $feeSudahLunas,
                'fee_menunggu_pelunasan' => $feeMenungguPelunasan,
                'fee_rekap_bruto' => $feeRekapBruto,
                'fee_lunas_belum_dikredit' => $feeLunasBelumDikredit,
                'fee_data_sinkron' => $feeLunasBelumDikredit < 1,
                'saldo_aktif' => $saldoAktif,
                'sudah_dicairkan' => $sudahDicairkan,
                // backward compatibility
                'estimated_fee' => round($estimatedFee, 2),
                'claimed_fee' => $claimedFeeMaster,
                'fee_berjalan' => $feeMenungguPelunasan,
                'withdrawn_fee' => $sudahDicairkan,
                'unpaid_rekap_fee' => $feeMenungguPelunasan,
                'active_balance' => $saldoAktif,
            ],
            'message' => 'Saldo Fee Sales retrieved successfully',
        ], 200);
    }

    public function getMutasiSaldo(Request $request)
    {
        $mutasiSaldo = MutasiFeeSales::where(['sales_id' => $request->salesId, 'is_active' => true])
            // ->where('period', $request->year . '-' . $request->month)
            ->latest();

        return DataTables::of($mutasiSaldo)->make(true);
    }

    public function getWithdrawal(Request $request)
    {
        $withdrawalFeeSales = WithdrawalFeeSales::where(['sales_id' => $request->salesId, 'is_active' => true])
            // ->whereMonth('created_at', $request->month)
            // ->whereYear('created_at', $request->year)
            ->latest();

        return DataTables::of($withdrawalFeeSales)->make(true);
    }

    public function requestWithdrawal(Request $request)
    {
        $timestamp = Carbon::now();
        $saldoFeeSales = SaldoFeeSales::where(['sales_id' => $request->sales_id, 'is_active' => true])->latest()->first();
        if (!$saldoFeeSales) return response()->json(['message' => 'Saldo Fee Sales not found'], 404);
        if ($saldoFeeSales->active_balance < $request->amount) return response()->json(['message' => 'Saldo Fee Sales tidak cukup'], 400);
        $limitWithdraw = LimitWithdraw::where(['user_id' => $request->sales_id, 'is_active' => true])->latest()->first();
        if (!$limitWithdraw) return response()->json(['message' => 'Limit penarikan belum diatur'], 404);

        $usedLimit = WithdrawalFeeSales::where(['sales_id' => $request->sales_id, 'is_active' => true])
            ->whereIn('status', ['Pending', 'Approved', 'Transfered'])
            ->whereYear('created_at', $timestamp->year)
            ->whereMonth('created_at', $timestamp->month)
            ->sum('amount');
        $limit = $limitWithdraw->limit - $usedLimit;

        if ($request->amount > $limit) return response()->json(['message' => 'Permintaan anda melebihi batas penarikan bulan ini'], 400);

        $withdrawalFeeSales = new WithdrawalFeeSales();

        $withdrawalFeeSales->sales_id = $request->sales_id;
        $withdrawalFeeSales->batch_number = str_replace('.', '/', microtime(true));;
        $withdrawalFeeSales->amount = $request->amount;
        $withdrawalFeeSales->description = $request->description;
        $withdrawalFeeSales->status = 'Pending';
        $withdrawalFeeSales->created_by = $this->karyawan;
        $withdrawalFeeSales->created_at = $timestamp;

        $withdrawalFeeSales->save();

        $saldoFeeSales = SaldoFeeSales::where('sales_id', $withdrawalFeeSales->sales_id)->first();
        $saldoFeeSales->active_balance -= $withdrawalFeeSales->amount;
        $saldoFeeSales->updated_by = 'System';
        $saldoFeeSales->updated_at = $timestamp;
        $saldoFeeSales->save();

        return response()->json(['message' => 'Withdrawal Fee Sales requested successfully'], 201);
    }
}
