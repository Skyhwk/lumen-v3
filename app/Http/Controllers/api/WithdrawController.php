<?php

namespace App\Http\Controllers\api;

use App\Models\Withdraw;
use App\Models\Invoice;
use App\Services\GenerateToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Datatables;
use Carbon\Carbon;


class WithdrawController extends Controller
{

    public function outstandingIndex(Request $request)
    {
        try {
            $withdrawSub = DB::table('withdraw')
                ->select('no_invoice', DB::raw('SUM(nilai_pembayaran) as total_pembayaran'))
                ->groupBy('no_invoice');

            $invoices = Invoice::select(
                    'invoice.no_invoice',
                    DB::raw('SUM(invoice.total_tagihan) AS total_tagihan'),
                    DB::raw('floor(SUM(invoice.nilai_tagihan)) AS nilai_tagihan'),
                    DB::raw('(MAX(invoice.nilai_pelunasan) + COALESCE(MAX(w.total_pembayaran), 0)) AS nilai_pelunasan'),
                    DB::raw('MAX(nama_pj) AS nama_pj'),
                    DB::raw('MAX(invoice.no_po) AS no_po'),
                    DB::raw('MAX(no_spk) AS no_spk'),
                    DB::raw('COALESCE(MAX(order_header.nama_perusahaan), MAX(order_header.konsultan)) AS nama_customer'),
                    DB::raw('MAX(order_header.konsultan) AS konsultan'),
                    DB::raw('MAX(invoice.created_at) AS created_at'),
                    DB::raw('MAX(invoice.created_by) AS created_by'),
                    DB::raw('MAX(tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
                    DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
                    DB::raw('GROUP_CONCAT(invoice.no_order) AS no_orders')
                )
                ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                ->leftJoinSub($withdrawSub, 'w', function($join) {
                    $join->on('invoice.no_invoice', '=', 'w.no_invoice');
                })
                ->groupBy('invoice.no_invoice')
                ->where('invoice.nilai_pelunasan', '>', 0)
                ->where('invoice.is_active', true)
                ->where('is_whitelist', false)
                ->havingRaw('floor(SUM(nilai_tagihan)) > (MAX(invoice.nilai_pelunasan) + COALESCE(MAX(w.total_pembayaran), 0))');


                return datatables()->of($invoices)
                ->filterColumn('no_invoice', function($query, $keyword) {
                    $query->where('invoice.no_invoice', 'like', "%{$keyword}%");
                })
                ->filterColumn('nama_customer', function($query, $keyword) {
                    $query->where(function($q) use ($keyword) {
                        $q->where('order_header.nama_perusahaan', 'like', "%{$keyword}%")
                          ->orWhere('order_header.konsultan', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('nama_pj', function($query, $keyword) {
                    $query->where('nama_pj', 'like', "%{$keyword}%");
                })
                ->filterColumn('no_po', function($query, $keyword) {
                    $query->where('invoice.no_po', 'like', "%{$keyword}%");
                })
                ->filterColumn('konsultan', function($query, $keyword) {
                    $query->where('order_header.konsultan', 'like', "%{$keyword}%");
                })
                ->make(true);
            
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function settlementIndex(Request $request)
    {
        try {
            $withdrawSub = DB::table('withdraw')
                ->select('no_invoice', DB::raw('SUM(nilai_pembayaran) as total_pembayaran'))
                ->groupBy('no_invoice');

            $invoices = Invoice::select(
                    'invoice.no_invoice',
                    DB::raw('SUM(invoice.total_tagihan) AS total_tagihan'),
                    DB::raw('floor(SUM(invoice.nilai_tagihan)) AS nilai_tagihan'),
                    DB::raw('(MAX(invoice.nilai_pelunasan) + COALESCE(MAX(w.total_pembayaran), 0)) AS nilai_pelunasan'),
                    DB::raw('MAX(nama_pj) AS nama_pj'),
                    DB::raw('MAX(invoice.no_po) AS no_po'),
                    DB::raw('MAX(no_spk) AS no_spk'),
                    DB::raw('COALESCE(MAX(order_header.nama_perusahaan), MAX(order_header.konsultan)) AS nama_customer'),
                    DB::raw('MAX(order_header.konsultan) AS konsultan'),
                    DB::raw('MAX(invoice.created_at) AS created_at'),
                    DB::raw('MAX(invoice.created_by) AS created_by'),
                    DB::raw('MAX(tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
                    DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
                    DB::raw('GROUP_CONCAT(invoice.no_order) AS no_orders')
                )
                ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                ->leftJoinSub($withdrawSub, 'w', function($join) {
                    $join->on('invoice.no_invoice', '=', 'w.no_invoice');
                })
                ->groupBy('invoice.no_invoice')
                ->where('invoice.nilai_pelunasan', '>', 0)
                ->where('invoice.is_active', true)
                ->where('is_whitelist', false)
                ->havingRaw('floor(SUM(nilai_tagihan)) <= (MAX(invoice.nilai_pelunasan) + COALESCE(MAX(w.total_pembayaran), 0))');

            return datatables()->of($invoices)
                ->filterColumn('no_invoice', function($query, $keyword) {
                    $query->where('invoice.no_invoice', 'like', "%{$keyword}%");
                })
                ->filterColumn('nama_customer', function($query, $keyword) {
                    $query->where(function($q) use ($keyword) {
                        $q->where('order_header.nama_perusahaan', 'like', "%{$keyword}%")
                          ->orWhere('order_header.konsultan', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('nama_pj', function($query, $keyword) {
                    $query->where('nama_pj', 'like', "%{$keyword}%");
                })
                ->filterColumn('no_po', function($query, $keyword) {
                    $query->where('invoice.no_po', 'like', "%{$keyword}%");
                })
                ->filterColumn('konsultan', function($query, $keyword) {
                    $query->where('order_header.konsultan', 'like', "%{$keyword}%");
                })
                ->make(true);


        } catch (\Throwable $th) {
            dd($th);
        }
    }

    function updateOutstandingData(Request $request) {
        DB::beginTransaction();
        try {
            Withdraw::Insert([
                'no_invoice' => $request->no_invoice,
                'sisa_tagihan' => floatval(preg_replace('/[Rp., ]/', '', $request->sisa_tagihan)),
                'nilai_pembayaran' => floatval(preg_replace('/[Rp., ]/', '', $request->jumlah_pembayaran)),
                'keterangan_tambahan' => $request->reason_additional,
                'keterangan_pelunasan' => $request->reason,
                'created_by' => $this->karyawan,
                'created_at' => Carbon::now(),
            ]);
            
            DB::commit();

            return response()->json([
                'message' => "Invoice " . $request->no_invoice . " Has Been Updated."
            ]);
        } catch (\Exception $th) {
            DB::rollback();
            dd($th);
        }
    }

    function whiteListOutstandingData(Request $request) {
        DB::beginTransaction();
        try {
            $invoices = Invoice::where('no_invoice', $request->no_invoice)->first();
            $invoices->is_whitelist = true;
            $invoices->save();
            
            DB::commit();

            return response()->json([
                'message' => "Invoice " . $request->no_invoice . " Has Been Updated."
            ]);
        } catch (\Exception $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }

}