<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Datatables;

use App\Models\ForecastSP;
use App\Models\Jadwal;
use App\Models\MasterKaryawan;
use App\Services\GetBawahan;
use App\Services\GetDepartmentHierarchy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DataForecastController extends Controller
{
    public function index(Request $request)
    {
        $tahun = $request->year;

        $hierarchy = new GetDepartmentHierarchy();
        $salesHierarchy = $hierarchy->getByDepartment('sales', 'tree');

        $forecastPerSales = $this->getForecastPerSales($tahun);

        $includedSales = [];

        $finalData = collect($salesHierarchy)
            ->flatMap(function ($root) use ($forecastPerSales, &$includedSales) {
                return $this->flattenWithRootPromotion(
                    $root,
                    $forecastPerSales,
                    $includedSales,
                    1,
                    true // root context
                );
            })
            ->values();

        return response()->json([
            'status' => true,
            'data' => $finalData
        ], 200);
    }

    public function indexData(Request $request){
        // Menggunakan query() agar efisien
        $data = ForecastSP::whereYear('tanggal_sampling_min', $request->year);

        // Paksa order ke kolom yang PASTI ADA, misalnya tanggal_sampling_min
        return Datatables::of($data)
            ->make(true);
    }

    private function getForecastPerSales(int $tahun)
    {
        $forecasts = ForecastSP::whereYear('tanggal_sampling_min', $tahun)->get();

        $monthNames = ['', 'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agt','Sep','Okt','Nov','Des'];

        $result = [];

        foreach ($forecasts as $forecast) {
            $sid = $forecast->sales_id;

            if (!isset($result[$sid])) {
                $result[$sid] = [
                    'total_tahun' => 0,
                    'periode' => $this->getEmptyOrder(),
                ];
            }

            $bulan = $monthNames[intval(explode('-', $forecast->tanggal_sampling_min)[1])];

            $result[$sid]['periode'][$bulan] += $forecast->revenue_forecast;
            $result[$sid]['total_tahun'] += $forecast->revenue_forecast;
        }

        return $result;
    }

    private function flattenWithRootPromotion(
        array $node,
        array $forecastPerSales,
        array &$includedSales,
        int $level,
        bool $isRootContext = false
    ): array {

        $result = [];
        $salesId = $node['id'];

        $hasForecast = isset($forecastPerSales[$salesId]);
        // $hasForecast = true;
        $alreadyIncluded = in_array($salesId, $includedSales);

        // =============================
        // CASE 1 — node punya forecast → include
        // =============================
        if ($hasForecast && !$alreadyIncluded) {

            $includedSales[] = $salesId;

            $result[] = [
                'sales_id' => $salesId,
                'nama_sales' => $node['nama_lengkap'] ?? null,
                'employee_level' => $level,
                'grade' => $node['grade'],
                'total_tahun' => $forecastPerSales[$salesId]['total_tahun'] ?? 0,
                'is_active' => $node['is_active'] ?? false,
                'is_root' => $isRootContext,
                'periode' => $forecastPerSales[$salesId]['periode'] ?? $this->getEmptyOrder(),
            ];

            // anak turun level +1
            foreach ($node['bawahan'] ?? [] as $child) {
                $result = array_merge(
                    $result,
                    $this->flattenWithRootPromotion(
                        $child,
                        $forecastPerSales,
                        $includedSales,
                        $level + 1,
                        false
                    )
                );
            }

            return $result;
        }

        // =============================
        // CASE 2 — node TIDAK punya forecast
        // → cek anak → promote anak jadi root
        // =============================
        foreach ($node['bawahan'] ?? [] as $child) {
            $result = array_merge(
                $result,
                $this->flattenWithRootPromotion(
                    $child,
                    $forecastPerSales,
                    $includedSales,
                    1,      // reset level
                    true    // promoted root
                )
            );
        }

        return $result;
    }

    private function getEmptyOrder()
    {
        return [
            'Jan' => 0, 'Feb' => 0, 'Mar' => 0,
            'Apr' => 0, 'Mei' => 0, 'Jun' => 0,
            'Jul' => 0, 'Agt' => 0, 'Sep' => 0,
            'Okt' => 0, 'Nov' => 0, 'Des' => 0,
        ];
    }
}
