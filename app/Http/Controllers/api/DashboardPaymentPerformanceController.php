<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardPaymentPerformanceController extends Controller
{
    private const MIN_YEAR = 2025;

    public function index(Request $request)
    {
        $year = $this->resolveYear($request->year);
        $paymentRows = $this->getPaymentRows($year);

        if ($paymentRows->isEmpty()) {
            return response()->json([
                'year' => $year,
                'summaryCards' => $this->buildSummaryCards(null, null, null),
                'fastestCompanies' => [],
                'slowestCompanies' => [],
            ], 200);
        }

        $days = $paymentRows->pluck('payment_days');
        $positiveDays = $days->filter(fn ($day) => $day > 0);
        $companyStats = $this->buildCompanyStats($paymentRows);

        return response()->json([
            'year' => $year,
            'summaryCards' => $this->buildSummaryCards(
                $positiveDays->isNotEmpty() ? $positiveDays->min() : null,
                $days->max(),
                round($days->avg(), 1)
            ),
            'fastestCompanies' => $this->mapCompanyRanking(
                $companyStats
                    ->filter(fn ($company) => $company->avg_days > 0)
                    ->sortBy('avg_days')
                    ->take(10)
                    ->values()
            ),
            'slowestCompanies' => $this->mapCompanyRanking(
                $companyStats->sortByDesc('avg_days')->take(10)->values()
            ),
        ], 200);
    }

    private function resolveYear($year): int
    {
        $currentYear = (int) Carbon::now()->year;
        $year = (int) ($year ?: $currentYear);

        if ($year > $currentYear) {
            $year = $currentYear;
        }

        if ($year < self::MIN_YEAR) {
            $year = self::MIN_YEAR;
        }

        return $year;
    }

    private function getPaymentRows(int $year)
    {
        return collect(DB::select("
            SELECT
                dq.nama_perusahaan,
                dq.pelanggan_ID,
                DATEDIFF(
                    STR_TO_DATE(SUBSTRING_INDEX(dq.tanggal_pembayaran, ',', 1), '%Y-%m-%d'),
                    DATE(dq.tanggal_order)
                ) AS payment_days
            FROM daily_qsd dq
            WHERE dq.is_invoicing = 1
                AND dq.tanggal_pembayaran IS NOT NULL
                AND dq.tanggal_order IS NOT NULL
                AND YEAR(dq.tanggal_kelompok) = ?
                AND DATEDIFF(
                    STR_TO_DATE(SUBSTRING_INDEX(dq.tanggal_pembayaran, ',', 1), '%Y-%m-%d'),
                    DATE(dq.tanggal_order)
                ) >= 0
        ", [$year]));
    }

    private function buildCompanyStats($paymentRows)
    {
        return $paymentRows
            ->groupBy('pelanggan_ID')
            ->map(function ($rows) {
                $first = $rows->first();
                $avgDays = round($rows->avg('payment_days'), 1);

                return (object) [
                    'nama_perusahaan' => $first->nama_perusahaan,
                    'pelanggan_ID' => $first->pelanggan_ID,
                    'avg_days' => $avgDays,
                    'total_order' => $rows->count(),
                ];
            })
            ->values();
    }

    private function mapCompanyRanking($companies): array
    {
        return $companies
            ->values()
            ->map(function ($company, $index) {
                return [
                    'rank' => $index + 1,
                    'nama_perusahaan' => $company->nama_perusahaan,
                    'pelanggan_ID' => $company->pelanggan_ID,
                    'waktu' => $this->formatDays($company->avg_days),
                    'avg_days' => $company->avg_days,
                    'total_order' => (int) $company->total_order,
                ];
            })
            ->all();
    }

    private function buildSummaryCards($min, $max, $avg): array
    {
        return [
            [
                'title' => 'Waktu Pembayaran Tercepat',
                'value' => $this->formatDays($min),
                'accent' => '#16A34A',
                'icon' => '⚡',
            ],
            [
                'title' => 'Waktu Pembayaran Terlama',
                'value' => $this->formatDays($max),
                'accent' => '#DC2626',
                'icon' => '🐢',
            ],
            [
                'title' => 'Rata-rata Waktu Pembayaran',
                'value' => $this->formatDays($avg),
                'accent' => '#185ABC',
                'icon' => '📊',
            ],
        ];
    }

    private function formatDays($days): string
    {
        if ($days === null || $days === '') {
            return '-';
        }

        $days = (float) $days;

        if ($days === floor($days)) {
            return (int) $days . ' Hari';
        }

        return number_format($days, 1, ',', '.') . ' Hari';
    }
}
