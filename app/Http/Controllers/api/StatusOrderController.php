<?php

namespace App\Http\Controllers\api;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use App\Models\QuotationKontrakD;
use App\Services\GetBawahan;
use App\Services\GroupedCfrByLhp;
use Illuminate\Http\Request;
use Datatables;
use Exception;

class StatusOrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $mode = $request->mode;
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with([
                    'sales',
                    'link_lhp',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'orderHeader.invoices.recordWithdraw'
                ])
                    ->where('id_cabang', $request->cabang)
                    ->whereHas('orderHeader')
                    // ->where('flag_status', '!=', 'ordered')
                    // ->where('is_active', true)
                    ->where('is_approved', true)
                    ->where('is_emailed', true)
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->orderBy('tanggal_penawaran', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakD::with([
                    'header',
                    'header.sales',
                    'header.link_lhp',
                    'header.sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'header.orderHeader.invoices.recordWithdraw'
                ])
                    ->select('request_quotation_kontrak_D.*')
                    ->whereHas('header', function ($q) use ($request) {
                        $q->where('id_cabang', $request->cabang)
                            ->where('is_approved', true)
                            ->where('is_emailed', true)
                            ->where('is_active', true)
                            ->whereYear('tanggal_penawaran', $request->year)
                            ->whereHas('orderHeader')
                            ->orderBy('tanggal_penawaran', 'desc');
                    })
                    ->orderBy('request_quotation_kontrak_D.id', 'desc');
            }

            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
            if($mode == 'non_kontrak') {
                if ($jabatan == 24 || $jabatan == 86) { // sales staff || Secretary Staff
                    $data->where('sales_id', $this->user_id);
                } else if ($jabatan == 21 || $jabatan == 15 || $jabatan == 154) { // sales supervisor || sales manager || senior sales manager
                    $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->toArray();
                    array_push($bawahan, $this->user_id);
                    $data->whereIn('sales_id', $bawahan);
                }
            }else if($mode == 'kontrak') {
                $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->toArray();
                if ($jabatan == 24 || $jabatan == 86) { 
                    $data->whereHas('header', function ($q) {
                        $q->where('sales_id', $this->user_id);
                    });

                } else if ($jabatan == 21 || $jabatan == 15 || $jabatan == 154) {
                    array_push($bawahan, $this->user_id);

                    $data->whereHas('header', function ($q) use ($bawahan) {
                        $q->whereIn('sales_id', $bawahan);
                    });
                }
            }

            return DataTables::of($data)
                ->addColumn('count_jadwal', function ($row) {
                    return $row->sampling ? $row->sampling->sum(function ($sampling) {
                        return $sampling->jadwal->count();
                    }) : 0;
                })
                ->filterColumn('no_document', function ($query, $keyword) use ($mode) {
                    if ($mode == 'non_kontrak') {
                        $query->where('no_document', 'like', "%{$keyword}%");
                    } elseif ($mode == 'kontrak') {
                        $query->whereHas('header', function ($q) use ($keyword) {
                            $q->where('no_document', 'like', "%{$keyword}%");
                        });
                    }
                })
                ->filterColumn('invoice', function ($query, $keyword) use ($mode) {
                    if ($mode == 'non_kontrak') {
                        $query->whereHas('orderHeader.invoices', function ($q) use ($keyword) {
                            $q->where('no_invoice', 'like', "%{$keyword}%");
                        });
                    }

                    if ($mode == 'kontrak') {
                        $query->whereHas('header.orderHeader.invoices', function ($q) use ($keyword) {
                            $q->where('no_invoice', 'like', "%{$keyword}%");
                        });
                    }
                })
                ->addColumn('total_invoice', function ($row) use ($mode) {
                    if ($mode == 'non_kontrak') {
                        if (!$row->orderHeader) return 0;
                        return $row->orderHeader->invoices->count();
                    } else if ($mode == 'kontrak') {
                        if (!$row->header->orderHeader) return 0;
                        $filtered = $row->header->orderHeader->invoices
                            ->where('periode', $row->periode_kontrak);
                        if ($filtered->isEmpty()) {
                            $filtered = $row->header->orderHeader->invoices
                                ->where('periode', 'all');
                        };
                        return $filtered->count();
                    };
                })
                ->editColumn('link_lhp', function ($row) use ($mode) {
                    if ($mode == 'kontrak') {
                        if (!$row->header || !$row->header->link_lhp) return null;

                        // Filter link_lhp berdasarkan periode_kontrak
                        $filtered = $row->header->link_lhp->where('periode', $row->periode_kontrak)->first();

                        return json_decode($filtered, true);
                    } else {
                        return json_decode($row->link_lhp, true);
                    }
                })
                // ->addColumn('nilai_invoice', function ($row) use ($mode) {
                //     if ($mode == 'non_kontrak') {
                //         if (!$row->orderHeader || $row->orderHeader->invoices->isEmpty()) return 0;
                        
                //         $filtered = $row->orderHeader->invoices
                //             ->where('periode', $row->periode_kontrak)->where('no_quotation', $row->no_document);
                //         if ($filtered->isEmpty()) {
                //             $filtered = $row->orderHeader->invoices
                //                 ->where('periode', 'all')->where('no_quotation', $row->no_document);
                //         };
                //         $nilaiInvoice = 0;
                //         foreach ($filtered as $invoice) {
                //             $nilaiInvoice += $invoice->nilai_pelunasan;
                //             if($invoice->record_withdraw) {
                //                 foreach ($invoice->record_withdraw as $withdraw) {
                //                     $nilaiInvoice += $withdraw->nilai_pembayaran;
                //                 }
                //             }
                //         }

                //         return $nilaiInvoice;
                //     } else if ($mode == 'kontrak') {
                //         if (!$row->header->orderHeader || $row->header->orderHeader->invoices->isEmpty()) return '-';
                //         $filtered = $row->header->orderHeader->invoices
                //             ->where('periode', $row->periode_kontrak)->where('no_quotation', $row->header->no_document);
                //         if ($filtered->isEmpty()) {
                //             $filtered = $row->header->orderHeader->invoices
                //                 ->where('periode', 'all')->where('no_quotation', $row->header->no_document);
                //         };
                //         $nilaiInvoice = 0;
                //         foreach ($filtered as $invoice) {
                //             $nilaiInvoice += $invoice->nilai_pelunasan;
                //             if($invoice->record_withdraw) {
                //                 foreach ($invoice->record_withdraw as $withdraw) {
                //                     $nilaiInvoice += $withdraw->nilai_pembayaran;
                //                 }
                //             }
                //         }

                //         return $nilaiInvoice;
                //     };
                // })
                ->addColumn('nilai_pelunasan', function ($row) use ($mode) {
                    if ($mode == 'non_kontrak') {
                        if (!$row->orderHeader || $row->orderHeader->invoices->isEmpty()) return '-';
                        $totalPelunasan = $row->orderHeader->invoices->sum('nilai_pelunasan');

                        return $totalPelunasan;
                    } else if ($mode == 'kontrak') {
                        if (!$row->header->orderHeader || $row->header->orderHeader->invoices->isEmpty()) return '-';
                        $filtered = $row->header->orderHeader->invoices
                            ->where('periode', $row->periode_kontrak);
                        if ($filtered->isEmpty()) {
                            $filtered = $row->header->orderHeader->invoices
                                ->where('periode', 'all');
                        };
                        $totalPelunasan = $filtered->sum('nilai_pelunasan');

                        return $totalPelunasan;
                    };
                })
                ->addColumn('invoice', function ($row) use ($mode) {
                    if ($mode == 'non_kontrak') {
                        if (!$row->orderHeader || $row->orderHeader->invoices->isEmpty()) return 0;
                        
                        $filtered = $row->orderHeader->invoices
                            ->where('periode', $row->periode_kontrak)->where('no_quotation', $row->no_document);
                        if ($filtered->isEmpty()) {
                            $filtered = $row->orderHeader->invoices
                                ->where('periode', 'all')->where('no_quotation', $row->no_document);
                        };


                        return $filtered->values()->toArray();
                    } else if ($mode == 'kontrak') {
                        if (!$row->header->orderHeader || $row->header->orderHeader->invoices->isEmpty()) return '-';
                        $filtered = $row->header->orderHeader->invoices
                            ->where('periode', $row->periode_kontrak)->where('no_quotation', $row->header->no_document);
                        if ($filtered->isEmpty()) {
                            $filtered = $row->header->orderHeader->invoices
                                ->where('periode', 'all')->where('no_quotation', $row->header->no_document);
                        };

                        return $filtered->values()->toArray();
                    };
                })
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function initializeSteps($orderDate)
    {
        return [
            'order' => ['label' => 'Order', 'date' => $orderDate],
            'sampling' => ['label' => 'Sampling', 'date' => null],
            'analisa' => ['label' => 'Analisa', 'date' => null],
            'drafting' => ['label' => 'Drafting', 'date' => null],
            'lhp_release' => ['label' => 'LHP Release', 'date' => null],
        ];
    }

    public function detail(Request $request)
    {
        $orderHeader = OrderHeader::find($request->id_order_header);

        $groupedData = (new GroupedCfrByLhp($orderHeader, $request->periode))->get();

        return response()->json(['groupedCFRs' => $groupedData], 200);
    }
}
