<?php

namespace App\Http\Controllers\api;

use App\Models\Withdraw;
use App\Models\Invoice;
use App\Models\DailyQsd;
use App\Services\GenerateToken;
use App\Http\Controllers\Controller;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\SalesInDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Datatables;
use Carbon\Carbon;


class CompareInvoiceController extends Controller
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

    // public function needCompareIndex(Request $request)
    // {
    //     $isMatched = filter_var($request->is_matched, FILTER_VALIDATE_BOOLEAN);

    //     // ================= NORMAL (group by quotation) =================
    //     $dataNormal = DailyQsd::select(
    //             DB::raw('MAX(uuid) AS id'),
    //             DB::raw('GROUP_CONCAT(DISTINCT no_invoice ORDER BY no_invoice SEPARATOR ", ") AS no_invoice'),
    //             DB::raw('no_quotation'),
    //             DB::raw('MAX(konsultan) AS konsultan'),
    //             DB::raw('MAX(nama_perusahaan) AS nama_perusahaan'),
    //             DB::raw('SUM(biaya_akhir) AS biaya_akhir'),
    //             DB::raw('SUM(nilai_invoice) AS nilai_invoice'),
    //             DB::raw('MAX(tanggal_sampling_min) AS tanggal_sampling_min')
    //         )
    //         ->whereYear('tanggal_sampling_min', $request->year)
    //         ->groupBy('no_quotation')
    //         ->havingRaw(
    //             $isMatched
    //                 ? 'ABS(SUM(biaya_akhir) - SUM(nilai_invoice)) <= 50'
    //                 : 'ABS(SUM(biaya_akhir) - SUM(nilai_invoice)) > 50'
    //         );

    //     // ================= SPECIAL (group by invoice) =================
    //     $dataSpecial = DailyQsd::select(
    //             DB::raw('MAX(uuid) AS id'),
    //             DB::raw('no_invoice'),
    //             DB::raw('GROUP_CONCAT(DISTINCT no_quotation ORDER BY no_quotation SEPARATOR ", ") AS no_quotation'),
    //             DB::raw('MAX(konsultan) AS konsultan'),
    //             DB::raw('MAX(nama_perusahaan) AS nama_perusahaan'),
    //             DB::raw('SUM(biaya_akhir) AS biaya_akhir'),
    //             DB::raw('MIN(nilai_invoice) AS nilai_invoice'),
    //             DB::raw('MAX(tanggal_sampling_min) AS tanggal_sampling_min')
    //         )
    //         ->whereYear('tanggal_sampling_min', $request->year)
    //         ->groupBy('no_invoice')
    //         ->havingRaw(
    //             $isMatched
    //                 ? 'ABS(SUM(biaya_akhir) - MIN(nilai_invoice)) <= 50'
    //                 : 'ABS(SUM(biaya_akhir) - MIN(nilai_invoice)) > 50'
    //         );

    //     // ================= UNION =================
    //     $union = $dataNormal->unionAll($dataSpecial);

    //     $finalQuery = DB::query()
    //         ->fromSub($union, 'u')
    //         ->select(
    //             DB::raw('MAX(id) AS id'),
    //             DB::raw('GROUP_CONCAT(DISTINCT no_invoice ORDER BY no_invoice SEPARATOR ", ") AS no_invoice'),
    //             DB::raw('GROUP_CONCAT(DISTINCT no_quotation ORDER BY no_quotation SEPARATOR ", ") AS no_quotation'),
    //             DB::raw('MAX(konsultan) AS konsultan'),
    //             DB::raw('MAX(nama_perusahaan) AS nama_perusahaan'),
    //             DB::raw('SUM(biaya_akhir) AS biaya_akhir'),
    //             DB::raw('SUM(nilai_invoice) AS nilai_invoice'),
    //             DB::raw('MAX(tanggal_sampling_min) AS tanggal_sampling_min')
    //         )
    //         ->groupBy(
    //             DB::raw('COALESCE(no_invoice, "")'),
    //             DB::raw('COALESCE(no_quotation, "")')
    //         );

    //     // ================= FINAL DEDUP (UNIK PER BARIS) =================
    //     $data = DB::query()
    //         ->fromSub($finalQuery, 'qsd')
    //         ->select(
    //             DB::raw('MAX(id) AS id'),
    //             DB::raw('no_invoice'),
    //             DB::raw('no_quotation'),
    //             DB::raw('MAX(konsultan) AS konsultan'),
    //             DB::raw('MAX(nama_perusahaan) AS nama_perusahaan'),
    //             DB::raw('SUM(biaya_akhir) AS biaya_akhir'),
    //             DB::raw('SUM(nilai_invoice) AS nilai_invoice'),
    //             DB::raw('MAX(tanggal_sampling_min) AS tanggal_sampling_min')
    //         )
    //         ->groupBy('no_invoice', 'no_quotation') // 🔥 KUNCI UNIK
    //         ->orderByDesc('tanggal_sampling_min');

    //     return Datatables::of($data)
    //         ->filterColumn('nama_perusahaan', function ($query, $keyword) {
    //             $query->where(function($q) use ($keyword) {
    //                 $q->where('nama_perusahaan', 'like', "%{$keyword}%");
    //             });
    //         })
    //         ->filterColumn('konsultan', function ($query, $keyword) {
    //             $query->where(function($q) use ($keyword) {
    //                 $q->Where('konsultan', 'like', "%{$keyword}%");
    //             });
    //         })
    //         ->filterColumn('no_invoice', function ($query, $keyword) {
    //             $query->where(function($q) use ($keyword) {
    //                 $q->where('no_invoice', 'like', "%{$keyword}%");
    //             });
    //         })
    //         ->filterColumn('no_quotation', function ($query, $keyword) {
    //             $query->where(function($q) use ($keyword) {
    //                 $q->where('no_quotation', 'like', "%{$keyword}%");
    //             });
    //         })
    //         ->make(true);
    // }

    public function needCompareIndex(Request $request)
    {
        $isMatched = filter_var($request->is_matched, FILTER_VALIDATE_BOOLEAN);

        $excludeInv = Invoice::where('is_active', 1)
            ->whereIn('no_invoice', function ($q) {
                $q->select('no_invoice')
                    ->from('invoice')
                    ->where('is_active', 1)
                    ->groupBy('no_invoice')
                    ->havingRaw('COUNT(*) > 1')
                    ->havingRaw('COUNT(DISTINCT no_quotation) > 1');
            })
            ->groupBy('no_invoice')
            ->pluck('no_invoice')->toArray();
        
        $dataNormal = DailyQsd::whereNotNull('no_invoice')->whereYear('tanggal_sampling_min', $request->year)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->uuid,
                    'no_invoice' => $item->no_invoice,
                    'no_quotation' => $item->no_quotation,
                    'konsultan' => $item->konsultan,
                    'nama_perusahaan' => $item->nama_perusahaan,
                    'biaya_akhir' => $item->biaya_akhir,
                    'nilai_invoice' => $item->nilai_invoice,
                    'tanggal_sampling_min' => $item->tanggal_sampling_min,
                ];
            });
            $dataCollect = collect($dataNormal)->sortBy('no_invoice');
            
            $dataExclude = $dataCollect
                ->filter(function ($item) use ($excludeInv) {
                    $noInvoice = str_replace(' (Lunas)', '', $item['no_invoice']);
                    return in_array($noInvoice, $excludeInv);
                })
                ->groupBy(function ($item) {
                    return str_replace(' (Lunas)', '', $item['no_invoice']);
                })
                ->map(function ($items, $noInvoice) {
                    return [
                        'id' => $items->max('id'),
                        'no_invoice' => $noInvoice,
                        'no_quotation' => $items
                            ->pluck('no_quotation')
                            ->filter()
                            ->unique()
                            ->implode(', '),
                        'konsultan' => $items->pluck('konsultan')->filter()->first(),
                        'nama_perusahaan' => $items->pluck('nama_perusahaan')->filter()->first(),
                        'biaya_akhir' => $items->sum('biaya_akhir'),
                        'nilai_invoice' => $items->max('nilai_invoice'),
                        'tanggal_sampling_min' => $items->max('tanggal_sampling_min'),
                    ];
                })
                ->values();


            $dataInclude = $dataCollect->filter(function ($item) use ($excludeInv) {
                return !in_array(str_replace(' (Lunas)', '', ($item['no_invoice'])), $excludeInv);
            })->values();
            
            $allData = $dataInclude->merge($dataExclude);

            $allData = $allData->filter(function ($item) use ($isMatched) {
                $difference = abs($item['biaya_akhir'] - $item['nilai_invoice']);
                return $isMatched ? ($difference <= 50) : ($difference > 50);
            });
            
            return response()->json([
                'message' => 'Success',
                'status' => true,
                'data' => $allData->sortByDesc('tanggal_sampling_min')->values(),
            ], 200);
    }

    public function getDocumentDetail(Request $request)
    {
        if($request->type == 'invoice'){
            $data = Invoice::where('no_invoice', $request->no_invoice)->where('is_active', true)->first();
        } else if($request->type == 'quotation'){
            if($request->mode == 'kontrak'){
                $data = QuotationKontrakH::where('no_document', $request->no_document)->where('is_active', true)->first();
            }else{
                $data = QuotationNonKontrak::where('no_document', $request->no_document)->where('is_active', true)->first();
            }
        } else {
            return response()->json(['message' => 'Invalid document type.'], 400);
        }

        if(!$data){
            return response()->json(['message' => 'Document not found.'], 404);
        }

        return response()->json(['message' => 'Success', 'data' => $data], 200);
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