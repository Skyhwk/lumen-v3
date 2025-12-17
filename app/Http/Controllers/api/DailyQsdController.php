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
    public function index(Request $request)
    {
        $data = DailyQsd::with('invoice')->where('tanggal_sampling_min', 'like', '%' . $request->tanggal_sampling . '%');
        if($request->cut_off == "today"){
            $data = $data->where('tanggal_sampling_min', '<=',Carbon::now()->format('Y-m-d'));
        }
        $data = $data->orderBy('tanggal_sampling_min', 'desc')
        ->orderBy('no_order', 'asc');

        $page = "awal";
        if($request->start > 29){
            $page = "lanjut";
        }

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
        ->filterColumn('no_order', function ($query, $keyword) {
            $query->where('no_order', 'like', "%$keyword%")->orderBy('no_order', 'asc');
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
            'page' => function () use ($page) {
                return $page;
            },
        ])
        
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
