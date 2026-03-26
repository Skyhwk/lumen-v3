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
    public function index(Request $request)
    {
        $withdrawalFeeSales = WithdrawalFeeSales::with('sales')
        ->whereIn('status', $request->status)
        ->latest();
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
        $withdrawalFeeSales = WithdrawalFeeSales::find($request->id);
        $filename = null;
        if ($request->hasFile('image')) {
        $dir_image = "withdrawal_fee_sales";

        if (!file_exists(public_path($dir_image))) {
            mkdir(public_path($dir_image), 0777, true);
        }

            $batchNumber = str_replace('/', '_', $withdrawalFeeSales->batch_number);
            $microtime = str_replace('.', '', microtime(true));

            $file = $request->file('image');
            $extension = $file->getClientOriginalExtension();

            $filename = 'transfer_' . $batchNumber . '_' . $microtime . '.' . $extension;

            $file->move(public_path($dir_image), $filename);
        }

        $timestamp = Carbon::now();

        $withdrawalFeeSales = WithdrawalFeeSales::where(['id' => $request->id, 'is_active' => true])->latest()->first();
        $withdrawalFeeSales->status = 'Approved';
        $withdrawalFeeSales->amount = $request->nominal;
        $withdrawalFeeSales->pph = $request->pph_percent;
        $withdrawalFeeSales->amount_transfer = $request->nominal_transfer;
        $withdrawalFeeSales->filename_pph = $filename;
        $withdrawalFeeSales->approved_by = $this->karyawan;
        $withdrawalFeeSales->approved_at = $timestamp;

        $mutasiFeeSales = new MutasiFeeSales();

        $mutasiFeeSales->sales_id = $withdrawalFeeSales->sales_id;
        $mutasiFeeSales->batch_number = str_replace('.', '/', microtime(true));
        $mutasiFeeSales->period = $timestamp->year . '-' . $timestamp->month;
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
        $mutasiFeeSales->period = $timestamp->year . '-' . $timestamp->month;
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


     public function Transfer(Request $request)
    {
        $withdrawalFeeSales = WithdrawalFeeSales::find($request->id);
        $filename = null;
        if ($request->hasFile('image')) {
        $dir_image = "withdrawal_fee_sales";

        if (!file_exists(public_path($dir_image))) {
            mkdir(public_path($dir_image), 0777, true);
        }

            $batchNumber = str_replace('/', '_', $withdrawalFeeSales->batch_number);
            $microtime = str_replace('.', '', microtime(true));

            $file = $request->file('image');
            $extension = $file->getClientOriginalExtension();

            $filename = 'transfer_' . $batchNumber . '_' . $microtime . '.' . $extension;

            $file->move(public_path($dir_image), $filename);
        }


        $withdrawalFeeSales->status = 'Transfered';
        $withdrawalFeeSales->filename_transfer = $filename;
        $withdrawalFeeSales->transfered_by = $this->karyawan;
        $withdrawalFeeSales->transfered_at = Carbon::now();
 
        $withdrawalFeeSales->save();

        return response()->json(['message' => 'Withdrawal Fee Sales transferred successfully'], 200);
    }
}
