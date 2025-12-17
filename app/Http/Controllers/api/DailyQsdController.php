<?php
namespace App\Http\Controllers\api;

date_default_timezone_set('Asia/Jakarta');

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

use App\Models\DailyQsd;
use App\Models\Invoice;

class DailyQsdController extends Controller
{

    // public function index(Request $request)
    // {
    //     $query = DB::table('daily_qsd')
    //         ->select(
    //             'daily_qsd.no_order',
    //             'daily_qsd.no_quotation',
    //             'daily_qsd.periode',
    //             'daily_qsd.tanggal_sampling_min',
    //             'daily_qsd.nama_perusahaan',
    //             'daily_qsd.konsultan',
    //             'daily_qsd.kontrak',
    //             'daily_qsd.sales_nama',
    //             'daily_qsd.total_discount',
    //             'daily_qsd.total_ppn',
    //             'daily_qsd.total_pph',
    //             'daily_qsd.biaya_akhir',
    //             'daily_qsd.grand_total',
    //             'daily_qsd.total_revenue',
    //             // DB::raw('MAX(kelengkapan_konfirmasi_qs.approval_order) as approval_order'),
    //             // DB::raw('MAX(kelengkapan_konfirmasi_qs.no_purchaseorder) as no_purchaseorder'),
    //             // DB::raw('MAX(kelengkapan_konfirmasi_qs.no_co_qsd) as no_co_qsd'),
    //             DB::raw('GROUP_CONCAT(DISTINCT invoice.no_invoice SEPARATOR ", ") as no_invoice'),

    //         )->groupBy(
    //         'daily_qsd.no_order',
    //         'daily_qsd.no_quotation',
    //         'daily_qsd.periode',
    //         'daily_qsd.tanggal_sampling_min',
    //         'daily_qsd.nama_perusahaan',
    //         'daily_qsd.konsultan',
    //         'daily_qsd.kontrak',
    //         'daily_qsd.sales_nama',
    //         'daily_qsd.total_discount',
    //         'daily_qsd.total_ppn',
    //         'daily_qsd.total_pph',
    //         'daily_qsd.biaya_akhir',
    //         'daily_qsd.grand_total',
    //         'daily_qsd.total_revenue'
    //     );

    //     // $query->leftJoin(DB::raw('(
    //     //     SELECT *
    //     //     FROM kelengkapan_konfirmasi_qs
    //     //     WHERE keterangan_approval_order != "menyusul"
    //     //     AND is_active = 1
    //     // ) as kelengkapan_konfirmasi_qs'), function ($join) {
    //     //     $join->on('daily_qsd.no_quotation', '=', 'kelengkapan_konfirmasi_qs.no_quotation')
    //     //         ->on('daily_qsd.periode', '=', 'kelengkapan_konfirmasi_qs.periode');
    //     // });

    //     $query->leftJoin('invoice', function ($join) {
    //         $join->on('daily_qsd.no_quotation', '=', 'invoice.no_quotation')
    //             ->where(function ($q) {
    //                 $q->where('invoice.periode', 'all')
    //                     ->orWhereColumn('invoice.periode', 'daily_qsd.periode');
    //                 $q->where('invoice.is_active', 1);
    //             });

    //     });

    //     // Filter tanggal_sampling jika ada
    //     if ($request->filled('tanggal_sampling')) {
    //         $tanggalInput = $request->tanggal_sampling;

    //         // Deteksi format input
    //         if (strlen($tanggalInput) == 4 && is_numeric($tanggalInput)) {
    //             // Format: Y (tahun saja) - 2024
    //             $query->whereRaw("YEAR(tanggal_sampling_min) = ?", [$tanggalInput]);
    //         } elseif (strlen($tanggalInput) == 7 && strpos($tanggalInput, '-') !== false) {
    //             // Format: Y-m (bulan) - 2024-01
    //             $query->whereRaw("DATE_FORMAT(tanggal_sampling_min, '%Y-%m') = ?", [$tanggalInput]);
    //         } else {
    //             // Format: Y-m-d (tanggal lengkap) - 2024-01-15
    //             $query->whereDate('tanggal_sampling_min', $tanggalInput);
    //         }
    //     }

    //     // Filter periode untuk data kontrak
    //     if ($request->filled('periode')) {
    //         $periodeInput = $request->periode;

    //         if (strlen($periodeInput) == 4 && is_numeric($periodeInput)) {
    //             // Filter tahun periode
    //             $query->where(function ($q) use ($periodeInput) {
    //                 $q->where('kontrak', 'C')
    //                     ->whereRaw("LEFT(periode, 4) = ?", [$periodeInput]);
    //             });
    //         } elseif (strlen($periodeInput) == 7 && strpos($periodeInput, '-') !== false) {
    //             // Filter bulan periode
    //             $query->where(function ($q) use ($periodeInput) {
    //                 $q->where('kontrak', 'C')
    //                     ->where('periode', $periodeInput);
    //             });
    //         }
    //     }

    //     // Default ordering
    //     $query->orderBy('tanggal_sampling_min', 'desc')
    //         ->orderBy('no_order', 'asc');

    //     return DataTables::of($query)
    //         ->addColumn('tipe_quotation', function ($data) {
    //             return $data->kontrak == 'C' ? 'KONTRAK' : 'NON-KONTRAK';
    //         })
    //         ->addColumn('periode_aktif', function ($data) {
    //             if ($data->kontrak == 'C' && isset($data->periode)) {
    //                 return $data->periode;
    //             }
    //             return '-';
    //         })
    //         ->addColumn('sales_id', function ($data) {
    //             return $data->sales_id ?? '-';
    //         })
    //         ->addColumn('sales_nama', function ($data) {
    //             return $data->sales_nama ?? '-';
    //         })
    //         ->addColumn('total_revenue', function ($data) {
    //             return $data->total_revenue ?? 0;
    //         })
    //         ->filterColumn('no_order', function ($query, $keyword) {
    //             $query->where('daily_qsd.no_order', 'like', "%$keyword%");
    //         })
    //         ->filterColumn('no_quotation', function ($query, $keyword) {
    //             $query->where('daily_qsd.no_quotation', 'like', "%$keyword%");
    //         })
    //         ->filterColumn('nama_perusahaan', function ($query, $keyword) {
    //             $query->where('daily_qsd.nama_perusahaan', 'like', "%$keyword%");
    //         })
    //         ->filterColumn('konsultan', function ($query, $keyword) {
    //             $query->where('daily_qsd.konsultan', 'like', "%$keyword%");
    //         })
    //         ->filterColumn('tanggal_sampling_min', function ($query, $keyword) {
    //             $query->whereDate('daily_qsd.tanggal_sampling_min', 'like', "%$keyword%");
    //         })
    //         ->filterColumn('tipe_quotation', function ($query, $keyword) {
    //             $keyword = strtolower($keyword);
    //             if (strpos($keyword, 'kon') !== false) {
    //                 $query->where('kontrak', 'C');
    //             } elseif (strpos($keyword, 'non') !== false) {
    //                 $query->where('kontrak', '!=', 'C');
    //             }
    //         })
    //         ->filterColumn('sales_nama', function ($query, $keyword) {
    //             $keyword = strtolower($keyword);
    //             $query->where(function ($q) use ($keyword) {
    //                 $q->where('sales_nama', 'like', "%$keyword%");

    //             });
    //         })
    //         // ->filterColumn('no_co_qsd', function ($query, $keyword) {
    //         //     $query->where('kelengkapan_konfirmasi_qs.no_co_qsd', 'like', "%$keyword%");
    //         // })
    //         // ->filterColumn('no_purchaseorder', function ($query, $keyword) {
    //         //     $query->where('kelengkapan_konfirmasi_qs.no_purchaseorder', 'like', "%$keyword%");
    //         // })
    //         // ->filterColumn('approval_order', function ($query, $keyword) {
    //         //     $query->where('kelengkapan_konfirmasi_qs.approval_order', 'like', "%$keyword%");
    //         // })
    //         ->filterColumn('no_invoice', function ($query, $keyword) {
    //             $query->where('invoice.no_invoice', 'like', "%$keyword%");
    //         })
    //         ->orderColumn('tanggal_sampling_min', function ($query, $order) {
    //             $query->orderBy('tanggal_sampling_min', $order);
    //         })
    //         ->orderColumn('periode', function ($query, $order) {
    //             $query->orderBy('periode', $order);
    //         })
    //         ->make(true);
    // }

    public function index(Request $request)
    {
        $data = DailyQsd::with('invoice')->where('tanggal_sampling_min', 'like', '%' . $request->tanggal_sampling . '%')
        ->orderBy('tanggal_sampling_min', 'desc')
        ->orderBy('no_quotation', 'desc');

        return Datatables::of($data)
        ->addColumn('no_invoice', function ($data) {
            if(isset($data->invoice) && $data->invoice->count() > 0){
                if($data->kontrak == "C"){
                    if($data->invoice->where('periode', $data->periode)->count() == 0){
                        $dataInvoice = $data->invoice->where('periode', 'all');
                        $no_invoice = [];
                        foreach($dataInvoice as $cek){
                            $nilai_tagihan = $cek->nilai_tagihan;
                            $nominal = 0;
                            if($cek->recordPembayaran->count() > 0){
                                $nominal += $cek->recordPembayaran->sum('nilai_pembayaran');
                            }

                            if($cek->recordWithdraw->count() > 0){
                                $nominal += $cek->recordWithdraw->sum('nilai_pembayaran');
                            }
                            $status = $nominal >= $nilai_tagihan ? "(Lunas)" : "";
                            $no_invoice[] = $cek->no_invoice . " ". $status;
                        }
                        return implode(', ', $no_invoice);
                    } else {
                        $dataInvoice = $data->invoice->where('periode', $data->periode);
                        $no_invoice = [];
                        foreach($dataInvoice as $cek){
                            $nilai_tagihan = $cek->nilai_tagihan;
                            $nominal = 0;
                            if($cek->recordPembayaran->count() > 0){
                                $nominal += $cek->recordPembayaran->sum('nilai_pembayaran');
                            }

                            if($cek->recordWithdraw->count() > 0){
                                $nominal += $cek->recordWithdraw->sum('nilai_pembayaran');
                            }
                            $status = $nominal >= $nilai_tagihan ? "(Lunas)" : "";
                            $no_invoice[] = $cek->no_invoice . " ". $status;
                        }
                        return implode(', ', $no_invoice);
                    }
                } else {
                    $dataInvoice = $data->invoice;
                    $no_invoice = [];
                    foreach($dataInvoice as $cek){
                        $nilai_tagihan = $cek->nilai_tagihan;
                        $nominal = 0;
                        if($cek->recordPembayaran->count() > 0){
                            $nominal += $cek->recordPembayaran->sum('nilai_pembayaran');
                        }

                        if($cek->recordWithdraw->count() > 0){
                            $nominal += $cek->recordWithdraw->sum('nilai_pembayaran');
                        }
                        $status = $nominal >= $nilai_tagihan ? "(Lunas)" : "";
                        $no_invoice[] = $cek->no_invoice . " ". $status;
                    }
                    return implode(', ', $no_invoice);
                }
            }
        })
        ->filterColumn('no_invoice', function ($query, $keyword) {
            if($keyword == '-'){
                $query->whereDoesntHave('invoice');
            } else {
                $query->whereHas('invoice', function ($q) use ($keyword) {
                    $q->where('no_invoice', 'like', "%$keyword%");
                });
            }
        })
        ->filterColumn('tipe_quotation', function ($query, $keyword) {
            if($keyword == ""){
                $query->whereIn('kontrak', ["C", "N"]);
            } else {
                $query->where('kontrak', $keyword);
            }
        })
        ->filterColumn('periode', function ($query, $keyword) use ($request) {
            $bulanMap = [
                "januari" => "01", "februari" => "02", "maret" => "03", "april" => "04",
                "mei" => "05", "juni" => "06", "juli" => "07", "agustus" => "08",
                "september" => "09", "oktober" => "10", "november" => "11", "desember" => "12"
            ];
            $keyword = strtolower(trim($keyword));
            $bulan = $tahun = null;

            if (preg_match('/^(\D+)\s*(\d{2,4})?$/', $keyword, $m)) {
                // text (bulan nama/singkatan/angka) dan opsional tahun
                $bk = trim($m[1]);
                $tk = isset($m[2]) ? $m[2] : null;
                foreach ($bulanMap as $nama => $angka) {
                    if ($bk === $angka || strpos($nama, $bk) === 0 || substr($nama,0,3) === substr($bk,0,3)) {
                        $bulan = $angka;
                        break;
                    }
                }
                if (is_numeric($bk) && intval($bk) >= 1 && intval($bk) <= 12) $bulan = str_pad($bk,2,'0',STR_PAD_LEFT);
                // Tahun, 2 digit -> 20xx
                if ($tk) $tahun = strlen($tk)==2 ? ('20'.$tk) : $tk;
            }
            // Jika gabungan misal 'jan23' tanpa spasi
            if ((!$bulan || !$tahun) && preg_match('/([a-z]+)(\d{2,4})/i', $keyword, $m)) {
                foreach ($bulanMap as $nama => $angka) {
                    if (strpos($nama, $m[1]) === 0 || substr($nama,0,3) === substr($m[1],0,3)) {
                        $bulan = $angka;
                        break;
                    }
                }
                $tahun = strlen($m[2])==2 ? ('20'.$m[2]) : $m[2];
            }
            if ($bulan) {
                $tahun = $tahun ?: $request->tanggal_sampling;
                $query->where('periode', $tahun . '-' . $bulan);
            }
        })
        ->filterColumn('tanggal_sampling_min', function ($query, $keyword) use ($request) {
            $bulanMap = [
                "januari" => "01", "februari" => "02", "maret" => "03", "april" => "04",
                "mei" => "05", "juni" => "06", "juli" => "07", "agustus" => "08",
                "september" => "09", "oktober" => "10", "november" => "11", "desember" => "12"
            ];

            $keyword = strtolower(trim($keyword));
            $bulan = null;
            $tahun = null;
            $tanggal = null;

            // Strip all unwanted chars for splitting/regex
            $keyword = preg_replace('/[\s\-,.]+/', ' ', $keyword);
            $parts = explode(' ', $keyword);

            // Coba tangkap berbagai pola tanggal-bulan-tahun dll
            // Contoh: "16 des 2025", "1des", "02feb", "des 2023", "16des", "feb23", "16 desember"
            // 1. Gabungan angka-bulan (ddMMMyyyy atau dMMMyyyy atau ddMMMyy)
            if (preg_match('/^(\d{1,2})?([a-z]+)(\d{2,4})?$/i', str_replace(' ', '', $keyword), $m)) {
                // Jika ada angka di depan -> tanggal
                if (!empty($m[1])) {
                    $tanggal = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                }
                // Ambil bulan dari nama/singkatan
                $bk = $m[2];
                foreach ($bulanMap as $nama => $angka) {
                    if ($bk === $angka || strpos($nama, $bk) === 0 || substr($nama, 0, 3) === substr($bk, 0, 3)) {
                        $bulan = $angka;
                        break;
                    }
                }
                // Cek tahun jika ada
                if (!empty($m[3])) {
                    $tahun = strlen($m[3]) == 2 ? ('20' . $m[3]) : $m[3];
                }
            }

            // 2. Format: [dd] [bulan(nama/angka)] [yyyy|yy] (contoh: "16 desember 2025", "02 des")
            if (!$bulan && count($parts) > 0) {
                foreach ($parts as $ix => $val) {
                    // Cek bulan
                    foreach ($bulanMap as $nama => $angka) {
                        if ($val === $angka || strpos($nama, $val) === 0 || substr($nama, 0, 3) === substr($val, 0, 3)) {
                            $bulan = $angka;
                            break 2;
                        }
                    }
                }
            }
            // Tangkap tanggal (jika di depan atau di mana saja)
            foreach ($parts as $val) {
                if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31) {
                    if (strlen($val) <= 2) {
                        $tanggal = str_pad($val, 2, '0', STR_PAD_LEFT);
                        break;
                    } else {
                        // Tahun, bukan tanggal
                        if (strlen($val) == 4) $tahun = $val;
                        if (strlen($val) == 2 && intval($val) > 31) $tahun = '20' . $val;
                    }
                }
            }
            // Tangkap tahun (4 digit paling akhir, atau 2 digit jika > 31)
            foreach ($parts as $val) {
                if (preg_match('/^\d{4}$/', $val)) $tahun = $val;
                if (preg_match('/^\d{2}$/', $val) && intval($val) > 31) $tahun = '20' . $val;
            }
            if ($bulan) {
                $tahun = $tahun ?: $request->tanggal_sampling; // fallback tahun current jika tidak ada
                if ($tanggal) {
                    // Filter persis tanggal_sampling_min
                    $query->where('tanggal_sampling_min', "$tahun-$bulan-$tanggal");
                } else {
                    // Filter bulan+tahun
                    $query->whereRaw("DATE_FORMAT(tanggal_sampling_min, '%Y-%m') = ?", ["$tahun-$bulan"]);
                }
            }
        })
        ->make(true);
    }

    public function getTotalRevenue(Request $request)
    {
        $currentYear  = Carbon::now()->format('Y');
        $currentMonth = Carbon::now()->format('Y-m');
        $currentDate  = Carbon::now()->format('Y-m-d');

        // $currentYear  = $request->year;
        // $currentMonth = $currentYear . '-' . Carbon::now()->format('m');

        // Total Revenue Per Tahun
        $yearRevenue = DB::table('daily_qsd')
            ->whereRaw("YEAR(tanggal_sampling_min) = ?", [$currentYear])
            ->selectRaw('
                SUM(COALESCE(total_revenue, 0)) as total
            ')
            ->first();

        // Total Revenue Per Bulan
        $monthRevenue = DB::table('daily_qsd')
            ->whereRaw("DATE_FORMAT(tanggal_sampling_min, '%Y-%m') = ?", [$currentMonth])
            ->selectRaw('
                SUM(COALESCE(total_revenue, 0)) as total
            ')
            ->first();

        // Total Revenue Per Hari (Today)
        $dayRevenue = DB::table('daily_qsd')
            ->whereDate('tanggal_sampling_min', $currentDate)
            ->selectRaw('
                SUM(COALESCE(total_revenue, 0)) as total
            ')
            ->first();

        return response()->json([
            'year_revenue'  => $yearRevenue->total ?? 0,
            'month_revenue' => $monthRevenue->total ?? 0,
            'day_revenue'   => $dayRevenue->total ?? 0,
        ]);
    }
}
