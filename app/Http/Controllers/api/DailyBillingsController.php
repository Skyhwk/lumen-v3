<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Datatables;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Models\Invoice;
use App\Models\FollowupBilling;

class DailyBillingsController extends Controller
{
    public function index(Request $request)
    {
        /*
            karena penulis menggunakan left join ke table withdraw dan follow billing dan membuat select query nilai tagihan dengan sum maka yang terjadi akumulasi seperti ini  nilai tagiah 1000.000
            ex:
            Skenario Bug: Bayangkan Invoice A senilai 1.000.000.
            Ada 2 kali pembayaran withdraw (@500.000). Total bayar = 1.000.000 (Lunas).
            Ada 3 history follow up (Telpon, WA, Email).
            Apa yang terjadi di database saat join? SQL akan mengalikan barisnya: 2 (withdraw) x 3 (followup) = 6 baris data.
            Efek pada Rumus:
            SUM(invoice.nilai_tagihan): 1.000.000 x 6 baris = 6.000.000.
            SUM(withdraw.nilai_pembayaran): Setiap withdraw (500rb) akan muncul 3 kali (sesuai jumlah followup). (500rb x 3) + (500rb x 3) = 3.000.000. 
         */
        // $invoices = Invoice::with('followup_billings')
        //     ->select(
        //         'invoice.no_invoice',
        //         DB::raw('COALESCE(MAX(order_header.nama_perusahaan), MAX(order_header.konsultan)) AS nama_customer'),
        //         DB::raw('MAX(order_header.konsultan) AS consultant'),
        //         DB::raw('floor(SUM(invoice.nilai_tagihan)) AS nilai_tagihan'),
        //         DB::raw('MAX(invoice.tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
        //         DB::raw('(MAX(invoice.nilai_pelunasan) + COALESCE(SUM(withdraw.nilai_pembayaran), 0)) AS nilai_pelunasan'),
        //         DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
        //         DB::raw('MAX(invoice.keterangan) AS keterangan'),
        //         // DB::raw('MAX(followup_status) AS followup_status'),
        //         // DB::raw('MAX(followup_billings.keterangan) AS keterangan'),
        //     )
        //     ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
        //     ->leftJoin('withdraw', 'invoice.no_invoice', '=', 'withdraw.no_invoice')
        //     ->leftJoin('followup_billings', 'invoice.no_invoice', '=', 'followup_billings.no_invoice')
        //     ->groupBy('invoice.no_invoice')
        //     // ->where('nilai_pelunasan', '>', 0)
        //     ->havingRaw('floor(SUM(nilai_tagihan)) > (COALESCE(MAX(invoice.nilai_pelunasan), 0) + COALESCE(SUM(withdraw.nilai_pembayaran), 0))')
        //     ->where('invoice.is_active', true)
        //     ->where('is_whitelist', false)
        //     ->where('invoice.tgl_jatuh_tempo', $request->tgl_jatuh_tempo);
        $invoices = Invoice::with('followup_billings') // Data followup diambil lewat sini saja
        ->select(
            'invoice.no_invoice',
            DB::raw('COALESCE(MAX(order_header.nama_perusahaan), MAX(order_header.konsultan)) AS nama_customer'),
            DB::raw('MAX(order_header.konsultan) AS consultant'),
            DB::raw('floor(MAX(invoice.nilai_tagihan)) AS nilai_tagihan'), 
            DB::raw('MAX(invoice.tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
            DB::raw('(MAX(invoice.nilai_pelunasan) + COALESCE(SUM(withdraw.nilai_pembayaran), 0)) AS nilai_pelunasan'),
            DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
            DB::raw('MAX(invoice.keterangan) AS keterangan')
        )
        ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
        ->leftJoin('withdraw', 'invoice.no_invoice', '=', 'withdraw.no_invoice')
        ->groupBy('invoice.no_invoice')
        ->havingRaw('floor(MAX(invoice.nilai_tagihan)) > (COALESCE(MAX(invoice.nilai_pelunasan), 0) + COALESCE(SUM(withdraw.nilai_pembayaran), 0))')
        ->where('invoice.is_active', true)
        ->where('is_whitelist', false)
        ->where('invoice.tgl_jatuh_tempo', $request->tgl_jatuh_tempo);
        return Datatables::of($invoices)->make(true);
    }

    public function updateBilling(Request $request)
    {
        DB::beginTransaction();
        try {
            //code...
            $followUpBilling = new FollowupBilling();
            $followUpBilling->no_invoice = $request->no_invoice;
            if ($request->followup_status) $followUpBilling->followup_status = $request->followup_status;
            if ($request->keterangan) $followUpBilling->keterangan = $request->keterangan;
            if ($request->tgl_jatuh_tempo) $followUpBilling->tgl_jatuh_tempo = $request->tgl_jatuh_tempo;
            if ($request->forecasted_billing) $followUpBilling->forecasted_billing = $request->forecasted_billing;
            $followUpBilling->created_by = $this->karyawan;
            $followUpBilling->created_at = Carbon::now();
            $followUpBilling->updated_by = $this->karyawan;
            $followUpBilling->updated_at = Carbon::now();
    
            $followUpBilling->save();
    
            if ($request->forecasted_billing) {
                $invoice = Invoice::where('no_invoice', $request->no_invoice)->latest()->first();
                $invoice->tgl_jatuh_tempo = $request->forecasted_billing;
                $invoice->save();
            }
            DB::commit();
            return response()->json([
                'status' => 200,
                'message' => 'Saved Successfully',
            ], 200);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollback();
            return response()->json(['message'=>$th->getMessage(),'line'=>$th->getLine(),'file'=>$th->getFile()],500);
        }
    }
}
