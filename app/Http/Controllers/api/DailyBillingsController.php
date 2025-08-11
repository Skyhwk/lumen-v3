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
        // $data = Invoice::select(
        //     'invoice.no_invoice',
        //     DB::raw('MAX(invoice.created_by) AS created_by'),
        //     DB::raw('MAX(faktur_pajak) AS faktur_pajak'),
        //     DB::raw('SUM(total_tagihan) AS total_tagihan'),
        //     DB::raw('MAX(jabatan_pj) AS jabatan_pj'),
        //     DB::raw('MAX(rekening) AS rekening'),
        //     DB::raw('MAX(keterangan) AS keterangan'),
        //     DB::raw('MAX(nama_pj) AS nama_pj'),
        //     DB::raw('MAX(jabatan_pj) AS jabatan_pj'),
        //     DB::raw('MAX(tgl_invoice) AS tgl_invoice'),
        //     DB::raw('MAX(no_faktur) AS no_faktur'),
        //     DB::raw('MAX(alamat_penagihan) AS alamat_penagihan'),
        //     DB::raw('MAX(nama_pic) AS nama_pic'),
        //     DB::raw('MAX(no_pic) AS no_pic'),
        //     DB::raw('MAX(email_pic) AS email_pic'),
        //     DB::raw('MAX(is_custom) AS is_custom'),
        //     DB::raw('MAX(jabatan_pic) AS jabatan_pic'),
        //     DB::raw('MAX(no_po) AS no_po'),
        //     DB::raw('MAX(no_spk) AS no_spk'),
        //     DB::raw('MAX(tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
        //     DB::raw('MAX(filename) AS filename'),
        //     DB::raw('MAX(order_header.konsultan) AS consultant'),
        //     DB::raw('MAX(invoice.created_at) AS created_at'),
        //     DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
        //     DB::raw('MAX(nilai_pelunasan) AS nilai_pelunasan'),
        //     DB::raw('MAX(is_generate) AS is_generate'),
        //     DB::raw('MAX(generated_by) AS generated_by'),
        //     DB::raw('MAX(generated_at) AS generated_at'),
        //     DB::raw('MAX(expired) AS expired'),
        //     DB::raw('MAX(invoice.pelanggan_id) AS pelanggan_id'),
        //     DB::raw('MAX(invoice.detail_pendukung) AS detail_pendukung'),
        //     DB::raw('COALESCE(MAX(order_header.nama_perusahaan), MAX(order_header.konsultan)) AS nama_customer'),
        //     // DB::raw('COALESCE(MAX(order_header.konsultan), MAX(order_header.nama_perusahaan)) AS nama_customer'),
        //     DB::raw('SUM(invoice.nilai_tagihan) AS nilai_tagihan'),
        //     DB::raw('MAX(order_header.is_revisi) AS is_revisi'),
        //     DB::raw('GROUP_CONCAT(invoice.no_order) AS no_orders')
        // )
        //     ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
        //     ->groupBy('invoice.no_invoice')
        //     ->where('is_emailed', true)
        //     ->where('invoice.is_active', true)
        //     ->where('order_header.is_active', true)
        //     ->where('tgl_jatuh_tempo', $request->tgl_jatuh_tempo)
        //     ->orderBy('invoice.no_invoice', 'DESC')
        //     ->get();

        // return Datatables::of($data)->make(true);
        $invoices = Invoice::with('followup_billings')
            ->select(
                'invoice.no_invoice',
                DB::raw('COALESCE(MAX(order_header.nama_perusahaan), MAX(order_header.konsultan)) AS nama_customer'),
                DB::raw('MAX(order_header.konsultan) AS consultant'),
                DB::raw('floor(SUM(invoice.nilai_tagihan)) AS nilai_tagihan'),
                DB::raw('MAX(invoice.tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
                DB::raw('(MAX(invoice.nilai_pelunasan) + COALESCE(SUM(withdraw.nilai_pembayaran), 0)) AS nilai_pelunasan'),
                DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
                DB::raw('MAX(invoice.keterangan) AS keterangan'),
                // DB::raw('MAX(followup_status) AS followup_status'),
                // DB::raw('MAX(followup_billings.keterangan) AS keterangan'),
            )
            ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
            ->leftJoin('withdraw', 'invoice.no_invoice', '=', 'withdraw.no_invoice')
            ->leftJoin('followup_billings', 'invoice.no_invoice', '=', 'followup_billings.no_invoice')
            ->groupBy('invoice.no_invoice')
            // ->where('nilai_pelunasan', '>', 0)
            ->havingRaw('floor(SUM(nilai_tagihan)) > (COALESCE(MAX(invoice.nilai_pelunasan), 0) + COALESCE(SUM(withdraw.nilai_pembayaran), 0))')
            ->where('invoice.is_active', true)
            ->where('is_whitelist', false)
            ->where('invoice.tgl_jatuh_tempo', $request->tgl_jatuh_tempo)
            ->get();

        return Datatables::of($invoices)->make(true);
    }

    public function updateBilling(Request $request)
    {
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

        return response()->json([
            'status' => 200,
            'message' => 'Saved Successfully',
        ], 200);
    }
}
