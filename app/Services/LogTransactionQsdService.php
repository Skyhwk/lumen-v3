<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LogTransactionQsdService
{
    private const MIN_PERIOD = '2026-06';

    public static function syncRevenue(): int
    {
        $now = Carbon::now('Asia/Jakarta');

        $current = DB::table('daily_qsd')
            ->whereNotNull('tanggal_kelompok')
            ->whereNotNull('no_order')
            ->where('no_order', '!=', '')
            ->select(
                'uuid',
                'no_order',
                'periode as periode_kontrak',
                DB::raw("DATE_FORMAT(tanggal_kelompok, '%Y-%m') as bulan_periode"),
                'total_revenue'
            )
            ->get()
            ->keyBy('uuid');

        $snapshots = DB::table('qsd_revenue_snapshot')->get()->keyBy('uuid');

        $isInitialRun = $snapshots->isEmpty();
        $logs = $isInitialRun ? [] : self::buildRevenueDiffLogs($current, $snapshots, $now);

        if (!empty($logs)) {
            foreach (array_chunk($logs, 500) as $chunk) {
                DB::table('qsd_revenue_transaction_log')->insert($chunk);
            }
        }

        self::rebuildRevenueSnapshot($current, $now);

        return count($logs);
    }

    public static function syncForecast(): int
    {
        $now = Carbon::now('Asia/Jakarta');

        $current = DB::table('forecast_sp')
            ->whereNotNull('tanggal_sampling_min')
            ->whereNotNull('no_quotation')
            ->select(
                'uuid',
                'no_quotation as no_penawaran',
                'periode as periode_kontrak',
                DB::raw("DATE_FORMAT(tanggal_sampling_min, '%Y-%m') as bulan_periode"),
                'revenue_forecast'
            )
            ->get()
            ->keyBy('uuid');

        $snapshots = DB::table('qsd_forecast_snapshot')->get()->keyBy('uuid');

        $isInitialRun = $snapshots->isEmpty();
        $logs = $isInitialRun ? [] : self::buildForecastDiffLogs($current, $snapshots, $now);

        if (!empty($logs)) {
            foreach (array_chunk($logs, 500) as $chunk) {
                DB::table('qsd_forecast_transaction_log')->insert($chunk);
            }
        }

        self::rebuildForecastSnapshot($current, $now);

        return count($logs);
    }

    public static function run(): array
    {
        return [
            'revenue_logs'  => self::syncRevenue(),
            'forecast_logs' => self::syncForecast(),
        ];
    }

    private static function buildRevenueDiffLogs($current, $snapshots, Carbon $now): array
    {
        $logs = [];

        foreach ($current as $uuid => $row) {
            $newValue = (float) ($row->total_revenue ?? 0);
            $bulanPeriode = $row->bulan_periode;

            if ($bulanPeriode < self::MIN_PERIOD) {
                continue;
            }

            $old = $snapshots->get($uuid);

            if (!$old) {
                if ($newValue > 0) {
                    $logs[] = self::revenueLogRow($row->no_order, $bulanPeriode, $newValue, 'penambahan', $now);
                }
                continue;
            }

            $oldValue = (float) ($old->total_revenue ?? 0);

            if ($newValue === $oldValue) {
                continue;
            }

            $delta = abs($newValue - $oldValue);
            $status = $newValue > $oldValue ? 'penambahan' : 'pengurangan';

            $logs[] = self::revenueLogRow($row->no_order, $bulanPeriode, $delta, $status, $now);
        }

        foreach ($snapshots as $uuid => $old) {
            if ($current->has($uuid)) {
                continue;
            }

            $oldValue = (float) ($old->total_revenue ?? 0);
            $bulanPeriode = $old->bulan_periode;

            if ($oldValue <= 0 || $bulanPeriode < self::MIN_PERIOD) {
                continue;
            }

            $logs[] = self::revenueLogRow($old->no_order, $bulanPeriode, $oldValue, 'pengurangan', $now);
        }

        return $logs;
    }

    private static function buildForecastDiffLogs($current, $snapshots, Carbon $now): array
    {
        $logs = [];

        foreach ($current as $uuid => $row) {
            $newValue = (float) ($row->revenue_forecast ?? 0);
            $bulanPeriode = $row->bulan_periode;

            if ($bulanPeriode < self::MIN_PERIOD) {
                continue;
            }

            $old = $snapshots->get($uuid);

            if (!$old) {
                if ($newValue > 0) {
                    $logs[] = self::forecastLogRow($row->no_penawaran, $bulanPeriode, $newValue, 'penambahan', $now);
                }
                continue;
            }

            $oldValue = (float) ($old->revenue_forecast ?? 0);

            if ($newValue === $oldValue) {
                continue;
            }

            $delta = abs($newValue - $oldValue);
            $status = $newValue > $oldValue ? 'penambahan' : 'pengurangan';

            $logs[] = self::forecastLogRow($row->no_penawaran, $bulanPeriode, $delta, $status, $now);
        }

        foreach ($snapshots as $uuid => $old) {
            if ($current->has($uuid)) {
                continue;
            }

            $oldValue = (float) ($old->revenue_forecast ?? 0);
            $bulanPeriode = $old->bulan_periode;

            if ($oldValue <= 0 || $bulanPeriode < self::MIN_PERIOD) {
                continue;
            }

            $logs[] = self::forecastLogRow($old->no_penawaran, $bulanPeriode, $oldValue, 'pengurangan', $now);
        }

        return $logs;
    }

    private static function revenueLogRow(string $noOrder, string $bulanPeriode, float $revenue, string $status, Carbon $now): array
    {
        return [
            'no_order'   => $noOrder,
            'periode'    => $bulanPeriode,
            'revenue'    => $revenue,
            'status'     => $status,
            'created_at' => $now,
        ];
    }

    private static function forecastLogRow(string $noPenawaran, string $bulanPeriode, float $revenue, string $status, Carbon $now): array
    {
        return [
            'no_penawaran'     => $noPenawaran,
            'periode'          => $bulanPeriode,
            'revenue_forecast' => $revenue,
            'status'           => $status,
            'created_at'       => $now,
        ];
    }

    private static function rebuildRevenueSnapshot($current, Carbon $now): void
    {
        DB::table('qsd_revenue_snapshot')->truncate();

        $rows = $current
            ->filter(fn ($row) => ($row->bulan_periode ?? '') >= self::MIN_PERIOD)
            ->map(fn ($row) => [
                'uuid'           => $row->uuid,
                'no_order'       => $row->no_order,
                'periode_kontrak'=> $row->periode_kontrak,
                'bulan_periode'  => $row->bulan_periode,
                'total_revenue'  => $row->total_revenue ?? 0,
                'updated_at'     => $now,
            ])
            ->values()
            ->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('qsd_revenue_snapshot')->insert($chunk);
        }
    }

    private static function rebuildForecastSnapshot($current, Carbon $now): void
    {
        DB::table('qsd_forecast_snapshot')->truncate();

        $rows = $current
            ->filter(fn ($row) => ($row->bulan_periode ?? '') >= self::MIN_PERIOD)
            ->map(fn ($row) => [
                'uuid'              => $row->uuid,
                'no_penawaran'      => $row->no_penawaran,
                'periode_kontrak'   => $row->periode_kontrak,
                'bulan_periode'     => $row->bulan_periode,
                'revenue_forecast'  => $row->revenue_forecast ?? 0,
                'updated_at'        => $now,
            ])
            ->values()
            ->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('qsd_forecast_snapshot')->insert($chunk);
        }
    }
}
