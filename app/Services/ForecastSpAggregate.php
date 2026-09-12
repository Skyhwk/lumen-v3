<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ForecastSpAggregate
{
    public const EXCLUDE_CUSTOMERS = ['SAIR02', 'T2PE01', 'TPTT01', 'SEMX01'];

    /** @return array<int, array<string, float|int>> */
    public static function mapBySalesForPeriode(string $periode, ?array $salesIds = null): array
    {
        [$year, $month] = explode('-', $periode);

        $query = DB::table('forecast_sp as f')
            ->whereNotIn('f.pelanggan_ID', self::EXCLUDE_CUSTOMERS)
            ->whereYear('f.tanggal_sampling_min', $year)
            ->whereMonth('f.tanggal_sampling_min', $month);

        if ($salesIds !== null) {
            $query->whereIn('f.sales_id', $salesIds);
        }

        $rows = $query
            ->selectRaw("
                f.sales_id,
                SUM(CASE WHEN f.status_quotation <> 'kontrak' AND f.status_customer = 'new' THEN f.revenue_forecast ELSE 0 END) as revenue_forecast_nonkontrak_new,
                SUM(CASE WHEN f.status_quotation <> 'kontrak' AND f.status_customer = 'exist' THEN f.revenue_forecast ELSE 0 END) as revenue_forecast_nonkontrak_exist,
                SUM(CASE WHEN f.status_quotation = 'kontrak' AND f.status_customer = 'new' THEN f.revenue_forecast ELSE 0 END) as revenue_forecast_kontrak_new,
                SUM(CASE WHEN f.status_quotation = 'kontrak' AND f.status_customer = 'exist' THEN f.revenue_forecast ELSE 0 END) as revenue_forecast_kontrak_exist
            ")
            ->groupBy('f.sales_id')
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $newNon    = (float) ($row->revenue_forecast_nonkontrak_new ?? 0);
            $existNon  = (float) ($row->revenue_forecast_nonkontrak_exist ?? 0);
            $newKon    = (float) ($row->revenue_forecast_kontrak_new ?? 0);
            $existKon  = (float) ($row->revenue_forecast_kontrak_exist ?? 0);
            $total     = $newNon + $existNon + $newKon + $existKon;

            $map[(int) $row->sales_id] = [
                'revenue_forecast_nonkontrak_new'    => $newNon,
                'revenue_forecast_nonkontrak_exist'  => $existNon,
                'revenue_forecast_kontrak_new'       => $newKon,
                'revenue_forecast_kontrak_exist'     => $existKon,
                'revenue_forecast'                   => $total,
            ];
        }

        return $map;
    }

    public static function totalsForPeriode(string $periode, ?array $salesIds = null): array
    {
        $map = self::mapBySalesForPeriode($periode, $salesIds);

        $totals = [
            'revenue_forecast_nonkontrak_new'   => 0.0,
            'revenue_forecast_nonkontrak_exist' => 0.0,
            'revenue_forecast_kontrak_new'      => 0.0,
            'revenue_forecast_kontrak_exist'    => 0.0,
            'revenue_forecast'                  => 0.0,
        ];

        foreach ($map as $row) {
            $totals['revenue_forecast_nonkontrak_new']   += $row['revenue_forecast_nonkontrak_new'];
            $totals['revenue_forecast_nonkontrak_exist'] += $row['revenue_forecast_nonkontrak_exist'];
            $totals['revenue_forecast_kontrak_new']      += $row['revenue_forecast_kontrak_new'];
            $totals['revenue_forecast_kontrak_exist']    += $row['revenue_forecast_kontrak_exist'];
            $totals['revenue_forecast']                  += $row['revenue_forecast'];
        }

        return $totals;
    }

    /** Sales yang punya forecast SP pada tahun berjalan (belum order pun tetap masuk). */
    public static function salesIdsWithForecastInYear(int $year): array
    {
        return DB::table('forecast_sp')
            ->whereNotIn('pelanggan_ID', self::EXCLUDE_CUSTOMERS)
            ->whereYear('tanggal_sampling_min', $year)
            ->whereNotNull('sales_id')
            ->distinct()
            ->pluck('sales_id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
    }

    public static function salesIdsWithForecastInPeriode(string $periode): array
    {
        [$year, $month] = explode('-', $periode);

        return DB::table('forecast_sp')
            ->whereNotIn('pelanggan_ID', self::EXCLUDE_CUSTOMERS)
            ->whereYear('tanggal_sampling_min', $year)
            ->whereMonth('tanggal_sampling_min', $month)
            ->whereNotNull('sales_id')
            ->distinct()
            ->pluck('sales_id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
    }
}
