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
    private function getWorkingDaysAgo($hariIni, $daysAgo, $kalenderPerusahaanLengkap)
    {
        $index = array_search($hariIni, $kalenderPerusahaanLengkap);
        if ($index !== false) {
            $targetIndex = $index - $daysAgo;
            if ($targetIndex >= 0 && isset($kalenderPerusahaanLengkap[$targetIndex])) {
                return $kalenderPerusahaanLengkap[$targetIndex];
            }
        }
        
        // Fallback jika tidak ditemukan di kalender, asumsikan 5 hari kerja per minggu
        return Carbon::parse($hariIni)->subWeekdays($daysAgo)->format('Y-m-d');
    }

    public function index(Request $request)
    {
        try {
            $hariIni = $request->input('tanggal', date('Y-m-d'));
            $tahunIni = $request->input('tahun', date('Y', strtotime($hariIni)));
            
            // Fetch kalender
            $dbKalenderTahun = RekapLiburKalender::where('tahun', $tahunIni)
                ->where('is_active', 1)
                ->first();

            $kalenderPerusahaanLengkap = [];
            if ($dbKalenderTahun) {
                $kalenderBulan = json_decode($dbKalenderTahun->tanggal, true);
                if (is_array($kalenderBulan)) {
                    foreach ($kalenderBulan as $bulan => $dates) {
                        $kalenderPerusahaanLengkap = array_merge($kalenderPerusahaanLengkap, $dates);
                    }
                }
            }
            sort($kalenderPerusahaanLengkap);

            // 1. Target Harian: LHP dihitung 10 hari kerja dari hari H Sampling (tanggal terima/sampling)
            $targetDate10DaysAgo = $this->getWorkingDaysAgo($hariIni, 10, $kalenderPerusahaanLengkap);

            $targetHarianData = OrderDetail::whereDate('tanggal_terima', $targetDate10DaysAgo)
                ->where('is_active', true)
                ->get(['no_sampel', 'status']);
            
            $targetHarianCount = $targetHarianData->count();

            // 2. Jumlah selesai perhari: LHP yang sudah diselesaikan/dirilis (Status 2 atau 3)
            $selesaiPerhariCount = $targetHarianData->whereIn('status', [2, 3])->count();
            
            // 3. Selesai Tepat Waktu (%)
            $selesaiTepatWaktu = $targetHarianCount > 0 
                ? round(($selesaiPerhariCount / $targetHarianCount) * 100, 1) 
                : 0;

            // 4. Belum selesai: LHP yang masih proses release dari Target Harian (Status 0 atau 1)
            $belumSelesaiPerhariCount = $targetHarianData->whereIn('status', [0, 1])->count();

            // 5. Global Pertahun
            $orderTahunIni = OrderDetail::whereYear('tanggal_terima', $tahunIni)
                ->where('is_active', true)
                ->get(['no_sampel', 'status']);
            
            $belumSelesaiGlobalTahun = $orderTahunIni->whereIn('status', [0, 1])->count();
            $selesaiGlobalTahun = $orderTahunIni->whereIn('status', [2, 3])->count();
            $totalGlobalTahun = $orderTahunIni->count();

            // 6. Persentase penyelesaian global (%)
            $persenGlobal = $totalGlobalTahun > 0 
                ? round(($selesaiGlobalTahun / $totalGlobalTahun) * 100, 1) 
                : 0;

            // 7. Chart Data (Bar chart penyelesaian LHP per bulan)
            $chartData = [];
            for ($i = 1; $i <= 12; $i++) {
                $monthStr = str_pad($i, 2, '0', STR_PAD_LEFT);
                $ordersInMonth = OrderDetail::whereYear('tanggal_terima', $tahunIni)
                    ->whereMonth('tanggal_terima', $monthStr)
                    ->whereIn('status', [2, 3])
                    ->where('is_active', true)
                    ->count();
                
                $chartData[] = [
                    'label' => Carbon::create()->month($i)->translatedFormat('M'),
                    'value' => $ordersInMonth
                ];
            }

            $summaryCard = [
                [
                    "title" => "Target Harian (10 Hari Lalu)",
                    "value" => $targetHarianCount,
                    "icon" => "🎯",
                    "accent" => "#F59E0B",
                    "lightBackground" => "#FEF3C7",
                    "darkBackground" => "#452711",
                    "lightIconBackground" => "#FDE68A",
                    "darkIconBackground" => "#78350F"
                ],
                [
                    "title" => "Selesai (Dari Target Harian)",
                    "value" => $selesaiPerhariCount,
                    "icon" => "✅",
                    "accent" => "#10B981",
                    "lightBackground" => "#D1FAE5",
                    "darkBackground" => "#064E3B",
                    "lightIconBackground" => "#A7F3D0",
                    "darkIconBackground" => "#065F46"
                ],
                [
                    "title" => "Belum Selesai (Sisa Target Harian)",
                    "value" => $belumSelesaiPerhariCount,
                    "icon" => "⏳",
                    "accent" => "#EF4444",
                    "lightBackground" => "#FEE2E2",
                    "darkBackground" => "#7F1D1D",
                    "lightIconBackground" => "#FECACA",
                    "darkIconBackground" => "#991B1B"
                ],
                [
                    "title" => "LHP Belum Rilis Global ($tahunIni)",
                    "value" => $belumSelesaiGlobalTahun,
                    "icon" => "🌍",
                    "accent" => "#6366F1",
                    "lightBackground" => "#E0E7FF",
                    "darkBackground" => "#312E81",
                    "lightIconBackground" => "#C7D2FE",
                    "darkIconBackground" => "#3730A3"
                ]
            ];

            $avgCards = [
                [
                    "title" => "Selesai Tepat Waktu (Harian)",
                    "value" => $selesaiTepatWaktu . " %",
                    "icon" => "⏱️",
                    "accent" => "#3B82F6",
                    "lightBackground" => "#DBEAFE",
                    "darkBackground" => "#1E3A8A"
                ],
                [
                    "title" => "Penyelesaian Global Tahunan",
                    "value" => $persenGlobal . " %",
                    "icon" => "📈",
                    "accent" => "#8B5CF6",
                    "lightBackground" => "#EDE9FE",
                    "darkBackground" => "#4C1D95"
                ]
            ];

            return response()->json([
                'status' => 'success',
                'summaryCard' => $summaryCard,
                // 'avgCards' => $avgCards,
                'chartData' => $chartData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}