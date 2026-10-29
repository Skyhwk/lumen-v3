<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogTransactionQsdService
{
    private const MIN_PERIOD = '2026-06';

    /**
     * Parse revenue_forecast dari string ke float.
     * Handle: "2000000", "2.000.000", "Rp 2.000.000", "2,000,000.50", "2.000.000,50"
     */
    public static function parseRevenueForecast($raw): float
    {
        $str = trim((string) $raw);

        if ($str === '' || $str === null) {
            return 0.0;
        }

        // Hapus prefix non-numerik (Rp, IDR, $, dll)
        $str = preg_replace('/^[^0-9\-]+/', '', $str);

        if (preg_match('/,\d{1,2}$/', $str)) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } else {
            $str = str_replace(['.', ','], '', $str);
        }

        $str = preg_replace('/[^0-9.\-]/', '', $str);

        return round((float) $str, 2);
    }

    /**
     * Sinkronisasi revenue log dengan konsep Buku Besar (Ledger Saldo Berjalan)
     * dan tetap memperbarui tabel qsd_revenue_snapshot.
     */
    public static function syncRevenue(?string $targetPeriode = null): int
    {
        if ($targetPeriode && preg_match('/^\d{4}-\d{2}$/', $targetPeriode)) {
            $date = Carbon::createFromFormat('Y-m', $targetPeriode, 'Asia/Jakarta');
            $startOfMonth = $date->copy()->startOfMonth()->startOfDay();
            $endOfMonth   = $date->copy()->endOfMonth()->endOfDay();
            $now          = Carbon::now('Asia/Jakarta');
        } else {
            $now = Carbon::now('Asia/Jakarta');
            $startOfMonth = $now->copy()->startOfMonth()->startOfDay();
            $endOfMonth   = $now->copy()->endOfMonth()->endOfDay();
        }

        $countInsert = 0;

        try {
            // STEP 1: Ambil SALDO BERJALAN (Grand Total Terakhir) di bulan ini
            $currentGrandTotal = DB::table('qsd_revenue_transaction_log')
                ->whereBetween('tanggal_kelompok', [$startOfMonth, $endOfMonth])
                ->orderByDesc('id')
                ->value('total');

            $currentGrandTotal = $currentGrandTotal ? (float) $currentGrandTotal : 0.0;

            // STEP 2: Ambil riwayat NILAI PER ORDER di bulan ini
            $latestLogs = DB::table('qsd_revenue_transaction_log')
                ->select(
                    'no_order',
                    DB::raw('MAX(periode) as periode'),
                    DB::raw('MAX(tanggal_kelompok) as tanggal_kelompok'),
                    DB::raw('SUM(CASE WHEN status = "penambahan" THEN revenue ELSE -revenue END) as order_total')
                )
                ->whereBetween('tanggal_kelompok', [$startOfMonth, $endOfMonth])
                ->groupBy('no_order')
                ->get()
                ->keyBy('no_order');

            $processedOrders = [];
            $insertData = [];

            // STEP 3: Cek dari daily_qsd ke Log (Update Saldo Berjalan)
            DB::table('daily_qsd')
                ->select(
                    'no_order',
                    DB::raw('MAX(periode) as periode'),
                    DB::raw('MAX(tanggal_kelompok) as tanggal_kelompok'),
                    DB::raw('SUM(total_revenue) as total_revenue')
                )
                ->whereBetween('tanggal_kelompok', [$startOfMonth, $endOfMonth])
                ->groupBy('no_order')
                ->orderBy('no_order')
                ->chunk(500, function ($records) use ($latestLogs, $now, &$insertData, &$processedOrders, &$currentGrandTotal) {
                    foreach ($records as $row) {
                        $orderId = $row->no_order;
                        if (empty($orderId)) continue;

                        $processedOrders[] = $orderId;

                        $currentValue = round((float) $row->total_revenue, 2);
                        $lastOrderTotal = 0.0;
                        $isNewOrder = true;

                        if ($latestLogs->has($orderId)) {
                            $lastOrderTotal = round((float) $latestLogs->get($orderId)->order_total, 2);
                            $isNewOrder = false;
                        }

                        if (!$isNewOrder && abs($currentValue - $lastOrderTotal) < 0.01) {
                            continue;
                        }

                        $isRevisi = !$isNewOrder;

                        if ($isNewOrder || $currentValue > $lastOrderTotal) {
                            $status = 'penambahan';
                            $revenueDiff = round($currentValue - $lastOrderTotal, 2);
                            $currentGrandTotal += $revenueDiff;
                        } else {
                            $status = 'pengurangan';
                            $revenueDiff = round($lastOrderTotal - $currentValue, 2);
                            $currentGrandTotal -= $revenueDiff;
                        }

                        $insertData[] = [
                            'no_order'         => $orderId,
                            'periode'          => $row->periode,
                            'tanggal_kelompok' => $row->tanggal_kelompok,
                            'revenue'          => $revenueDiff,
                            'status'           => $status,
                            'total'            => $currentGrandTotal,
                            'is_revisi'        => $isRevisi,
                            'created_at'       => $now,
                        ];
                    }
                });

            // STEP 4: Menangkap Order yang di-HARD REMOVE atau Pindah Bulan
            $deletedOrders = $latestLogs->except($processedOrders);

            foreach ($deletedOrders as $orderId => $logData) {
                $lastOrderTotal = round((float) $logData->order_total, 2);

                if ($lastOrderTotal > 0.01) {
                    $currentGrandTotal -= $lastOrderTotal;

                    $insertData[] = [
                        'no_order'         => $orderId,
                        'periode'          => $logData->periode,
                        'tanggal_kelompok' => $logData->tanggal_kelompok,
                        'revenue'          => $lastOrderTotal,
                        'status'           => 'pengurangan',
                        'total'            => $currentGrandTotal,
                        'is_revisi'        => true,
                        'created_at'       => $now,
                    ];
                } elseif ($lastOrderTotal < -0.01) {
                    // Auto-Balancing: Menetralkan order yang saldonya minus (orphan reduction)
                    $correction = abs($lastOrderTotal);
                    $currentGrandTotal += $correction;

                    $insertData[] = [
                        'no_order'         => $orderId,
                        'periode'          => $logData->periode,
                        'tanggal_kelompok' => $logData->tanggal_kelompok,
                        'revenue'          => $correction,
                        'status'           => 'penambahan',
                        'total'            => $currentGrandTotal,
                        'is_revisi'        => true,
                        'created_at'       => $now,
                    ];
                }
            }

            // STEP 5: Batch Insert ke Log
            if (!empty($insertData)) {
                foreach (array_chunk($insertData, 500) as $chunk) {
                    DB::table('qsd_revenue_transaction_log')->insert($chunk);
                    $countInsert += count($chunk);
                }
            }

            // STEP 6: Rebuild Snapshot (Proses snapshot tetap berjalan)
            self::rebuildRevenueSnapshot($now);

        } catch (\Throwable $e) {
            Log::error('[LogTransactionQsdService::syncRevenue] Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }

        return $countInsert;
    }

    /**
     * Sinkronisasi forecast log dengan konsep Buku Besar (Ledger Saldo Berjalan)
     * dan tetap memperbarui tabel qsd_forecast_snapshot.
     */
    public static function syncForecast(?string $targetPeriode = null): int
    {
        if ($targetPeriode && preg_match('/^\d{4}-\d{2}$/', $targetPeriode)) {
            $date = Carbon::createFromFormat('Y-m', $targetPeriode, 'Asia/Jakarta');
            $startOfMonth = $date->copy()->startOfMonth()->startOfDay();
            $endOfMonth   = $date->copy()->endOfMonth()->endOfDay();
            $now          = Carbon::now('Asia/Jakarta');
        } else {
            $now = Carbon::now('Asia/Jakarta');
            $startOfMonth = $now->copy()->startOfMonth()->startOfDay();
            $endOfMonth   = $now->copy()->endOfMonth()->endOfDay();
        }

        $countInsert = 0;

        try {
            // STEP 1: Ambil SALDO BERJALAN (Grand Total Terakhir) di bulan ini
            $currentGrandTotal = DB::table('qsd_forecast_transaction_log')
                ->whereBetween('tanggal_sampling_min', [$startOfMonth, $endOfMonth])
                ->orderByDesc('id')
                ->value('total');

            $currentGrandTotal = $currentGrandTotal ? (float) $currentGrandTotal : 0.0;

            // STEP 1.5: Ambil riwayat NILAI PER PENAWARAN di bulan ini
            $latestLogs = DB::table('qsd_forecast_transaction_log')
                ->select(
                    'no_penawaran',
                    DB::raw('MAX(periode) as periode'),
                    DB::raw('MAX(tanggal_sampling_min) as tanggal_sampling_min'),
                    DB::raw('SUM(CASE WHEN status = "penambahan" THEN revenue_forecast ELSE -revenue_forecast END) as order_total'),
                    DB::raw('MAX(forecast_order) as is_ordered')
                )
                ->whereBetween('tanggal_sampling_min', [$startOfMonth, $endOfMonth])
                ->groupBy('no_penawaran')
                ->get()
                ->keyBy('no_penawaran');

            $activePenawaran = [];
            $insertData = [];

            // STEP 2: Tarik & Grouping di Memori berdasarkan Kunci Ganda
            $groupedForecasts = [];

            DB::table('forecast_sp')
                ->select('no_quotation', 'periode', 'tanggal_sampling_min', 'revenue_forecast')
                ->whereNotNull('tanggal_sampling_min')
                ->whereBetween('tanggal_sampling_min', [$startOfMonth, $endOfMonth])
                ->orderBy('no_quotation')
                ->chunk(1000, function ($records) use (&$groupedForecasts) {
                    foreach ($records as $row) {
                        $penawaranId = $row->no_quotation;
                        if (empty($penawaranId)) continue;

                        $uniqueKey = $penawaranId;
                        $parsedValue = self::parseRevenueForecast($row->revenue_forecast);

                        if (!isset($groupedForecasts[$uniqueKey])) {
                            $groupedForecasts[$uniqueKey] = [
                                'no_quotation'         => $penawaranId,
                                'periode'              => $row->periode,
                                'tanggal_sampling_min' => $row->tanggal_sampling_min,
                                'total_revenue'        => 0.0,
                            ];
                        }

                        $groupedForecasts[$uniqueKey]['total_revenue'] += $parsedValue;
                    }
                });

            // STEP 3: Cek Perubahan Nilai & Insert (Update Saldo Berjalan)
            foreach ($groupedForecasts as $uniqueKey => $data) {
                $activePenawaran[] = $uniqueKey;

                $currentValue = round($data['total_revenue'], 2);
                $lastOrderTotal = 0.0;
                $isNewPenawaran = true;
                $wasOrdered = false;

                if ($latestLogs->has($uniqueKey)) {
                    $log = $latestLogs->get($uniqueKey);
                    $lastOrderTotal = round((float) $log->order_total, 2);
                    $wasOrdered = (int) $log->is_ordered === 1;
                    $isNewPenawaran = false;
                }

                if (!$isNewPenawaran && !$wasOrdered && abs($currentValue - $lastOrderTotal) < 0.01) {
                    continue;
                }

                if ($isNewPenawaran || $wasOrdered) {
                    $status = 'penambahan';
                    $revenueDiff = round($currentValue - $lastOrderTotal, 2);
                    $currentGrandTotal += $revenueDiff;
                } elseif ($currentValue > $lastOrderTotal) {
                    $status = 'penambahan';
                    $revenueDiff = round($currentValue - $lastOrderTotal, 2);
                    $currentGrandTotal += $revenueDiff;
                } else {
                    $status = 'pengurangan';
                    $revenueDiff = round($lastOrderTotal - $currentValue, 2);
                    $currentGrandTotal -= $revenueDiff;
                }

                $insertData[] = [
                    'no_penawaran'         => $data['no_quotation'],
                    'periode'              => $data['periode'],
                    'tanggal_sampling_min' => $data['tanggal_sampling_min'],
                    'revenue_forecast'     => $revenueDiff,
                    'status'               => $status,
                    'total'                => $currentGrandTotal,
                    'forecast_order'       => 0,
                    'created_at'           => $now,
                ];
            }

            // STEP 4: Deteksi Status Berubah Menjadi Order (Hard Delete dari Forecast)
            $deletedQuotations = $latestLogs->except($activePenawaran);

            foreach ($deletedQuotations as $uniqueKey => $logEntry) {
                $lastOrderTotal = round((float) $logEntry->order_total, 2);

                if ((int)$logEntry->is_ordered === 1 || $lastOrderTotal <= 0.01) {
                    continue;
                }

                $currentGrandTotal -= $lastOrderTotal;

                $insertData[] = [
                    'no_penawaran'         => $logEntry->no_penawaran,
                    'periode'              => $logEntry->periode,
                    'tanggal_sampling_min' => $logEntry->tanggal_sampling_min,
                    'revenue_forecast'     => $lastOrderTotal,
                    'status'               => 'pengurangan',
                    'total'                => $currentGrandTotal,
                    'forecast_order'       => 1,
                    'created_at'           => $now,
                ];
            }

            // STEP 5: Batch Insert ke Log
            if (!empty($insertData)) {
                foreach (array_chunk($insertData, 500) as $chunk) {
                    DB::table('qsd_forecast_transaction_log')->insert($chunk);
                    $countInsert += count($chunk);
                }
            }

            // STEP 6: Rebuild Snapshot (Proses snapshot tetap berjalan)
            self::rebuildForecastSnapshot($now);

        } catch (\Throwable $e) {
            Log::error('[LogTransactionQsdService::syncForecast] Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }

        return $countInsert;
    }

    public static function run(?string $targetPeriode = null): array
    {
        return [
            'revenue_logs'  => self::syncRevenue($targetPeriode),
            'forecast_logs' => self::syncForecast($targetPeriode),
        ];
    }

    /**
     * Memperbarui tabel snapshot qsd_revenue_snapshot.
     */
    public static function rebuildRevenueSnapshot(Carbon $now): void
    {
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

    /**
     * Memperbarui tabel snapshot qsd_forecast_snapshot.
     */
    public static function rebuildForecastSnapshot(Carbon $now): void
    {
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
