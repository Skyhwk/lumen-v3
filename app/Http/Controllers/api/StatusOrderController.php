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
                    // ->whereMonth('tanggal_penawaran', '>=', 11)
                    ->whereHas('orderHeader') // Harus punya order
                    ->orderBy('tanggal_penawaran', 'desc');

                // Filter jabatan
                if ($jabatan == 24 || $jabatan == 86) {
                    $data->where('sales_id', $this->user_id);
                } else if ($jabatan == 21 || $jabatan == 15 || $jabatan == 154) {
                    // $bawahan = GetBawahan::where('id', $this->user_id)->pluck('id')->toArray();
                    // $bawahan[] = $this->user_id;

                    $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->toArray();
                    array_push($bawahan, $this->user_id);
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
                            // ->whereMonth('tanggal_penawaran', '>=', 11)
                            ->whereHas('orderHeader');
                    });

                // Filter jabatan
                
                if ($jabatan == 24 || $jabatan == 86) {
                    $data->whereHas('header', function ($q) {
                        $q->where('sales_id', $this->user_id);
                    });
                } else if ($jabatan == 21 || $jabatan == 15 || $jabatan == 154) {
                    // $bawahan[] = $this->user_id;
                    $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->toArray();
                    array_push($bawahan, $this->user_id);
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
                ->filter(function ($instance) use ($request, $mode) {
                    $globalSearch = $request->input('search.value');
                    $columns = $request->input('columns');
                    \Log::info('Columns received:', $columns);
                    $instance->collection = $instance->collection->filter(function ($row) use ($globalSearch, $columns, $mode) {
                        $row = (array) $row;
                        // ===== LOGIKA GLOBAL SEARCH =====
                        if ($globalSearch) {
                            $searchableColumns = ['no_document', 'pelanggan_ID', 'nama_perusahaan', 'konsultan','status_sampling','sales','invoice_searchable'];
                            foreach ($searchableColumns as $key) {
                                if (stripos($row[$key] ?? '', $globalSearch) !== false) {
                                    return true;
                                }
                            }
                            return false;
                        }

                        // ===== LOGIKA PER KOLOM SEARCH =====
                        foreach ($columns as $column) {
                            $columnSearch = $column['search']['value'] ?? null;
                            $dataKey = $column['data'] ?? null;
                            
                            // Perbaikan handling null/array untuk stripos
            
                            if ($columnSearch && $dataKey) {
        
                                // --- MODIFIKASI DIMULAI DARI SINI ---
                                
                                // Jika kolom yang dicari adalah 'invoice' (Array), alihkan ke 'invoice_searchable' (String)
                                if ($dataKey === 'invoice') {
                                    $searchValue = $row['invoice_searchable'] ?? '';
                                } else {
                                    // Untuk kolom lain, ambil normal
                                    $searchValue = $this->getNestedValue($row, $dataKey, $mode);
                                }

                                // ------------------------------------

                                // Pengecekan standar (sama seperti kodemu)
                                if (is_array($searchValue)) {
                                    return false; 
                                }

                                if ($searchValue === null || stripos((string)$searchValue, $columnSearch) === false) {
                                    return false;
                                }
                            }
                        }
                        
                        return true;
                    });
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
                
                // foreach ($item->orderHeader->getInvoice as $invoice) {
                //     // Jika pelunasan NULL atau kurang dari tagihan = belum lunas
                //     if (!$invoice->nilai_pelunasan || $invoice->nilai_pelunasan < $invoice->nilai_tagihan) {
                //         $allInvoicesPaid = false;
                //         break;
                //     }
                // }
                foreach ($item->orderHeader->getInvoice as $invoice) {
                    // Hitung total withdraw dari relasi recordWithdraw
                    // Pastikan nama kolom jumlah uangnya sesuai (misal: 'nominal', 'amount', dll)
                    $totalWithdraw = $invoice->recordWithdraw->sum('nilai_pembayaran'); 
                    
                    // Hitung total yang sudah dibayar (Pelunasan + Withdraw)
                    $totalPaid = ($invoice->nilai_pelunasan ?? 0) + $totalWithdraw;

                    // Cek apakah total bayar kurang dari tagihan (toleransi floating point jika perlu)
                    if ($totalPaid < $invoice->nilai_tagihan) {
                        $allInvoicesPaid = false;
                        break; // Ada satu saja belum lunas, status failed
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
                    
                    // foreach ($invoices as $invoice) {
                    //     if (!$invoice->nilai_pelunasan || $invoice->nilai_pelunasan < $invoice->nilai_tagihan) {
                    //         $allInvoicesPaid = false;
                    //         break;
                    //     }
                    // }

                    foreach ($invoices as $invoice) {
                        // Hitung total withdraw
                        $totalWithdraw = $invoice->recordWithdraw->sum('nilai_pembayaran');

                        // Hitung total yang sudah dibayar
                        $totalPaid = ($invoice->nilai_pelunasan ?? 0) + $totalWithdraw;

                        // Validasi pelunasan
                        if ($totalPaid < $invoice->nilai_tagihan) {
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

    private function getNestedValue($array, $key, $mode = null)
    {
        // Handle kasus khusus untuk sales
        \Log::info('Columns received:', ['key' => $key]);
        if ($key === 'sales') {
            // Pastikan $array['sales'] ada DAN berupa array sebelum akses key di dalamnya
            if (isset($array['sales']) && is_array($array['sales'])) {
                return $array['sales']['nama_lengkap'] ?? null;
            }
            return null; // Return null jika sales tidak ada atau format salah
        }
        
        // Jika key sederhana (tanpa dot)
        if (!str_contains($key, '.')) {
            return $array[$key] ?? null;
        }
        
        // Jika key nested (contoh: 'sales.nama_lengkap')
        $keys = explode('', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return null;
            }
        }
        
        return $value;
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

    // Helper function untuk akses nested value
    // private function getNestedValue($array, $key)
    // {
    //     // Jika key sederhana (tanpa dot), langsung return
    //     if (!str_contains($key, '.')) {
    //         return $array[$key] ?? null;
    //     }
        
    //     // Jika key nested (contoh: 'sales.nama_lengkap')
    //     $keys = explode('.', $key);
    //     $value = $array;
        
    //     foreach ($keys as $k) {
    //         if (is_array($value) && isset($value[$k])) {
    //             $value = $value[$k];
    //         } else {
    //             return null;
    //         }
    //     }
        
    //     return $value;
    // }

    /**
     * HELPER: Map item ke format DataTable
     */
    private function mapItemForDataTable($item, $mode, $status)
    {
        if ($mode == 'non_kontrak') {
            $invoicesData = $this->getInvoicesForItem($item, $mode);
            return [
                'id' => $item->id,
                'no_document' => $item->no_document,
                'filename' => $item->filename,
                'tanggal_penawaran' => $item->tanggal_penawaran,
                'pelanggan_ID' => $item->pelanggan_ID, // Tambahan
                'nama_perusahaan' => $item->nama_perusahaan, // Tambahan
                'konsultan' => $item->konsultan, // Tambahan
                'status_sampling' => $item->status_sampling, // Tambahan
                'sales' => $item->sales ? $item->sales->nama_lengkap : null,
                'status' => $status,
                'count_jadwal' => $item->sampling ? $item->sampling->sum(function ($sampling) {
                    return $sampling->jadwal->count();
                }) : 0,
                'total_invoice' => $item->orderHeader ? $item->orderHeader->getInvoice->count() : 0,
                'nilai_pelunasan' => $item->orderHeader && $item->orderHeader->getInvoice 
                    ? $item->orderHeader->getInvoice->sum('nilai_pelunasan') 
                    : 0,
                'invoice' => $this->getInvoicesForItem($item, $mode),
                'invoice_searchable' => !empty($invoicesData) 
                ? implode(', ', array_column($invoicesData, 'no_invoice')) 
                : null,
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
                'sales' => $item->header->sales ? $item->header->sales->nama_lengkap :null,
                'status' => $status,
                'count_jadwal' => $item->header && $item->header->sampling && $item->header->sampling->isNotEmpty()
                    ? $item->header->sampling->sum(function ($sampling) {
                        return $sampling->jadwal ? $sampling->jadwal->count() : 0;
                    }) : 0,
                'total_invoice' => count($invoicesData),
                'nilai_pelunasan' => collect($invoicesData)->sum('nilai_pelunasan'),
                'invoice_searchable' => !empty($invoicesData) 
                ? implode(', ', array_column($invoicesData, 'no_invoice')) 
                : null,
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
