<?php

namespace App\Http\Controllers\api;

use App\Models\Withdraw;
use App\Models\Invoice;
use App\Services\GenerateToken;
use App\Http\Controllers\Controller;
use App\Models\SalesInDetail;
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
            // Subquery untuk total pembayaran withdraw
            $withdrawSub = DB::table('withdraw')
                ->select('no_invoice', DB::raw('SUM(nilai_pembayaran) as total_pembayaran'))
                ->groupBy('no_invoice');

            // Subquery untuk aggregate invoice data terlebih dahulu
            $invoiceAgg = DB::table('invoice')
                ->select(
                    'no_invoice',
                    DB::raw('SUM(total_tagihan) AS total_tagihan'),
                    DB::raw('FLOOR(SUM(nilai_tagihan)) AS nilai_tagihan'),
                    DB::raw('MAX(nilai_pelunasan) AS nilai_pelunasan'),
                    DB::raw('MAX(nama_pj) AS nama_pj'),
                    DB::raw('MAX(no_po) AS no_po'),
                    DB::raw('MAX(no_spk) AS no_spk'),
                    DB::raw('MAX(created_at) AS created_at'),
                    DB::raw('MAX(created_by) AS created_by'),
                    DB::raw('MAX(tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
                    DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
                    DB::raw('GROUP_CONCAT(DISTINCT no_order) AS no_orders'), // DISTINCT penting!
                    DB::raw('MAX(no_order) AS primary_no_order') // untuk join ke order_header
                )
                ->where('nilai_pelunasan', '>', 0)
                ->where('is_active', true)
                ->where('is_whitelist', false)
                ->groupBy('no_invoice');

            $invoices = DB::table(DB::raw("({$invoiceAgg->toSql()}) as inv"))
                ->mergeBindings($invoiceAgg)
                ->select(
                    'inv.no_invoice',
                    'inv.total_tagihan',
                    'inv.nilai_tagihan',
                    DB::raw('(inv.nilai_pelunasan + COALESCE(w.total_pembayaran, 0)) AS nilai_pelunasan'),
                    DB::raw('COALESCE(w.total_pembayaran, 0) AS total_pembayaran'),
                    DB::raw('CASE WHEN w.total_pembayaran IS NOT NULL THEN 1 ELSE 0 END AS has_withdraw'),
                    'inv.nama_pj',
                    'inv.no_po',
                    'inv.no_spk',
                    DB::raw('COALESCE(oh.nama_perusahaan, oh.konsultan) AS nama_customer'),
                    'oh.konsultan',
                    'inv.created_at',
                    'inv.created_by',
                    'inv.tgl_jatuh_tempo',
                    'inv.tgl_pelunasan',
                    'inv.no_orders'
                )
                ->leftJoin('order_header as oh', 'inv.primary_no_order', '=', 'oh.no_order')
                ->leftJoinSub($withdrawSub, 'w', function($join) {
                    $join->on('inv.no_invoice', '=', 'w.no_invoice');
                })
                ->whereRaw('inv.nilai_tagihan > (inv.nilai_pelunasan + COALESCE(w.total_pembayaran, 0))')
                ->distinct();

            
            return datatables()->of($invoices)
                ->addColumn('withdraw', function ($row) {
                    return Withdraw::where('no_invoice', $row->no_invoice)
                    ->orderBy('created_at')
                    ->get()
                    ->toArray();
                })
                ->filterColumn('no_invoice', function($query, $keyword) {
                    $query->where('inv.no_invoice', 'like', "%{$keyword}%");
                })
                ->filterColumn('nama_customer', function($query, $keyword) {
                    $query->where(function($q) use ($keyword) {
                        $q->where('oh.nama_perusahaan', 'like', "%{$keyword}%")
                        ->orWhere('oh.konsultan', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('nama_pj', function($query, $keyword) {
                    $query->where('inv.nama_pj', 'like', "%{$keyword}%");
                })
                ->filterColumn('no_po', function($query, $keyword) {
                    $query->where('inv.no_po', 'like', "%{$keyword}%");
                })
                ->filterColumn('konsultan', function($query, $keyword) {
                    $query->where('oh.konsultan', 'like', "%{$keyword}%");
                })
                ->make(true);
            
        } catch (\Throwable $th) {
            Log::error('Outstanding Index Error: ' . $th->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan'], 500);
        }
    }

    public function settlementIndex(Request $request)
    {
        try {
            $withdrawSub = DB::table('withdraw')
            ->select('no_invoice', DB::raw('SUM(nilai_pembayaran) as total_pembayaran'))
            ->groupBy('no_invoice');

        // Subquery untuk aggregate invoice data terlebih dahulu
        $invoiceAgg = DB::table('invoice')
            ->select(
                DB::raw('MAX(id) AS id'),
                'no_invoice',
                DB::raw('SUM(total_tagihan) AS total_tagihan'),
                DB::raw('FLOOR(SUM(nilai_tagihan)) AS nilai_tagihan'),
                DB::raw('MAX(nilai_pelunasan) AS nilai_pelunasan'),
                DB::raw('MAX(nama_pj) AS nama_pj'),
                DB::raw('MAX(no_po) AS no_po'),
                DB::raw('MAX(no_spk) AS no_spk'),
                DB::raw('MAX(created_at) AS created_at'),
                DB::raw('MAX(created_by) AS created_by'),
                DB::raw('MAX(tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
                DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
                DB::raw('GROUP_CONCAT(DISTINCT no_order) AS no_orders'), // DISTINCT penting!
                DB::raw('MAX(no_order) AS primary_no_order') // untuk join ke order_header
            )
            ->where('nilai_pelunasan', '>', 0)
            ->where('is_active', true)
            ->where('is_whitelist', false)
            ->groupBy('no_invoice');

        $invoices = DB::table(DB::raw("({$invoiceAgg->toSql()}) as inv"))
            ->mergeBindings($invoiceAgg)
            ->select(
                'inv.no_invoice',
                'inv.total_tagihan',
                'inv.nilai_tagihan',
                DB::raw('(inv.nilai_pelunasan + COALESCE(w.total_pembayaran, 0)) AS nilai_pelunasan'),
                'w.total_pembayaran',
                'inv.nama_pj',
                'inv.no_po',
                'inv.no_spk',
                DB::raw('COALESCE(oh.nama_perusahaan, oh.konsultan) AS nama_customer'),
                'oh.konsultan',
                'inv.created_at',
                'inv.created_by',
                'inv.tgl_jatuh_tempo',
                'inv.tgl_pelunasan',
                'inv.no_orders',
                'inv.id'
            )
            ->leftJoin('order_header as oh', 'inv.primary_no_order', '=', 'oh.no_order')
            ->joinSub($withdrawSub, 'w', function($join) {
                $join->on('inv.no_invoice', '=', 'w.no_invoice');
            })
            ->whereRaw('inv.nilai_tagihan <= (inv.nilai_pelunasan + COALESCE(w.total_pembayaran, 0))')
            ->distinct();

        return datatables()->of($invoices)
            ->filterColumn('no_invoice', function($query, $keyword) {
                $query->where('inv.no_invoice', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_customer', function($query, $keyword) {
                $query->where(function($q) use ($keyword) {
                    $q->where('oh.nama_perusahaan', 'like', "%{$keyword}%")
                      ->orWhere('oh.konsultan', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('nama_pj', function($query, $keyword) {
                $query->where('inv.nama_pj', 'like', "%{$keyword}%");
            })
            ->filterColumn('no_po', function($query, $keyword) {
                $query->where('inv.no_po', 'like', "%{$keyword}%");
            })
            ->filterColumn('konsultan', function($query, $keyword) {
                $query->where('oh.konsultan', 'like', "%{$keyword}%");
            })
            ->addColumn('withdraw', function($invoice) {
                return Withdraw::where('no_invoice', $invoice->no_invoice)->orderByDesc('id')->get();
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

    public function deleteWithdraw(Request $request) {
        $withdraw = Withdraw::find($request->id);
        if (!$withdraw) return response()->json(['message' => "Withdraw Not Found."], 404);
        
        if ($withdraw->id_sales_in_detail) {
            $salesInDetail = SalesInDetail::find($withdraw->id_sales_in_detail);
            if (!$salesInDetail) return response()->json(['message' => "Sales In Detail Not Found."], 404);

            $salesInDetail->nilai_pengurangan = null;
            $salesInDetail->save();
        }

        $withdraw->delete();

        return response()->json(['message' => "Withdraw Has Been Deleted."]);
    }
}