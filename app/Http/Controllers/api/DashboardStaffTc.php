<?php
namespace App\Http\Controllers\api;

use App\Models\RekapLiburKalender;
use App\Models\OrderDetail;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as FacadesLog;

class DashboardStaffTc extends Controller
{
    private function getWorkingDaysAgo($tanggal, $daysAgo, $kalenderLengkap)
    {
        $index = array_search($tanggal, $kalenderLengkap);
        if ($index !== false) {
            $targetIndex = $index - $daysAgo;
            if ($targetIndex >= 0 && isset($kalenderLengkap[$targetIndex])) {
                return $kalenderLengkap[$targetIndex];
            }
        }
        return Carbon::parse($tanggal)->subWeekdays($daysAgo)->format('Y-m-d');
    }

    private function hitungHariKerja($dari, $sampai, $kalenderLengkap)
    {
        $dariStr = Carbon::parse($dari)->format('Y-m-d');
        $sampaiStr = Carbon::parse($sampai)->format('Y-m-d');

        $indexDari = array_search($dariStr, $kalenderLengkap);
        $indexSampai = array_search($sampaiStr, $kalenderLengkap);

        if ($indexDari !== false && $indexSampai !== false) {
            return abs($indexSampai - $indexDari);
        }

        return Carbon::parse($dari)->diffInDays(Carbon::parse($sampai));
    }

    private function formatDurasi($totalMenit)
    {
        if ($totalMenit <= 0) return '-';

        $hari = floor($totalMenit / (24 * 60));
        $sisa = $totalMenit % (24 * 60);
        $jam  = floor($sisa / 60);
        $menit = $sisa % 60;

        $parts = [];
        if ($hari > 0) $parts[] = $hari . ' Hari';
        if ($jam > 0) $parts[] = $jam . ' Jam';
        if ($menit > 0) $parts[] = $menit . ' Menit';

        return implode(' ', $parts) ?: '0 Menit';
    }

    public function index(Request $request)
    {
        try {
            $section = $request->input('section', 'all');
            $tanggalFilter = $request->input('tanggal', date('Y-m-d'));
            $tahunFilter = $request->input('tahun', date('Y', strtotime($tanggalFilter)));
            $bulanFilter = $request->input('bulan');

            $dbKalender = RekapLiburKalender::where('tahun', $tahunFilter)
                ->where('is_active', 1)
                ->first();

            $kalenderLengkap = [];
            if ($dbKalender) {
                $kalenderBulan = json_decode($dbKalender->tanggal, true);
                if (is_array($kalenderBulan)) {
                    foreach ($kalenderBulan as $bulan => $dates) {
                        $kalenderLengkap = array_merge($kalenderLengkap, $dates);
                    }
                }
            }
            sort($kalenderLengkap);

            $response = ['status' => true];

            if ($section === 'harian' || $section === 'all') {
                $targetDate = $this->getWorkingDaysAgo($tanggalFilter, 10, $kalenderLengkap);
                $targetHarianData = OrderDetail::whereDate('tanggal_terima', $targetDate)
                    ->where('is_active', true)
                    ->get(['id', 'no_sampel', 'status', 'tanggal_terima', 'approved_at']);

                $targetHarianCount = $targetHarianData->count();
                $selesaiPerhariCount = $targetHarianData->whereIn('status', [2, 3])->count();
                $selesaiTepatWaktu = $targetHarianCount > 0
                    ? round(($selesaiPerhariCount / $targetHarianCount) * 100, 1)
                    : 0;
                $belumSelesaiPerhariCount = $targetHarianData->whereIn('status', [0, 1])->count();

                $response['summaryCard'] = [
                    [
                        "title" => "Target Harian",
                        "subtitle" => "10 HK dari " . Carbon::parse($targetDate)->format('d M Y'),
                        "value" => $targetHarianCount,
                        "icon" => "🎯",
                        "accent" => "#F59E0B",
                        "lightBackground" => "#FEF3C7",
                        "darkBackground" => "#452711",
                        "lightIconBackground" => "#FDE68A",
                        "darkIconBackground" => "#78350F"
                    ],
                    [
                        "title" => "Selesai Perhari",
                        "subtitle" => "Dari target harian",
                        "value" => $selesaiPerhariCount,
                        "icon" => "✅",
                        "accent" => "#10B981",
                        "lightBackground" => "#D1FAE5",
                        "darkBackground" => "#064E3B",
                        "lightIconBackground" => "#A7F3D0",
                        "darkIconBackground" => "#065F46"
                    ],
                    [
                        "title" => "Selesai Tepat Waktu",
                        "subtitle" => "Presentase dari target",
                        "value" => $selesaiTepatWaktu . " %",
                        "icon" => "⏳",
                        "accent" => "#3B82F6",
                        "lightBackground" => "#DBEAFE",
                        "darkBackground" => "#1E3A8A",
                        "lightIconBackground" => "#BFDBFE",
                        "darkIconBackground" => "#1D4ED8"
                    ],
                    [
                        "title" => "Belum Selesai",
                        "subtitle" => "Dari target harian",
                        "value" => $belumSelesaiPerhariCount,
                        "icon" => "⚠️",
                        "accent" => "#EF4444",
                        "lightBackground" => "#FEE2E2",
                        "darkBackground" => "#7F1D1D",
                        "lightIconBackground" => "#FECACA",
                        "darkIconBackground" => "#B91C1C"
                    ]
                ];
            }

            if ($section === 'global' || $section === 'all') {
                $orderTahunIni = OrderDetail::whereYear('tanggal_terima', $tahunFilter)
                    ->where('is_active', true)
                    ->get(['no_sampel', 'status', 'tanggal_terima', 'approved_at']);

                $belumSelesaiGlobal = $orderTahunIni->whereIn('status', [0, 1, 2])->count();
                $selesaiGlobal = $orderTahunIni->where('status', 3)->count();
                $totalGlobal = $belumSelesaiGlobal + $selesaiGlobal;
                $persenGlobal = $totalGlobal > 0
                    ? round(($selesaiGlobal / $totalGlobal) * 100, 1)
                    : 0;

                $orderSelesaiTahun = $orderTahunIni->where('status', 3)
                    ->filter(function ($item) {
                        return !empty($item->tanggal_terima) && !empty($item->approved_at);
                    });

                $maxHariKeterlambatan = 0;
                foreach ($orderSelesaiTahun as $order) {
                    try {
                        $tglTerima = Carbon::parse($order->tanggal_terima);
                        $tglApproved = Carbon::parse($order->approved_at);
                        $hariKerja = $this->hitungHariKerja($tglTerima, $tglApproved, $kalenderLengkap);
                        if ($hariKerja > $maxHariKeterlambatan) {
                            $maxHariKeterlambatan = $hariKerja;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                $response['globalStats'] = [
                    [
                        "title" => "Belum Rilis ($tahunFilter)",
                        "value" => $belumSelesaiGlobal,
                        "icon" => "🌍",
                        "accent" => "#6366F1",
                        "lightBackground" => "#E0E7FF",
                        "darkBackground" => "#312E81",
                        "lightIconBackground" => "#C7D2FE",
                        "darkIconBackground" => "#3730A3"
                    ],
                    [
                        "title" => "Penyelesaian ($tahunFilter)",
                        "value" => $persenGlobal . "%",
                        "icon" => "📈",
                        "accent" => "#8B5CF6",
                        "lightBackground" => "#EDE9FE",
                        "darkBackground" => "#4C1D95",
                        "lightIconBackground" => "#DDD6FE",
                        "darkIconBackground" => "#5B21B6"
                    ],
                    [
                        "title" => "Maks Hari Rilis ($tahunFilter)",
                        "value" => $maxHariKeterlambatan . " Hari",
                        "icon" => "📅",
                        "accent" => "#EC4899",
                        "lightBackground" => "#FCE7F3",
                        "darkBackground" => "#831843",
                        "lightIconBackground" => "#FBCFE8",
                        "darkIconBackground" => "#9D174D"
                    ]
                ];
            }

            if ($section === 'durasi_table' || $section === 'durasi' || $section === 'all') {
                $durasiData = [];
                $filterBulan = $bulanFilter ?: date('Y-m', strtotime($tanggalFilter));

                $orderBulan = OrderDetail::where('is_active', true)
                    ->where('status', 3)
                    ->whereNotNull('approved_at')
                    ->whereNotNull('approved_by')
                    ->whereRaw("DATE_FORMAT(tanggal_terima, '%Y-%m') = ?", [$filterBulan])
                    ->get(['no_sampel', 'cfr', 'tanggal_terima', 'approved_at', 'approved_by', 'created_at', 'updated_at']);

                if ($orderBulan->count() > 0) {
                    $groupedByStaff = $orderBulan->groupBy('approved_by');

                    foreach ($groupedByStaff as $staffName => $orders) {
                        $totalMenit = 0;
                        $countValidLhp = 0;

                        $groupedByLhp = $orders->groupBy('cfr');

                        foreach ($groupedByLhp as $cfr => $lhpOrders) {
                            $ord = $lhpOrders->first();
                            try {
                                $tglTerima = Carbon::parse($ord->tanggal_terima);
                                $tglApproved = Carbon::parse($ord->approved_at);
                                $diffMenit = $tglTerima->diffInMinutes($tglApproved);
                                $totalMenit += $diffMenit;
                                $countValidLhp++;
                            } catch (\Exception $e) {
                                continue;
                            }
                        }

                        if ($countValidLhp > 0) {
                            $avgMenit = round($totalMenit / $countValidLhp);
                            $durasiData[] = [
                                'staff' => $staffName,
                                'jumlah_lhp' => $countValidLhp,
                                'rata_rata_total' => $this->formatDurasi($avgMenit),
                            ];
                        }
                    }
                    
                    usort($durasiData, function ($a, $b) {
                        return $b['jumlah_lhp'] <=> $a['jumlah_lhp'];
                    });
                }
                $response['durasiData'] = $durasiData;
            }

            if ($section === 'durasi_chart' || $section === 'durasi' || $section === 'all') {

                $chartData = [];
                $orderTahunChart = OrderDetail::where('is_active', true)
                    ->where('status', 3)
                    ->whereNotNull('approved_at')
                    ->whereYear('approved_at', $tahunFilter)
                    ->get(['cfr', 'approved_at']);

                $totalLhpTahunIni = $orderTahunChart->unique('cfr')->count();
                $groupedByMonth = $orderTahunChart->groupBy(function($item) {
                    return Carbon::parse($item->approved_at)->format('n');
                });

                for ($m = 1; $m <= 12; $m++) {
                    $monthOrders = $groupedByMonth->get($m);
                    $countLhp = $monthOrders ? $monthOrders->unique('cfr')->count() : 0;
                    $percentage = $totalLhpTahunIni > 0 ? round(($countLhp / $totalLhpTahunIni) * 100, 1) : 0;
                    
                    $chartData[] = [
                        'bulan_angka' => $m,
                        'nama_bulan' => Carbon::create()->month($m)->locale('id')->monthName,
                        'jumlah_lhp' => $countLhp,
                        'persentase' => $percentage
                    ];
                }
                $response['chartData'] = $chartData;
            }

            return response()->json($response, 200);

        } catch (\Exception $e) {
            FacadesLog::error('DashboardStaffTc::index error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function detailTepatWaktu(Request $request)
    {
        try {
            $tanggalFilter = $request->input('tanggal', date('Y-m-d'));
            $tahunFilter = $request->input('tahun', date('Y', strtotime($tanggalFilter)));
            $limit = $request->input('limit', 10);
            $page = $request->input('page', 1);

            $dbKalender = RekapLiburKalender::where('tahun', $tahunFilter)
                ->where('is_active', 1)
                ->first();

            $kalenderLengkap = [];
            if ($dbKalender) {
                $kalenderBulan = json_decode($dbKalender->tanggal, true);
                if (is_array($kalenderBulan)) {
                    foreach ($kalenderBulan as $bulan => $dates) {
                        $kalenderLengkap = array_merge($kalenderLengkap, $dates);
                    }
                }
            }
            sort($kalenderLengkap);

            $targetDate = $this->getWorkingDaysAgo($tanggalFilter, 10, $kalenderLengkap);

            $query = OrderDetail::whereDate('tanggal_terima', $targetDate)
                ->where('is_active', true)
                ->select(['id', 'no_sampel', 'cfr', 'status', 'tanggal_terima', 'approved_at']);

            $total = $query->count();
            $data = $query->skip(($page - 1) * $limit)->take($limit)->get();

            $data->transform(function ($item) use ($kalenderLengkap) {
                $selisihWaktu = '-';
                if ($item->approved_at) {
                    $hari = $this->hitungHariKerja($item->tanggal_terima, $item->approved_at, $kalenderLengkap);
                    $selisihWaktu = $hari . ' Hari Kerja';
                }

                $statusText = 'Proses';
                if ($item->status == 2) $statusText = 'Approved TQC';
                if ($item->status == 3) $statusText = 'Rilis LHP';

                return [
                    'no_sampel' => $item->no_sampel,
                    'cfr' => $item->cfr,
                    'tanggal_terima' => Carbon::parse($item->tanggal_terima)->format('Y-m-d H:i:s'),
                    'approved_at' => $item->approved_at ? Carbon::parse($item->approved_at)->format('Y-m-d H:i:s') : '-',
                    'selisih_waktu' => $selisihWaktu,
                    'status_text' => $statusText
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'last_page' => ceil($total / $limit)
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function detailDurasiPengerjaan(Request $request)
    {
        try {
            $bulanFilter = $request->input('bulan', date('Y-m'));
            $staff = $request->input('staff');
            $limit = $request->input('limit', 10);
            $page = $request->input('page', 1);

            $query = OrderDetail::where('is_active', true)
                ->where('status', 3)
                ->whereNotNull('approved_at')
                ->whereRaw("DATE_FORMAT(approved_at, '%Y-%m') = ?", [$bulanFilter])
                ->select(['id', 'no_sampel', 'cfr', 'tanggal_terima', 'approved_at', 'approved_by'])
                ->orderBy('cfr', 'asc')
                ->orderBy('id', 'asc');

            if ($staff) {
                $query->where('approved_by', $staff);
            }

            $total = $query->count();
            $data = $query->skip(($page - 1) * $limit)->take($limit)->get();

            $cfrs = $data->pluck('cfr')->unique()->filter()->toArray();
            $avgDurations = [];
            if (!empty($cfrs)) {
                $allSamples = OrderDetail::whereIn('cfr', $cfrs)
                    ->where('is_active', true)
                    ->whereNotNull('approved_at')
                    ->whereNotNull('tanggal_terima')
                    ->get(['cfr', 'tanggal_terima', 'approved_at']);

                foreach ($allSamples->groupBy('cfr') as $cfr => $samples) {
                    $totalMenit = 0;
                    $count = 0;
                    foreach ($samples as $s) {
                        try {
                            $tTerima = \Carbon\Carbon::parse($s->tanggal_terima);
                            $tApprove = \Carbon\Carbon::parse($s->approved_at);
                            $totalMenit += $tTerima->diffInMinutes($tApprove);
                            $count++;
                        } catch (\Exception $e) {}
                    }
                    if ($count > 0) {
                        $avgDurations[$cfr] = $this->formatDurasi(round($totalMenit / $count));
                    }
                }
            }

            $data->transform(function ($item) use ($avgDurations) {
                $durasiText = '-';
                if ($item->approved_at && $item->tanggal_terima) {
                    $tglTerima = \Carbon\Carbon::parse($item->tanggal_terima);
                    $tglApproved = \Carbon\Carbon::parse($item->approved_at);
                    $diffMenit = $tglTerima->diffInMinutes($tglApproved);
                    $durasiText = $this->formatDurasi($diffMenit);
                }

                $rataRataLhp = $item->cfr ? ($avgDurations[$item->cfr] ?? $durasiText) : $durasiText;

                return [
                    'staff' => $item->approved_by,
                    'no_sampel' => $item->no_sampel,
                    'cfr' => $item->cfr,
                    'tanggal_terima' => \Carbon\Carbon::parse($item->tanggal_terima)->format('Y-m-d H:i:s'),
                    'approved_at' => \Carbon\Carbon::parse($item->approved_at)->format('Y-m-d H:i:s'),
                    'durasi' => $durasiText,
                    'durasi_rata_lhp' => $rataRataLhp
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'last_page' => ceil($total / $limit)
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}