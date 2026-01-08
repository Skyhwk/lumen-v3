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
    /**
     * Mapping bulan Indonesia ke format numerik
     */
    private const BULAN_MAP = [
        "januari" => "01", "februari" => "02", "maret" => "03", "april" => "04",
        "mei" => "05", "juni" => "06", "juli" => "07", "agustus" => "08",
        "september" => "09", "oktober" => "10", "november" => "11", "desember" => "12"
    ];

    public function index(Request $request)
    {
        // Query dasar
        $data = DailyQsd::whereYear('tanggal_kelompok', $request->tanggal_sampling);
        
        // Apply cut off filter jika ada
        if ($request->cut_off != "all") {
            $data = $data->whereMonth('tanggal_kelompok', $request->cut_off);
        }
        
        // Ambil invoice yang perlu di-exclude
        $excludeInv = $this->getExcludedInvoices();

        // Tentukan halaman (untuk pagination logic)
        $page = $request->start > 29 ? "lanjut" : "awal";

        return Datatables::of($data)
        ->filterColumn('no_invoice', function ($query, $keyword) {
            if($keyword == '-'){
                $query->whereNull('no_invoice');
            } else {
                $query->where('no_invoice', 'like', "%$keyword%");
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
            $this->applyPeriodeFilter($query, $keyword, $request);
        })
        ->filterColumn('tanggal_pembayaran', function ($query, $keyword) use ($request) {
            $this->applyTanggalFilter($query, $keyword, 'tanggal_pembayaran', $request);
        })
        ->filterColumn('tanggal_sampling_min', function ($query, $keyword) use ($request) {
            $this->applyTanggalFilter($query, $keyword, 'tanggal_sampling_min', $request);
        })
        ->filterColumn('no_order', function ($query, $keyword) {
            $query->where('no_order', 'like', "%$keyword%")->orderBy('no_order', 'asc');
        })
        ->filterColumn('status_sampling', function ($query, $keyword) {
            if( $keyword != 'Gabungan') 
            {
                $query->where('status_sampling', "$keyword");
            } else {
                $query->where('status_sampling', 'like', "%,%");
            }
        })
        ->with([
            'sumRevenue' => function ($query) {
                return $query->sum('total_revenue');
            },
            'sumPpn' => function ($query) {
                return $query->sum('total_ppn');
            },
            'sumPph' => function ($query) {
                return $query->sum('total_pph');
            },
            'sumDiscount' => function ($query) {
                return $query->sum('total_discount');
            },
            'sumQt' => function ($query) {
                return $query->sum('biaya_akhir');
            },
            'revenueInvoice' => function ($query) use ($excludeInv) {
                return $this->calculateSumWithSpecialInvoice($query, $excludeInv, 'revenue_invoice');
            },
            'sumInv' => function ($query) use ($excludeInv) {
                return $this->calculateSumWithSpecialInvoice($query, $excludeInv, 'nilai_invoice');
            },
            'sumPembayaran' => function ($query) use ($excludeInv) {
                return $this->calculateSumWithSpecialInvoice($query, $excludeInv, 'nilai_pembayaran');
            },
            'sumPengurangan' => function ($query) use ($excludeInv) {
                return $this->calculateSumWithSpecialInvoice($query, $excludeInv, 'nilai_pengurangan');
            },
            'page' => function () use ($page) {
                return $page;
            },
        ])
        ->order(function ($query) {
            $this->applyOrdering($query);
        })
        ->make(true);
    }

    /**
     * Mendapatkan daftar invoice yang harus di-exclude (special invoice)
     * Invoice dengan lebih dari 1 quotation yang berbeda
     */
    private function getExcludedInvoices()
    {
        return Invoice::where('is_active', 1)
            ->whereIn('no_invoice', function ($q) {
                $q->select('no_invoice')
                    ->from('invoice')
                    ->where('is_active', 1)
                    ->groupBy('no_invoice')
                    ->havingRaw('COUNT(*) > 1')
                    ->havingRaw('COUNT(DISTINCT no_quotation) > 1');
            })
            ->groupBy('no_invoice')
            ->pluck('no_invoice');
    }

    /**
     * Menghitung sum dengan logic special untuk invoice tertentu
     * @param $query Query builder
     * @param $excludeInv Array invoice yang di-exclude
     * @param $column Nama kolom yang akan di-sum
     */
    private function calculateSumWithSpecialInvoice($query, $excludeInv, $column)
    {
        $base = clone $query;
        $base->reorder();
        
        // Hitung normal invoice
        $normalSum = (clone $base)
        ->whereNotIn(
            DB::raw("TRIM(SUBSTRING_INDEX(no_invoice, ' ', 1))"),
            $excludeInv
        )
        ->sum($column);
            
        $specialSum = (clone $base)
            ->whereIn(
                DB::raw("TRIM(SUBSTRING_INDEX(no_invoice, ' ', 1))"),
                $excludeInv
            )
            ->groupBy('no_invoice')
            ->selectRaw("MAX($column) as nilai")
            ->pluck('nilai')
            ->sum();

        return ($normalSum ?: 0) + ($specialSum ?: 0);
    }

    /**
     * Parse keyword periode (bulan-tahun) dari input user
     * Contoh: "januari 2025", "jan25", "01 2025", dll
     */
    private function parsePeriode($keyword, $defaultYear)
    {
        $keyword = strtolower(trim($keyword));
        $bulan = $tahun = null;

        // Pattern: text bulan + optional tahun
        if (preg_match('/^(\D+)\s*(\d{2,4})?$/', $keyword, $m)) {
            $bk = trim($m[1]);
            $tk = isset($m[2]) ? $m[2] : null;
            
            // Cari bulan
            foreach (self::BULAN_MAP as $nama => $angka) {
                if ($bk === $angka || strpos($nama, $bk) === 0 || substr($nama, 0, 3) === substr($bk, 0, 3)) {
                    $bulan = $angka;
                    break;
                }
            }
            
            // Jika input angka 1-12
            if (is_numeric($bk) && intval($bk) >= 1 && intval($bk) <= 12) {
                $bulan = str_pad($bk, 2, '0', STR_PAD_LEFT);
            }
            
            // Parse tahun (2 digit -> 20xx)
            if ($tk) {
                $tahun = strlen($tk) == 2 ? ('20' . $tk) : $tk;
            }
        }

        // Pattern gabungan tanpa spasi: jan23, feb2025
        if ((!$bulan || !$tahun) && preg_match('/([a-z]+)(\d{2,4})/i', $keyword, $m)) {
            foreach (self::BULAN_MAP as $nama => $angka) {
                if (strpos($nama, $m[1]) === 0 || substr($nama, 0, 3) === substr($m[1], 0, 3)) {
                    $bulan = $angka;
                    break;
                }
            }
            $tahun = strlen($m[2]) == 2 ? ('20' . $m[2]) : $m[2];
        }

        if ($bulan) {
            $tahun = $tahun ?: $defaultYear;
            return ['bulan' => $bulan, 'tahun' => $tahun];
        }

        return null;
    }

    /**
     * Parse keyword tanggal lengkap dari input user
     * Contoh: "16 des 2025", "1des", "02feb", "16 desember", dll
     */
    private function parseTanggal($keyword, $defaultYear)
    {
        $keyword = strtolower(trim($keyword));
        $bulan = $tahun = $tanggal = null;

        // Normalize spacing
        $keyword = preg_replace('/[\s\-,.]+/', ' ', $keyword);
        $parts = explode(' ', $keyword);

        // Pattern 1: Gabungan angka-bulan (ddMMMyyyy atau dMMMyyyy atau ddMMMyy)
        if (preg_match('/^(\d{1,2})?([a-z]+)(\d{2,4})?$/i', str_replace(' ', '', $keyword), $m)) {
            if (!empty($m[1])) {
                $tanggal = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            }
            
            $bk = $m[2];
            foreach (self::BULAN_MAP as $nama => $angka) {
                if ($bk === $angka || strpos($nama, $bk) === 0 || substr($nama, 0, 3) === substr($bk, 0, 3)) {
                    $bulan = $angka;
                    break;
                }
            }
            
            if (!empty($m[3])) {
                $tahun = strlen($m[3]) == 2 ? ('20' . $m[3]) : $m[3];
            }
        }

        // Pattern 2: Format terpisah [dd] [bulan] [yyyy]
        if (!$bulan && count($parts) > 0) {
            foreach ($parts as $val) {
                foreach (self::BULAN_MAP as $nama => $angka) {
                    if ($val === $angka || strpos($nama, $val) === 0 || substr($nama, 0, 3) === substr($val, 0, 3)) {
                        $bulan = $angka;
                        break 2;
                    }
                }
            }
        }

        // Cari tanggal (1-31)
        foreach ($parts as $val) {
            if (is_numeric($val) && intval($val) >= 1 && intval($val) <= 31 && strlen($val) <= 2) {
                $tanggal = str_pad($val, 2, '0', STR_PAD_LEFT);
                break;
            }
        }

        // Cari tahun (4 digit atau 2 digit > 31)
        foreach ($parts as $val) {
            if (preg_match('/^\d{4}$/', $val)) {
                $tahun = $val;
            } elseif (preg_match('/^\d{2}$/', $val) && intval($val) > 31) {
                $tahun = '20' . $val;
            }
        }

        if ($bulan) {
            $tahun = $tahun ?: $defaultYear;
            return [
                'tanggal' => $tanggal,
                'bulan' => $bulan,
                'tahun' => $tahun
            ];
        }

        return null;
    }

    /**
     * Apply filter untuk kolom periode
     */
    private function applyPeriodeFilter($query, $keyword, Request $request)
    {
        $parsed = $this->parsePeriode($keyword, $request->tanggal_sampling);
        if ($parsed) {
            $query->where('periode', $parsed['tahun'] . '-' . $parsed['bulan']);
        }
    }

    /**
     * Apply filter untuk kolom tanggal (tanggal_pembayaran atau tanggal_sampling_min)
     */
    private function applyTanggalFilter($query, $keyword, $columnName, Request $request)
    {
        $parsed = $this->parseTanggal($keyword, $request->tanggal_sampling);
        if ($parsed) {
            if ($parsed['tanggal']) {
                // Filter tanggal lengkap
                $date = "{$parsed['tahun']}-{$parsed['bulan']}-{$parsed['tanggal']}";
                $query->where($columnName, $date);
            } else {
                // Filter bulan+tahun saja
                $query->whereRaw("DATE_FORMAT($columnName, '%Y-%m') = ?", ["{$parsed['tahun']}-{$parsed['bulan']}"]);
            }
        }
    }

    /**
     * Apply ordering berdasarkan request dari datatables
     */
    private function applyOrdering($query)
    {
        if (request()->has('order') && isset(request()->input('order')[0])) {
            $order = request()->input('order')[0];
            $columnName = $order['name'] ?? null;
            $dir = $order['dir'] ?? 'asc';
            
            if ($columnName) {
                $query->orderBy($columnName, $dir);
            }
        } else {
            // Default ordering
            $query->orderBy('tanggal_sampling_min', 'desc')
                  ->orderBy('no_order', 'asc');
        }
    }
}
