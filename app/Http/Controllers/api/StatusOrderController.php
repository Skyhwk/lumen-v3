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
use Illuminate\Support\Facades\DB;
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
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'orderHeader.invoices.recordWithdraw'
                ])
                ->select('request_quotation.*')
                    ->where('request_quotation.id_cabang', $request->cabang) 
                    ->whereHas('orderHeader') // whereHas tidak perlu dikualifikasi
                    ->where('request_quotation.is_approved', true) 
                    ->where('request_quotation.is_emailed', true) // <-- INI YANG MENYEBABKAN ERROR
                    ->whereYear('request_quotation.tanggal_penawaran', $request->year) 
                    ->whereMonth('request_quotation.tanggal_penawaran', '>=', 11)
                    ->orderBy('request_quotation.tanggal_penawaran', 'desc');
            } else if ($request->mode == 'kontrak') {
                /* $data = QuotationKontrakD::with([
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
                            ->whereYear('tanggal_penawaran', $request->year)
                            ->whereMonth('tanggal_penawaran', '>=', 11)
                            ->orderBy('tanggal_penawaran', 'desc');
                    })
                    ->orderBy('request_quotation_kontrak_D.id', 'desc'); */
                    //optimasi
                    $data = QuotationKontrakD::with([
                        'header',
                        'header.sales',
                        // 'header.link_lhp', // Jika JOIN digunakan, eager loading ini mungkin tidak diperlukan
                        'header.sampling' => function ($q) {
                            $q->orderBy('periode_kontrak', 'asc');
                        },
                        'header.orderHeader.invoices.recordWithdraw'
                    ]);
                    // Memastikan kolom D yang diambil, JIKA ADA JOIN EKSPLISIT
                    $data->select(
                        'request_quotation_kontrak_D.*',
                        // Tambahkan kolom yang digunakan untuk ORDER BY:
                        'header.tanggal_penawaran as header_tanggal_penawaran',
                        'header.id as header_id_fk' // Penting untuk memastikan relasi 'header' tetap berfungsi di Eloquent
                    );

                    // --- 🚀 INISIASI JOIN PERFORMA TINGGI DI SINI ---
                    
                    // 1. JOIN ke Header (Wajib, untuk filter Header dan untuk JOIN ke LHP)
                    $data->join('request_quotation_kontrak_H as header', 
                            'request_quotation_kontrak_D.id_request_quotation_kontrak_h', 
                            '=', 
                            'header.id')
                    ->distinct(); // Mencegah baris D terduplikasi jika ada relasi 1:N

                    // 2. Filter Header (Sama seperti whereHas sebelumnya, tapi diterapkan langsung)
                    $data->where('header.id_cabang', $request->cabang)
                        ->where('header.is_approved', true)
                        ->where('header.is_emailed', true)
                        ->where('header.is_active', true)
                        ->whereYear('header.tanggal_penawaran', $request->year)
                        // Asumsi: orderHeader/tanggal_penawaran hanya ada di Header (H)
                        ->whereMonth('header.tanggal_penawaran', '>=', 11);
                    $data->orderBy('header.tanggal_penawaran', 'desc')
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

            $filterStatusType = $request->input('filter_status_type'); // Ambil parameter dari frontend
           
            if ($filterStatusType === 'completed') {
                // 🎯 LOGIKA: HARUS MEMENUHI KRITERIA LHP SELESAI DAN INVOICE LUNAS

                if ($request->mode == 'kontrak'){
                      
                    $data->join('link_lhp as lhp', function($join) {
                        $join->on('header.no_document', '=', 'lhp.no_quotation') 
                            ->on('lhp.periode', '=', DB::raw('request_quotation_kontrak_D.periode_kontrak'))
                            ->where('lhp.is_completed', true);
                    });
                    $data->distinct(); 

                    
                    $data->join('order_header as oh', 'header.no_document', '=', 'oh.no_document')
                    ->distinct();
                    $data->join('invoice as i', function($join) {
                        $join->on('oh.no_order', '=', 'i.no_order');
                        $join->whereNotNull('i.nilai_pelunasan')
                            ->whereRaw('i.nilai_pelunasan >= i.nilai_tagihan');
                        $join->on('i.periode', '=', DB::raw('request_quotation_kontrak_D.periode_kontrak'));
                    })
                    ->distinct();
                }else{
                    $data->join('link_lhp as lhp', function($join) {
                        $join->on('request_quotation.no_document', '=', 'lhp.no_quotation')
                            ->where('lhp.is_completed', true);
                    });
                    $data->distinct();
                    $data->join('order_header as oh', 'request_quotation.no_document', '=', 'oh.no_document')
                    ->distinct();
                    $data->join('invoice as i', function($join) {
                        $join->on('oh.no_order', '=', 'i.no_order');
                        $join->whereNotNull('i.nilai_pelunasan')
                            ->whereRaw('i.nilai_pelunasan >= i.nilai_tagihan');
                        
                    })
                    ->distinct();
                }
                
            }else if($filterStatusType === 'incompleted'){
                
                if ($request->mode == 'kontrak'){
                      
                    $data->join('link_lhp as lhp', function($join) {
                        $join->on('header.no_document', '=', 'lhp.no_quotation') 
                            ->on('lhp.periode', '=', DB::raw('request_quotation_kontrak_D.periode_kontrak'));
                    });
                    $data->distinct();
                    // Filter untuk LHP: (LHP belum ada [NULL] ATAU LHP ada tapi is_completed = FALSE)
                    $data->where(function($q) {
                        $q->whereNull('lhp.no_quotation') // Data belum memiliki LHP
                        ->orWhere('lhp.is_completed', false); // Data memiliki LHP tapi belum completed
                    });
                    $data->join('order_header as oh', 'header.no_document', '=', 'oh.no_document')
                    ->distinct();
                    // 3. JOIN Invoice (Menggunakan LEFT JOIN untuk menyertakan Detail yang BELUM ADA Invoice)
                    $data->leftJoin('invoice as i', function($join) {
                        $join->on('oh.no_order', '=', 'i.no_order')
                            ->on('i.periode', '=', DB::raw('request_quotation_kontrak_D.periode_kontrak'));
                    })
                    ->distinct();
                    // Filter untuk Invoice: (Invoice belum ada [NULL] ATAU Invoice ada tapi BELUM LUNAS)
                    $data->where(function($q) {
                        $q->whereNull('i.no_invoice') // Data belum memiliki Invoice
                        // Jika ada Invoice, pastikan belum lunas (Pelunasan NULL ATAU Pelunasan < Tagihan)
                        ->orWhere(function($q2) {
                            $q2->whereNotNull('i.no_invoice') // Ada Invoice
                                ->where(function($q3) {
                                    $q3->whereNull('i.nilai_pelunasan') // Pelunasan NULL
                                        ->orWhereRaw('i.nilai_pelunasan < i.nilai_tagihan'); // Pelunasan Parsial
                                });
                        });
                    });
                }else{
                    $data->leftJoin('link_lhp as lhp', function($join) {
                        // Menggunakan nama tabel root Anda di klausa ON
                        $join->on('request_quotation.no_document', '=', 'lhp.no_quotation'); 
                    });
                    // Filter untuk LHP: (LHP belum ada [NULL] ATAU LHP ada tapi is_completed = FALSE)
                    $data->where(function($q) {
                        $q->whereNull('lhp.no_quotation') // Data belum memiliki LHP
                        ->orWhere('lhp.is_completed', false); // Data memiliki LHP tapi belum completed
                    });
                    // $data->whereDoesntHave('orderHeader.invoices', function ($q) {
                    //     // Kriteria LUNAS: Invoice yang sudah lunas penuh
                    //     $q->whereRaw('nilai_pelunasan >= nilai_tagihan');
                    //     // Catatan: Jika ada 5 Invoice, dan 1 LUNAS, maka whereDoesntHave ini akan gagal.
                    //     // Ini mengasumsikan kriteria "incompleted" adalah: "belum ada satupun Invoice yang lunas penuh".
                    // });
                    $data->join('order_header as oh', 'request_quotation.no_document', '=', 'oh.no_document')
                    ->distinct();
                    $data->join('invoice as i', function($join) { // Gunakan 'invoices' jika itu nama tabel yang benar
                        $join->on('oh.no_order', '=', 'i.no_order');
                        
                        // 🚨 PERBAIKAN: Gunakan OR untuk mendefinisikan BELUM LUNAS
                        $join->where(function($q) {
                            $q->whereNull('i.nilai_pelunasan') // Kriteria 1: Belum ada pembayaran
                            ->orWhereRaw('i.nilai_pelunasan < i.nilai_tagihan'); // Kriteria 2: Dibayar parsial
                        });
                    })
                    ->distinct();
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
                        $query->where('request_quotation.no_document', 'like', "%{$keyword}%");
                    } elseif ($mode == 'kontrak') {
                        $query->where('header.no_document', 'like', "%{$keyword}%");
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
                'error' => $e->getMessage(),
                'line' =>$e->getLine(),
                'file' =>$e->getFile()
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
