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
    // public function index(Request $request)
    // {
    //     try {
            
    //         $mode = $request->mode;
    //         if ($request->mode == 'non_kontrak') {
    //             $data = QuotationNonKontrak::with([
    //                 'sales',
    //                 'sampling' => function ($q) {
    //                     $q->orderBy('periode_kontrak', 'asc');
    //                 },
    //                 'orderHeader.invoices.recordWithdraw'
    //             ])
    //             ->select('request_quotation.*')
    //                 ->where('request_quotation.id_cabang', $request->cabang) 
    //                 ->whereHas('orderHeader') // whereHas tidak perlu dikualifikasi
    //                 ->where('request_quotation.is_approved', true) 
    //                 ->where('request_quotation.is_emailed', true) // <-- INI YANG MENYEBABKAN ERROR
    //                 ->whereYear('request_quotation.tanggal_penawaran', $request->year) 
    //                 ->whereMonth('request_quotation.tanggal_penawaran', '>=', 11)
    //                 ->orderBy('request_quotation.tanggal_penawaran', 'desc');
    //         } else if ($request->mode == 'kontrak') {
    //             /* $data = QuotationKontrakD::with([
    //                 'header',
    //                 'header.sales',
    //                 'header.link_lhp',
    //                 'header.sampling' => function ($q) {
    //                     $q->orderBy('periode_kontrak', 'asc');
    //                 },
    //                 'header.orderHeader.invoices.recordWithdraw'
    //             ])
    //                 ->select('request_quotation_kontrak_D.*')
    //                 ->whereHas('header', function ($q) use ($request) {
    //                     $q->where('id_cabang', $request->cabang)
    //                         ->where('is_approved', true)
    //                         ->where('is_emailed', true)
    //                         ->where('is_active', true)
    //                         ->whereYear('tanggal_penawaran', $request->year)
    //                         ->whereHas('orderHeader')
    //                         ->whereYear('tanggal_penawaran', $request->year)
    //                         ->whereMonth('tanggal_penawaran', '>=', 11)
    //                         ->orderBy('tanggal_penawaran', 'desc');
    //                 })
    //                 ->orderBy('request_quotation_kontrak_D.id', 'desc'); */
    //                 //optimasi
    //                 $data = QuotationKontrakD::with([
    //                     'header',
    //                     'header.sales',
    //                     // 'header.link_lhp', // Jika JOIN digunakan, eager loading ini mungkin tidak diperlukan
    //                     'header.sampling' => function ($q) {
    //                         $q->orderBy('periode_kontrak', 'asc');
    //                     },
    //                     'header.orderHeader.invoices.recordWithdraw'
    //                 ]);
    //                 // Memastikan kolom D yang diambil, JIKA ADA JOIN EKSPLISIT
    //                 $data->select(
    //                     'request_quotation_kontrak_D.*',
    //                     // Tambahkan kolom yang digunakan untuk ORDER BY:
    //                     'header.tanggal_penawaran as header_tanggal_penawaran',
    //                     'header.id as header_id_fk' // Penting untuk memastikan relasi 'header' tetap berfungsi di Eloquent
    //                 );

    //                 // --- 🚀 INISIASI JOIN PERFORMA TINGGI DI SINI ---
                    
    //                 // 1. JOIN ke Header (Wajib, untuk filter Header dan untuk JOIN ke LHP)
    //                 $data->join('request_quotation_kontrak_H as header', 
    //                         'request_quotation_kontrak_D.id_request_quotation_kontrak_h', 
    //                         '=', 
    //                         'header.id')
    //                 ->distinct(); // Mencegah baris D terduplikasi jika ada relasi 1:N

    //                 // 2. Filter Header (Sama seperti whereHas sebelumnya, tapi diterapkan langsung)
    //                 $data->where('header.id_cabang', $request->cabang)
    //                     ->where('header.is_approved', true)
    //                     ->where('header.is_emailed', true)
    //                     ->where('header.is_active', true)
    //                     ->whereYear('header.tanggal_penawaran', $request->year)
    //                     // Asumsi: orderHeader/tanggal_penawaran hanya ada di Header (H)
    //                     ->whereMonth('header.tanggal_penawaran', '>=', 11);
    //                 $data->orderBy('header.tanggal_penawaran', 'desc')
    //                 ->orderBy('request_quotation_kontrak_D.id', 'desc');
    //         }

    //         $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
    //         if($mode == 'non_kontrak') {
    //             if ($jabatan == 24 || $jabatan == 86) { // sales staff || Secretary Staff
    //                 $data->where('request_quotation.sales_id', $this->user_id);
    //             } else if ($jabatan == 21 || $jabatan == 15 || $jabatan == 154) { // sales supervisor || sales manager || senior sales manager
    //                 $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->toArray();
    //                 array_push($bawahan, $this->user_id);
    //                 $data->whereIn('request_quotation.sales_id', $bawahan);
    //             }
    //         }else if($mode == 'kontrak') {
    //             $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->toArray();
    //             if ($jabatan == 24 || $jabatan == 86) { 
    //                 $data->whereHas('header', function ($q) {
    //                     $q->where('request_quotation_kontrak_H.sales_id', $this->user_id);
    //                 });

    //             } else if ($jabatan == 21 || $jabatan == 15 || $jabatan == 154) {
    //                 array_push($bawahan, $this->user_id);

    //                 $data->whereHas('header', function ($q) use ($bawahan) {
    //                     $q->whereIn('request_quotation_kontrak_H.sales_id', $bawahan);
    //                 });
    //             }
    //         }

    //         $filterStatusType = $request->input('filter_status_type'); // Ambil parameter dari frontend
           
    //         if ($filterStatusType === 'completed') {
    //             // 🎯 LOGIKA: HARUS MEMENUHI KRITERIA LHP SELESAI DAN INVOICE LUNAS

    //             if ($request->mode == 'kontrak'){
                      
    //                 $data->join('link_lhp as lhp', function($join) {
    //                     $join->on('header.no_document', '=', 'lhp.no_quotation') 
    //                         ->on('lhp.periode', '=', DB::raw('request_quotation_kontrak_D.periode_kontrak'))
    //                         ->where('lhp.is_completed', true);
    //                 });
    //                 $data->distinct(); 

                    
    //                 $data->join('order_header as oh', 'header.no_document', '=', 'oh.no_document')
    //                 ->distinct();
    //                 $data->join('invoice as i', function($join) {
    //                     $join->on('oh.no_order', '=', 'i.no_order');
    //                     $join->whereNotNull('i.nilai_pelunasan')
    //                         ->whereRaw('i.nilai_pelunasan >= i.nilai_tagihan');
    //                     $join->on('i.periode', '=', DB::raw('request_quotation_kontrak_D.periode_kontrak'));
    //                 })
    //                 ->distinct();
    //             }else{
    //                 $data->join('order_header as oh', 'request_quotation.no_document', '=', 'oh.no_document')
    //                     ->distinct()
    //                     ->whereNotExists(function($query) {
    //                         $query->select(DB::raw(1))
    //                             ->from('invoice as i')
    //                             ->whereColumn('i.no_order', '=', 'oh.no_order')
    //                             ->where(function($q) {
    //                                 $q->whereNull('i.nilai_pelunasan')  // Belum bayar
    //                                     ->orWhereRaw('i.nilai_pelunasan < i.nilai_tagihan'); // Parsial
    //                             });
    //                     });
    //                 $data->join('link_lhp as lhp', function($join) {
    //                     $join->on('request_quotation.no_document', '=', 'lhp.no_quotation')
    //                         ->where('lhp.is_completed', true);
    //                 });
    //                 $data->whereHas('orderHeader.invoices');
    //             }
                
    //         }else if($filterStatusType === 'incompleted'){
                
    //             if ($request->mode == 'kontrak'){
                      
    //                 $data->join('link_lhp as lhp', function($join) {
    //                     $join->on('header.no_document', '=', 'lhp.no_quotation') 
    //                         ->on('lhp.periode', '=', DB::raw('request_quotation_kontrak_D.periode_kontrak'));
    //                 });
    //                 $data->distinct();
    //                 // Filter untuk LHP: (LHP belum ada [NULL] ATAU LHP ada tapi is_completed = FALSE)
    //                 $data->where(function($q) {
    //                     $q->whereNull('lhp.no_quotation') // Data belum memiliki LHP
    //                     ->orWhere('lhp.is_completed', false); // Data memiliki LHP tapi belum completed
    //                 });
    //                 $data->join('order_header as oh', 'header.no_document', '=', 'oh.no_document')
    //                 ->distinct();
    //                 // 3. JOIN Invoice (Menggunakan LEFT JOIN untuk menyertakan Detail yang BELUM ADA Invoice)
    //                 $data->leftJoin('invoice as i', function($join) {
    //                     $join->on('oh.no_order', '=', 'i.no_order')
    //                         ->on('i.periode', '=', DB::raw('request_quotation_kontrak_D.periode_kontrak'));
    //                 })
    //                 ->distinct();
    //                 // Filter untuk Invoice: (Invoice belum ada [NULL] ATAU Invoice ada tapi BELUM LUNAS)
    //                 $data->where(function($q) {
    //                     $q->whereNull('i.no_invoice') // Data belum memiliki Invoice
    //                     // Jika ada Invoice, pastikan belum lunas (Pelunasan NULL ATAU Pelunasan < Tagihan)
    //                     ->orWhere(function($q2) {
    //                         $q2->whereNotNull('i.no_invoice') // Ada Invoice
    //                             ->where(function($q3) {
    //                                 $q3->whereNull('i.nilai_pelunasan') // Pelunasan NULL
    //                                     ->orWhereRaw('i.nilai_pelunasan < i.nilai_tagihan'); // Pelunasan Parsial
    //                             });
    //                     });
    //                 });
    //             }else{
    //                 $data->where(function ($query) {
    //                     $query->whereDoesntHave('link_lhp', function ($q) {
    //                         $q->where('is_completed', true); // TIDAK memiliki LHP COMPLETED
    //                     });
    //                     $query->orWhereHas('orderHeader.invoices', function ($q) {
    //                         $q->where(function($q2) {
    //                             $q2->whereNull('nilai_pelunasan') 
    //                             ->orWhereRaw('nilai_pelunasan < nilai_tagihan');
    //                         });
    //                     });
    //                 });
    //             }
    //         }
            
    //         return DataTables::of($data)
    //             ->addColumn('count_jadwal', function ($row) {
    //                 return $row->sampling ? $row->sampling->sum(function ($sampling) {
    //                     return $sampling->jadwal->count();
    //                 }) : 0;
    //             })
    //             ->filterColumn('no_document', function ($query, $keyword) use ($mode) {
    //                 if ($mode == 'non_kontrak') {
    //                     $query->where('request_quotation.no_document', 'like', "%{$keyword}%");
    //                 } elseif ($mode == 'kontrak') {
    //                     $query->where('header.no_document', 'like', "%{$keyword}%");
    //                 }
    //             })
    //             ->filterColumn('invoice', function ($query, $keyword) use ($mode) {
    //                 if ($mode == 'non_kontrak') {
    //                     $query->whereHas('orderHeader.invoices', function ($q) use ($keyword) {
    //                         $q->where('no_invoice', 'like', "%{$keyword}%");
    //                     });
    //                 }

    //                 if ($mode == 'kontrak') {
    //                     $query->whereHas('header.orderHeader.invoices', function ($q) use ($keyword) {
    //                         $q->where('no_invoice', 'like', "%{$keyword}%");
    //                     });
    //                 }
    //             })
    //             ->addColumn('total_invoice', function ($row) use ($mode) {
    //                 if ($mode == 'non_kontrak') {
    //                     if (!$row->orderHeader) return 0;
    //                     return $row->orderHeader->getInvoice->count();
    //                 } else if ($mode == 'kontrak') {
    //                     if (!$row->header->orderHeader) return 0;
    //                     $filtered = $row->header->orderHeader->getInvoice
    //                         ->where('periode', $row->periode_kontrak);
    //                     if ($filtered->isEmpty()) {
    //                         $filtered = $row->header->orderHeader->getInvoice
    //                             ->where('periode', 'all');
    //                     };
    //                     return $filtered->count();
    //                 };
    //             })
    //             ->editColumn('link_lhp', function ($row) use ($mode) {
    //                 if ($mode == 'kontrak') {
    //                     if (!$row->header || !$row->header->link_lhp) return null;

    //                     // Filter link_lhp berdasarkan periode_kontrak
    //                     $filtered = $row->header->link_lhp->where('periode', $row->periode_kontrak)->first();

    //                     return json_decode($filtered, true);
    //                 } else {
    //                     return json_decode($row->link_lhp, true);
    //                 }
    //             })
    //             ->addColumn('nilai_pelunasan', function ($row) use ($mode) {
    //                 if ($mode == 'non_kontrak') {
    //                     if (!$row->orderHeader || $row->orderHeader->getInvoice->isEmpty()) return '-';
    //                     $totalPelunasan = $row->orderHeader->getInvoice->sum('nilai_pelunasan');

    //                     return $totalPelunasan;
    //                 } else if ($mode == 'kontrak') {
    //                     if (!$row->header->orderHeader || $row->header->orderHeader->getInvoice->isEmpty()) return '-';
    //                     $filtered = $row->header->orderHeader->getInvoice
    //                         ->where('periode', $row->periode_kontrak);
    //                     if ($filtered->isEmpty()) {
    //                         $filtered = $row->header->orderHeader->getInvoice
    //                             ->where('periode', 'all');
    //                     };
    //                     $totalPelunasan = $filtered->sum('nilai_pelunasan');

    //                     return $totalPelunasan;
    //                 };
    //             })
    //             ->addColumn('invoice', function ($row) use ($mode) {
    //                 if ($mode == 'non_kontrak') {
    //                     if (!$row->orderHeader || $row->orderHeader->getInvoice->isEmpty()) return 0;
                        
    //                     $filtered = $row->orderHeader->getInvoice
    //                         ->where('periode', $row->periode_kontrak)->where('no_quotation', $row->no_document);
    //                     if ($filtered->isEmpty()) {
    //                         $filtered = $row->orderHeader->getInvoice
    //                             ->where('periode', 'all')->where('no_quotation', $row->no_document);
    //                     };


    //                     return $filtered->values()->toArray();
    //                 } else if ($mode == 'kontrak') {
    //                     if (!$row->header->orderHeader || $row->header->orderHeader->getInvoice->isEmpty()) return '-';
    //                     $filtered = $row->header->orderHeader->getInvoice
    //                         ->where('periode', $row->periode_kontrak)->where('no_quotation', $row->header->no_document);
    //                     if ($filtered->isEmpty()) {
    //                         $filtered = $row->header->orderHeader->getInvoice
    //                             ->where('periode', 'all')->where('no_quotation', $row->header->no_document);
    //                     };

    //                     return $filtered->values()->toArray();
    //                 };
    //             })
    //             ->make(true);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'error' => $e->getMessage(),
    //             'line' =>$e->getLine(),
    //             'file' =>$e->getFile()
    //         ], 500);
    //     }
    // }

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
    //==== masak
    public function index(Request $request)
    {
        try {
            $mode = $request->mode;
            $filterStatusType = $request->input('filter_status_type');
            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;

            // ===== STEP 1: QUERY SEDERHANA DENGAN INDEX =====
            if ($mode == 'non_kontrak') {
                // Query BASE - hanya filter utama (cepat dengan index)
                $data = QuotationNonKontrak::query()
                    ->with([
                        'sales',
                        'sampling' => function ($q) {
                            $q->orderBy('periode_kontrak', 'asc');
                        },
                        'orderHeader.getInvoice.recordWithdraw',
                        'link_lhp' // Eager load untuk cek status
                    ])
                    ->where('id_cabang', $request->cabang)
                    ->where('is_approved', true)
                    ->where('is_emailed', true)
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->whereMonth('tanggal_penawaran', '>=', 11)
                    ->whereHas('orderHeader') // Harus punya order
                    ->orderBy('tanggal_penawaran', 'desc');

                // Filter jabatan
                if ($jabatan == 24 || $jabatan == 86) {
                    $data->where('sales_id', $this->user_id);
                } else if ($jabatan == 21 || $jabatan == 15 || $jabatan == 154) {
                    $bawahan = GetBawahan::where('id', $this->user_id)->pluck('id')->toArray();
                    $bawahan[] = $this->user_id;
                    $data->whereIn('sales_id', $bawahan);
                }

                $rawData = $data->get();

            } else if ($mode == 'kontrak') {
                // Query kontrak - sederhana
                $data = QuotationKontrakD::query()
                    ->select('request_quotation_kontrak_D.*')
                    ->with([
                        'header.sales',
                        'header.sampling' => function ($q) {
                            $q->orderBy('periode_kontrak', 'asc');
                        },
                        'header.orderHeader.getInvoice.recordWithdraw',
                        'header.link_lhp'
                    ])
                    ->whereHas('header', function ($q) use ($request) {
                        $q->where('id_cabang', $request->cabang)
                            ->where('is_approved', true)
                            ->where('is_emailed', true)
                            ->where('is_active', true)
                            ->whereYear('tanggal_penawaran', $request->year)
                            ->whereMonth('tanggal_penawaran', '>=', 11)
                            ->whereHas('orderHeader');
                    });

                // Filter jabatan
                
                if ($jabatan == 24 || $jabatan == 86) {
                    $data->whereHas('header', function ($q) {
                        $q->where('sales_id', $this->user_id);
                    });
                } else if ($jabatan == 21 || $jabatan == 15 || $jabatan == 154) {
                    $bawahan[] = $this->user_id;
                    $data->whereHas('header', function ($q) use ($bawahan) {
                        $q->whereIn('sales_id', $bawahan);
                    });
                }

                $data->orderBy('id', 'desc');
                $rawData = $data->get();
            }

            // ===== STEP 2: MAPPING COLLECTION + LOGIC BUSINESS =====
            $mappedData = $rawData->map(function ($item) use ($mode, $filterStatusType) {
                // Calculate status untuk setiap item
                $status = $this->calculateItemStatus($item, $mode);
                
                // Filter berdasarkan status (di PHP, bukan SQL)
                if ($filterStatusType === 'completed' && $status !== 'completed') {
                    return null;
                }
                if ($filterStatusType === 'incompleted' && $status !== 'incompleted') {
                    return null;
                }

                // Build data untuk DataTable
                return $this->mapItemForDataTable($item, $mode, $status);
            })->filter()->values(); // Remove null values & reindex

            // ===== STEP 3: KIRIM KE DATATABLE =====
            // Gunakan DataTables::collection() untuk array/collection
            return DataTables::collection($mappedData)
                // Semua column sudah ada di array, langsung akses
                ->addColumn('count_jadwal', function ($row) {
                    return $row['count_jadwal'];
                })
                ->addColumn('total_invoice', function ($row) {
                    return $row['total_invoice'];
                })
                ->addColumn('nilai_pelunasan', function ($row) {
                    return $row['nilai_pelunasan'];
                })
                ->addColumn('invoice', function ($row) {
                    return $row['invoice'];
                })
                ->editColumn('link_lhp', function ($row) {
                    return $row['link_lhp'];
                })
                // Filter custom untuk search
                ->filter(function ($instance) use ($request) {
                    if ($request->has('search') && $search = $request->search['value']) {
                        $instance->collection = $instance->collection->filter(function ($row) use ($search) {
                            // Search di no_document
                            if (stripos($row['no_document'], $search) !== false) {
                                return true;
                            }
                            // Search di invoice number
                            if (!empty($row['invoice'])) {
                                foreach ($row['invoice'] as $inv) {
                                    if (stripos($inv['no_invoice'] ?? '', $search) !== false) {
                                        return true;
                                    }
                                }
                            }
                            return false;
                        });
                    }
                })
                ->make(true);

        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    /**
     * HELPER: Hitung status item (completed/incompleted)
     */
    private function calculateItemStatus($item, $mode)
    {
        if ($mode == 'non_kontrak') {
            // 1. Cek LHP
            $lhpCompleted = $item->link_lhp && $item->link_lhp->is_completed;
            
            // 2. Cek Invoice - HARUS ADA dan SEMUA LUNAS
            $hasInvoice = false;
            $allInvoicesPaid = false;
            
            if ($item->orderHeader && $item->orderHeader->getInvoice && $item->orderHeader->getInvoice->isNotEmpty()) {
                $hasInvoice = true;
                $allInvoicesPaid = true;
                
                foreach ($item->orderHeader->getInvoice as $invoice) {
                    // Jika pelunasan NULL atau kurang dari tagihan = belum lunas
                    if (!$invoice->nilai_pelunasan || $invoice->nilai_pelunasan < $invoice->nilai_tagihan) {
                        $allInvoicesPaid = false;
                        break;
                    }
                }
            }

            // COMPLETED = LHP Complete + Ada Invoice + Semua Invoice Lunas
            return ($lhpCompleted && $hasInvoice && $allInvoicesPaid) ? 'completed' : 'incompleted';

        } else if ($mode == 'kontrak') {
            // 1. Check LHP untuk periode ini
            $lhpCompleted = false;
            if ($item->header && $item->header->link_lhp) {
                $lhp = $item->header->link_lhp->where('periode', $item->periode_kontrak)->first();
                $lhpCompleted = $lhp && $lhp->is_completed;
            }

            // 2. Check invoice untuk periode ini - HARUS ADA dan SEMUA LUNAS
            $hasInvoice = false;
            $allInvoicesPaid = false;
            
            if ($item->header && $item->header->orderHeader && $item->header->orderHeader->getInvoice) {
                $invoices = $item->header->orderHeader->getInvoice
                    ->where('periode', $item->periode_kontrak);
                
                if ($invoices->isEmpty()) {
                    $invoices = $item->header->orderHeader->getInvoice->where('periode', 'all');
                }

                if ($invoices->isNotEmpty()) {
                    $hasInvoice = true;
                    $allInvoicesPaid = true;
                    
                    foreach ($invoices as $invoice) {
                        if (!$invoice->nilai_pelunasan || $invoice->nilai_pelunasan < $invoice->nilai_tagihan) {
                            $allInvoicesPaid = false;
                            break;
                        }
                    }
                }
            }

            // COMPLETED = LHP Complete + Ada Invoice + Semua Invoice Lunas
            return ($lhpCompleted && $hasInvoice && $allInvoicesPaid) ? 'completed' : 'incompleted';
        }

        return 'incompleted';
    }

    /**
     * HELPER: Map item ke format DataTable
     */
    private function mapItemForDataTable($item, $mode, $status)
    {
        if ($mode == 'non_kontrak') {
            return [
                'id' => $item->id,
                'no_document' => $item->no_document,
                'filename' => $item->filename,
                'tanggal_penawaran' => $item->tanggal_penawaran,
                'pelanggan_ID' => $item->pelanggan_ID, // Tambahan
                'nama_perusahaan' => $item->nama_perusahaan, // Tambahan
                'konsultan' => $item->konsultan, // Tambahan
                'status_sampling' => $item->status_sampling, // Tambahan
                'sales' => $item->sales,
                'status' => $status,
                'count_jadwal' => $item->sampling ? $item->sampling->sum(function ($sampling) {
                    return $sampling->jadwal->count();
                }) : 0,
                'total_invoice' => $item->orderHeader ? $item->orderHeader->getInvoice->count() : 0,
                'nilai_pelunasan' => $item->orderHeader && $item->orderHeader->getInvoice 
                    ? $item->orderHeader->getInvoice->sum('nilai_pelunasan') 
                    : 0,
                'invoice' => $this->getInvoicesForItem($item, $mode),
                'link_lhp' => json_decode($item->link_lhp, true),
                'order_header'=>[
                    'id' =>  $item->orderHeader ? $item->orderHeader->id : null,
                    'tanggal_order' =>  $item->orderHeader ? $item->orderHeader->tanggal_order : null,
                    'no_order' =>  $item->orderHeader ? $item->orderHeader->no_order : null,
                ],
                // Original object untuk akses lain
                '_original' => $item
            ];

        } else if ($mode == 'kontrak') {
            $invoicesData = [];
            
            // Optimasi: Cek dulu apakah ada invoices sebelum filter
            if ($item->header && $item->header->orderHeader && $item->header->orderHeader->getInvoice && $item->header->orderHeader->getInvoice->isNotEmpty()) {
                $filtered = $item->header->orderHeader->getInvoice
                    ->where('periode', $item->periode_kontrak)
                    ->where('no_quotation', $item->header->no_document);
                
                if ($filtered->isEmpty()) {
                    $filtered = $item->header->orderHeader->getInvoice
                        ->where('periode', 'all')
                        ->where('no_quotation', $item->header->no_document);
                }
                
                // PENTING: Ambil hanya field yang dibutuhkan
                $invoicesData = $filtered->map(function($inv) {
                    return [
                        'id' => $inv->id,
                        'no_invoice' => $inv->no_invoice,
                        'no_quotation' => $inv->no_quotation,
                        'nilai_tagihan' => $inv->nilai_tagihan,
                        'nilai_pelunasan' => $inv->nilai_pelunasan,
                        'tgl_pelunasan' => $inv->tgl_pelunasan,
                        'record_withdraw' => $inv->recordWithdraw ? $inv->recordWithdraw->map(function($rw) {
                            return [
                                'id' => $rw->id,
                                'nilai_pembayaran' => $rw->nilai_pembayaran
                            ];
                        })->toArray() : []
                    ];
                })->values()->toArray();
            }

            $lhpData = null;
            if ($item->header && $item->header->link_lhp && $item->header->link_lhp->isNotEmpty()) {
                $lhp = $item->header->link_lhp->where('periode', $item->periode_kontrak)->first();
                if ($lhp) {
                    $lhpData = [
                        'id' => $lhp->id,
                        'no_quotation' => $lhp->no_quotation,
                        'periode' => $lhp->periode,
                        'jumlah_lhp' => $lhp->jumlah_lhp,
                        'jumlah_lhp_rilis' => $lhp->jumlah_lhp_rilis,
                        'is_completed' => $lhp->is_completed
                    ];
                }
            }

            return [
                'id' => $item->header->id,
                'no_document' => $item->header->no_document ?? null,
                'filename' => $item->header->filename ?? null,
                'periode_kontrak' => $item->periode_kontrak,
                'tanggal_penawaran' => $item->header->tanggal_penawaran ?? null,
                'pelanggan_ID' => $item->header->pelanggan_ID ?? null,
                'nama_perusahaan' => $item->header->nama_perusahaan ?? null,
                'konsultan' => $item->header->konsultan ?? null,
                'status_sampling' => $item->header->status_sampling ?? null,
                'sales' => $item->header->sales ? [
                    'id' => $item->header->sales->id,
                    'nama_lengkap' => $item->header->sales->nama_lengkap,
                    'email' => $item->header->sales->email
                ] : null,
                'status' => $status,
                'count_jadwal' => $item->header && $item->header->sampling && $item->header->sampling->isNotEmpty()
                    ? $item->header->sampling->sum(function ($sampling) {
                        return $sampling->jadwal ? $sampling->jadwal->count() : 0;
                    }) : 0,
                'total_invoice' => count($invoicesData),
                'nilai_pelunasan' => collect($invoicesData)->sum('nilai_pelunasan'),
                'invoice' => $invoicesData,
                'link_lhp' => $lhpData,
                'order_header'=>[
                    'id' =>  $item->header->orderHeader->id,
                    'tanggal_order' =>  $item->header->orderHeader->tanggal_order,
                    'no_order' =>  $item->header->orderHeader->no_order,
                ],
                // PENTING: Jangan simpan full object, hanya data minimal untuk fallback
                '_original' => [
                    'id' => $item->id,
                        'periode_kontrak' => $item->periode_kontrak,
                        'piutang' => $item->header->piutang ?? 0,
                    'status_sampling' => $item->header->status_sampling ?? null,
                    'konsultan' => $item->header->konsultan ?? null,
                    'pelanggan_ID' => $item->header->pelanggan_ID ?? null,
                    'nama_perusahaan' => $item->header->nama_perusahaan ?? null,
                ]
            ];
        }
    }

    /**
     * HELPER: Get invoices untuk item (RAW untuk status checking)
     */
    private function getInvoicesForItemRaw($item, $mode)
    {
        if ($mode == 'non_kontrak') {
            if (!$item->orderHeader || !$item->orderHeader->getInvoice || $item->orderHeader->getInvoice->isEmpty()) {
                return [];
            }
            
            // Untuk non-kontrak, ambil semua invoice dari order
            return $item->orderHeader->getInvoice->toArray();
        }
        
        if ($mode == 'kontrak') {
            if (!$item->header || !$item->header->orderHeader || !$item->header->orderHeader->getInvoice || $item->header->orderHeader->getInvoice->isEmpty()) {
                return [];
            }
            
            $invoices = $item->header->orderHeader->getInvoice
                ->where('periode', $item->periode_kontrak)
                ->where('no_quotation', $item->header->no_document);
            
            if ($invoices->isEmpty()) {
                $invoices = $item->header->orderHeader->getInvoice
                    ->where('periode', 'all')
                    ->where('no_quotation', $item->header->no_document);
            }
            
            return $invoices->values()->toArray();
        }
        
        return [];
    }

    /**
     * HELPER: Get invoices untuk item (untuk display)
     */
    private function getInvoicesForItem($item, $mode)
    {
        return $this->getInvoicesForItemRaw($item, $mode);
    }

    /**
     * HELPER: Calculate total pelunasan
     */
    private function calculateTotalPelunasan($item, $mode)
    {
        $invoices = $this->getInvoicesForItemRaw($item, $mode);
        
        if (empty($invoices)) {
            return 0;
        }
        
        $total = 0;
        foreach ($invoices as $invoice) {
            $total += $invoice['nilai_pelunasan'] ?? $invoice->nilai_pelunasan ?? 0;
        }
        
        return $total;
    }

    /**
     * HELPER: Get LHP untuk kontrak
     */
    private function getLhpForKontrak($item)
    {
        if (!$item->header || !$item->header->link_lhp) {
            return null;
        }
        
        $filtered = $item->header->link_lhp
            ->where('periode', $item->periode_kontrak)
            ->first();
        
        return json_decode($filtered, true);
    }
}
