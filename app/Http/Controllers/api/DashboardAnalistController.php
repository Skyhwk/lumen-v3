<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Ftc;
use App\Models\Colorimetri;
use App\Models\Gravimetri;
use App\Models\Subkontrak;
use App\Models\Titrimetri;
use App\Models\LingkunganHeader;
use App\Models\MicrobioHeader;
use App\Models\EmisiCerobongHeader;
use App\Models\RekapLiburKalender;
use Carbon\Carbon;
use Illuminate\Http\Request;

Carbon::setLocale('id');

class DashboardAnalistController extends Controller
{
    private function getHeaderConfigs(): array
    {
        return [
            'colorimetri' => ['model' => Colorimetri::class, 'label' => 'Colorimetri'],
            'titrimetri'  => ['model' => Titrimetri::class,  'label' => 'Titrimetri' ],
            'gravimetri'  => ['model' => Gravimetri::class,  'label' => 'Gravimetri' ],
            'lingkungan'  => ['model' => LingkunganHeader::class,  'label' => 'Lingkungan' ],
            'microbiologi'  => ['model' => MicrobioHeader::class,  'label' => 'Microbiologi' ],
            'emisi_cerobong'  => ['model' => EmisiCerobongHeader::class,  'label' => 'Emisi Cerobong' ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER FORMAT DURATION
    | Konversi detik → string human-readable.
    | Jika >= 86400 detik (24 jam), tampilkan dalam Hari.
    |--------------------------------------------------------------------------
    */
    private function formatDuration(?float $seconds): string
    {
        if (!$seconds || $seconds <= 0) {
            return '-';
        }

        $days    = floor($seconds / 86400);
        $hours   = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs    = $seconds % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = $days . ' Hari';
            // Jika ada sisa jam setelah hari, tampilkan juga
            if ($hours > 0) {
                $parts[] = $hours . ' Jam';
            }
            return implode(', ', $parts);
        }

        if ($hours > 0) {
            $parts[] = $hours . ' Jam';
        }
        if ($minutes > 0) {
            $parts[] = $minutes . ' Menit';
        }
        if ($hours === 0 && $minutes === 0) {
            $parts[] = $secs . ' Detik';
        }

        return implode(', ', $parts);
    }

    /*
    |--------------------------------------------------------------------------
    | COLLECT ALL SECONDS — SCAN → INPUT HASIL
    |
    | Mengumpulkan semua nilai diff_seconds dari seluruh header
    | (JOIN t_ftc via no_sampel → ftc_laboratory ke header.created_at).
    | Return: Collection of diff_seconds (semua header digabung).
    |--------------------------------------------------------------------------
    */
    private function collectScanToInput(int $year, int $month): \Illuminate\Support\Collection
    {
        $allSeconds = collect();

        $workingDates = $this->getWorkingDates($year);

        foreach ($this->getHeaderConfigs() as $config) {

            $modelClass  = $config['model'];
            $headerTable = (new $modelClass)->getTable();

            $rows = Ftc::query()
                ->join(
                    $headerTable,
                    "{$headerTable}.no_sampel",
                    '=',
                    't_ftc.no_sample'
                )
                ->whereNotNull('t_ftc.ftc_laboratory')
                ->whereNotNull("{$headerTable}.created_at")
                ->whereYear('t_ftc.ftc_laboratory', $year)
                ->whereMonth('t_ftc.ftc_laboratory', $month)
                ->select([
                    't_ftc.ftc_laboratory',
                    "{$headerTable}.created_at as input_created_at"
                ])
                ->get();

            foreach ($rows as $row) {

                $seconds = $this->calculateWorkingSeconds(
                    Carbon::parse($row->ftc_laboratory),
                    Carbon::parse($row->input_created_at),
                    $workingDates
                );

                if ($seconds > 0) {
                    $allSeconds->push($seconds);
                }
            }
        }

        return $allSeconds;
    }

    /*
    |--------------------------------------------------------------------------
    | COLLECT ALL SECONDS — INPUT HASIL → APPROVE
    |
    | Mengumpulkan semua nilai diff_seconds dari seluruh header
    | (header.created_at → header.approved_at).
    | Return: Collection of diff_seconds (semua header digabung).
    |--------------------------------------------------------------------------
    */
    private function collectInputToApprove(int $year, int $month): \Illuminate\Support\Collection
    {
        $allSeconds = collect();

        $workingDates = $this->getWorkingDates($year);

        foreach ($this->getHeaderConfigs() as $config) {

            $modelClass = $config['model'];

            $rows = $modelClass::query()
                ->whereNotNull('created_at')
                ->whereNotNull('approved_at')
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->select([
                    'created_at',
                    'approved_at'
                ])
                ->get();

            foreach ($rows as $row) {

                $seconds = $this->calculateWorkingSeconds(
                    Carbon::parse($row->created_at),
                    Carbon::parse($row->approved_at),
                    $workingDates
                );

                if ($seconds > 0) {
                    $allSeconds->push($seconds);
                }
            }
        }

        return $allSeconds;
    }

    /*
    |--------------------------------------------------------------------------
    | CALC STATS — max & avg dari collection seconds
    |--------------------------------------------------------------------------
    */
    private function calcStats(\Illuminate\Support\Collection $seconds): array
    {
        if ($seconds->isEmpty()) {
            return ['max' => 0, 'avg' => 0, 'count' => 0];
        }

        return [
            'max'   => (float) $seconds->max(),
            'avg'   => (float) $seconds->avg(),
            'count' => $seconds->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX — SUMMARY CARDS
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $tahun = $request->year  ?? Carbon::now()->year;
        $bulan = $request->month ?? Carbon::now()->format('m');

        // --- Lab Scan → Input Hasil ---
        $scanSeconds = $this->collectScanToInput($tahun, $bulan);
        $scanStats   = $this->calcStats($scanSeconds);

        // --- Input Hasil → Approve ---
        $approveSeconds = $this->collectInputToApprove($tahun, $bulan);
        $approveStats   = $this->calcStats($approveSeconds);

        return response()->json([
            'scanToInput' => [
                'max'   => $this->formatDuration($scanStats['max']),
                'avg'   => $this->formatDuration($scanStats['avg']),
                'count' => $scanStats['count'],
            ],
            'inputToApprove' => [
                'max'   => $this->formatDuration($approveStats['max']),
                'avg'   => $this->formatDuration($approveStats['avg']),
                'count' => $approveStats['count'],
            ],
        ], 200);
    }

    /*
    |--------------------------------------------------------------------------
    | GET CHART — Jumlah uji per bulan per header model
    |--------------------------------------------------------------------------
    */
    public function getChart(Request $request)
    {
        $tahun = $request->year ?? Carbon::now()->year;

        $chartLabels = [
            1 => 'Jan',  2 => 'Feb',  3 => 'Mar',  4 => 'Apr',
            5 => 'Mei',  6 => 'Jun',  7 => 'Jul',  8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $configs  = $this->getHeaderConfigs();
        $datasets = [];

        $accentMap = [
            'colorimetri' => '#7C3AED',
            'titrimetri'  => '#0891B2',
            'gravimetri'  => '#D97706',
            // 'subkontrak'  => '#DC2626',
        ];

        foreach ($configs as $key => $config) {
            $modelClass = $config['model'];

            $monthly = $modelClass::query()
                ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
                ->whereYear('created_at', $tahun)
                ->whereNotNull('created_at')
                ->groupByRaw('MONTH(created_at)')
                ->pluck('total', 'month');

            $values = collect(range(1, 12))
                ->map(fn($m) => (int) ($monthly[$m] ?? 0))
                ->values();

            $datasets[] = [
                'key'    => $key,
                'label'  => $config['label'],
                'accent' => $accentMap[$key] ?? '#185ABC',
                'data'   => $values,
            ];
        }

        return response()->json([
            'labels'   => array_values($chartLabels),
            'datasets' => $datasets,
        ], 200);
    }

    private function getWorkingDates(int $year): array
    {
        $calendar = RekapLiburKalender::where('tahun', $year)->first();

        if (!$calendar) {
            return [];
        }

        $data = json_decode($calendar->tanggal, true);

        return collect($data)
            ->flatten()
            ->unique()
            ->values()
            ->toArray();
    }

    private function calculateWorkingSeconds(
        Carbon $start,
        Carbon $end,
        array $workingDates
    ): int {
        if ($start->gte($end)) {
            return 0;
        }

        $seconds = 0;

        $current = $start->copy()->startOfDay();

        while ($current->lte($end)) {

            $date = $current->format('Y-m-d');

            if (in_array($date, $workingDates)) {

                $dayStart = $current->copy()->startOfDay();
                $dayEnd   = $current->copy()->endOfDay();

                $rangeStart = $start->gt($dayStart)
                    ? $start
                    : $dayStart;

                $rangeEnd = $end->lt($dayEnd)
                    ? $end
                    : $dayEnd;

                if ($rangeEnd->gt($rangeStart)) {
                    $seconds += $rangeEnd->diffInSeconds($rangeStart);
                }
            }

            $current->addDay();
        }

        return $seconds;
    }
}