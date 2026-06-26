<?php

namespace App\Services\PaymentPerformance;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentPerformanceService
{
    private const MIN_YEAR = 2025;

    public function resolveYear($year): int
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

    public function getDashboardData(int $year): array
    {
        [$startDate, $endDate] = $this->getYearDateRange($year);
        $rows = $this->fetchEligibleRows($startDate, $endDate, $year);

        if ($rows->isEmpty()) {
            return $this->emptyResponse($year);
        }

        $invoiceMap = $this->loadInvoicePeriodMap(
            $rows->pluck('no_quotation')->filter()->unique()->values()->all()
        );

        $orderMetrics = $this->buildOrderMetrics($rows, $invoiceMap);

        if ($orderMetrics->isEmpty()) {
            return $this->emptyResponse($year);
        }

        return [
            'year' => $year,
            'summaryCards' => $this->buildSummaryCards($orderMetrics),
            'fastestCompanies' => $this->buildCompanyRanking($orderMetrics, 'asc'),
            'slowestCompanies' => $this->buildCompanyRanking($orderMetrics, 'desc'),
        ];
    }

    private function emptyResponse(int $year): array
    {
        return [
            'year' => $year,
            'summaryCards' => PaymentDurationFormatter::buildSummaryCards(null, null, null),
            'fastestCompanies' => [],
            'slowestCompanies' => [],
        ];
    }

    private function getYearDateRange(int $year): array
    {
        return [
            Carbon::create($year, 1, 1)->startOfDay()->toDateString(),
            Carbon::create($year + 1, 1, 1)->startOfDay()->toDateString(),
        ];
    }

    private function fetchEligibleRows(string $startDate, string $endDate, int $year): Collection
    {
        return collect(DB::select("
            SELECT
                dq.uuid,
                dq.no_order,
                dq.no_quotation,
                dq.kontrak,
                dq.periode,
                dq.nama_perusahaan,
                dq.pelanggan_ID,
                dq.tanggal_order,
                dq.tanggal_sampling_min,
                dq.tanggal_pembayaran
            FROM daily_qsd dq
            WHERE dq.status_sampling <> 'Non Pengujian'
                AND dq.tanggal_pembayaran IS NOT NULL
                AND dq.tanggal_order IS NOT NULL
                AND dq.tanggal_sampling_min IS NOT NULL
                AND dq.tanggal_kelompok >= ?
                AND dq.tanggal_kelompok < ?
                AND YEAR(STR_TO_DATE(SUBSTRING_INDEX(dq.tanggal_pembayaran, ',', 1), '%Y-%m-%d')) = ?
        ", [$startDate, $endDate, $year]));
    }

    /**
     * @return array<string, array{has_all: bool, periods: array<int, string>}>
     */
    private function loadInvoicePeriodMap(array $quotations): array
    {
        if (empty($quotations)) {
            return [];
        }

        $invoiceRows = DB::table('invoice')
            ->select('no_quotation', 'periode')
            ->where('is_active', 1)
            ->whereIn('no_quotation', $quotations)
            ->get();

        $map = [];

        foreach ($invoiceRows as $invoice) {
            $quotation = $invoice->no_quotation;
            $period = strtolower(trim((string) ($invoice->periode ?? '')));

            if (!isset($map[$quotation])) {
                $map[$quotation] = [
                    'has_all' => false,
                    'periods' => [],
                ];
            }

            if ($period === 'all') {
                $map[$quotation]['has_all'] = true;
                continue;
            }

            if ($period !== '' && $period !== 'null') {
                $map[$quotation]['periods'][$invoice->periode] = true;
            }
        }

        return $map;
    }

    private function buildOrderMetrics(Collection $rows, array $invoiceMap): Collection
    {
        $metrics = collect();
        $kontrakAllBuckets = [];
        $nonKontrakBuckets = [];

        foreach ($rows as $row) {
            if ($this->isKontrakAllInvoice($row, $invoiceMap)) {
                $kontrakAllBuckets[$row->no_order]['rows'][] = $row;
                $kontrakAllBuckets[$row->no_order]['meta'] = [
                    'nama_perusahaan' => $row->nama_perusahaan,
                    'pelanggan_ID' => $row->pelanggan_ID,
                    'tanggal_order' => $row->tanggal_order,
                ];
                continue;
            }

            if (($row->kontrak ?? '') !== 'C') {
                $nonKontrakBuckets[$row->no_order]['rows'][] = $row;
                continue;
            }

            $metric = $this->buildKontrakPeriodeMetric(
                $row,
                $this->resolveLastPaymentDate($row->tanggal_pembayaran)
            );

            if ($metric !== null) {
                $metrics->push($metric);
            }
        }

        foreach ($kontrakAllBuckets as $noOrder => $bucket) {
            $metric = $this->buildKontrakAllMetric($noOrder, $bucket);

            if ($metric !== null) {
                $metrics->push($metric);
            }
        }

        foreach ($nonKontrakBuckets as $noOrder => $bucket) {
            $metric = $this->buildNonKontrakMetricFromBucket($noOrder, $bucket);

            if ($metric !== null) {
                $metrics->push($metric);
            }
        }

        return $metrics;
    }

    private function isKontrakAllInvoice(object $row, array $invoiceMap): bool
    {
        if (($row->kontrak ?? '') !== 'C') {
            return false;
        }

        $invoiceInfo = $invoiceMap[$row->no_quotation] ?? null;

        if ($invoiceInfo === null || !$invoiceInfo['has_all']) {
            return false;
        }

        $rowPeriod = trim((string) ($row->periode ?? ''));

        return $rowPeriod === '' || !isset($invoiceInfo['periods'][$row->periode]);
    }

    private function buildKontrakAllMetric(string $noOrder, array $bucket): ?array
    {
        $orderDate = $this->parseDate($bucket['meta']['tanggal_order'] ?? null);

        if ($orderDate === null) {
            return null;
        }

        $lastPayment = collect($bucket['rows'] ?? [])
            ->map(fn ($row) => $this->resolveLastPaymentDate($row->tanggal_pembayaran))
            ->filter()
            ->max();

        if ($lastPayment === null) {
            return null;
        }

        $paymentDays = $orderDate->diffInDays($lastPayment, false);

        if ($paymentDays < 0) {
            return null;
        }

        return [
            'metric_key' => 'kontrak_all|' . $noOrder,
            'no_order' => $noOrder,
            'nama_perusahaan' => $bucket['meta']['nama_perusahaan'],
            'pelanggan_ID' => $bucket['meta']['pelanggan_ID'],
            'payment_days' => $paymentDays,
            'calculation_type' => 'kontrak_all',
        ];
    }

    private function buildNonKontrakMetricFromBucket(string $noOrder, array $bucket): ?array
    {
        $firstRow = $bucket['rows'][0] ?? null;

        if ($firstRow === null) {
            return null;
        }

        $orderDate = $this->parseDate($firstRow->tanggal_order);

        if ($orderDate === null) {
            return null;
        }

        $lastPayment = collect($bucket['rows'])
            ->map(fn ($row) => $this->resolveLastPaymentDate($row->tanggal_pembayaran))
            ->filter()
            ->max();

        if ($lastPayment === null) {
            return null;
        }

        $paymentDays = $orderDate->diffInDays($lastPayment, false);

        if ($paymentDays < 0) {
            return null;
        }

        $samplingDates = collect($bucket['rows'])
            ->map(fn ($row) => $this->parseDate($row->tanggal_sampling_min))
            ->filter();

        $earliestSampling = $samplingDates->isEmpty() ? null : $samplingDates->min();

        if ($earliestSampling !== null && $lastPayment->lessThan($earliestSampling)) {
            return null;
        }

        return [
            'metric_key' => 'non_kontrak|' . $noOrder,
            'no_order' => $noOrder,
            'nama_perusahaan' => $firstRow->nama_perusahaan,
            'pelanggan_ID' => $firstRow->pelanggan_ID,
            'payment_days' => $paymentDays,
            'calculation_type' => 'non_kontrak',
        ];
    }

    private function buildKontrakPeriodeMetric(object $row, ?Carbon $lastPayment): ?array
    {
        if ($lastPayment === null) {
            return null;
        }

        $samplingDate = $this->parseDate($row->tanggal_sampling_min);

        if ($samplingDate === null) {
            return null;
        }

        $paymentDays = $samplingDate->diffInDays($lastPayment, false);

        if ($paymentDays < 0 || !$this->isPaymentAfterSampling($lastPayment, $row->tanggal_sampling_min)) {
            return null;
        }

        return [
            'metric_key' => 'kontrak_periode|' . $row->uuid,
            'no_order' => $row->no_order,
            'nama_perusahaan' => $row->nama_perusahaan,
            'pelanggan_ID' => $row->pelanggan_ID,
            'payment_days' => $paymentDays,
            'calculation_type' => 'kontrak_periode',
        ];
    }

    private function isPaymentAfterSampling(Carbon $paymentDate, ?string $samplingDate): bool
    {
        $sampling = $this->parseDate($samplingDate);

        if ($sampling === null) {
            return true;
        }

        return $paymentDate->greaterThanOrEqualTo($sampling);
    }

    private function resolveLastPaymentDate(?string $tanggalPembayaran): ?Carbon
    {
        if ($tanggalPembayaran === null || trim($tanggalPembayaran) === '') {
            return null;
        }

        $dates = collect(explode(',', $tanggalPembayaran))
            ->map(fn ($date) => $this->parseDate(trim($date)))
            ->filter();

        return $dates->isEmpty() ? null : $dates->sort()->last();
    }

    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse(trim($value))->startOfDay();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function buildSummaryCards(Collection $orderMetrics): array
    {
        $days = $orderMetrics->pluck('payment_days');
        $companyStats = $this->buildCompanyStats($orderMetrics);
        $positiveCompanies = $companyStats->filter(fn ($company) => $company['avg_days'] > 0);

        return PaymentDurationFormatter::buildSummaryCards(
            $positiveCompanies->isNotEmpty()
                ? $positiveCompanies->min('avg_days')
                : null,
            $companyStats->max('avg_days'),
            round($days->avg(), 1)
        );
    }

    private function buildCompanyStats(Collection $orderMetrics): Collection
    {
        return $orderMetrics
            ->groupBy('pelanggan_ID')
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'nama_perusahaan' => $first['nama_perusahaan'],
                    'pelanggan_ID' => $first['pelanggan_ID'],
                    'avg_days' => round($rows->avg('payment_days'), 1),
                    'total_order' => $rows->count(),
                ];
            })
            ->values();
    }

    private function buildCompanyRanking(Collection $orderMetrics, string $direction): array
    {
        $companyStats = $this->buildCompanyStats($orderMetrics);

        if ($direction === 'asc') {
            $companyStats = $companyStats
                ->filter(fn ($company) => $company['avg_days'] > 0)
                ->sortBy(fn ($company) => [$company['avg_days'], -$company['total_order']])
                ->values();
        } else {
            $companyStats = $companyStats
                ->sortByDesc(fn ($company) => [$company['avg_days'], $company['total_order']])
                ->values();
        }

        return $companyStats
            ->take(10)
            ->values()
            ->map(function (array $company, int $index) {
                return [
                    'rank' => $index + 1,
                    'nama_perusahaan' => $company['nama_perusahaan'],
                    'pelanggan_ID' => $company['pelanggan_ID'],
                    'waktu' => PaymentDurationFormatter::formatDays($company['avg_days']),
                    'avg_days' => $company['avg_days'],
                    'total_order' => $company['total_order'],
                ];
            })
            ->all();
    }
}
