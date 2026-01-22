<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

use App\Models\{QuotationKontrakH, QuotationNonKontrak, ForecastSP};

class UpdateForecastSPService
{
    public static function run()
    {
        $startTime = microtime(true);

        printf("\n[UpdateForecastSP] Service started at [%s]", date('Y-m-d H:i:s'));

        $startDate = Carbon::create(2024, 1, 1)->startOfDay();
        $endDate   = Carbon::now()->addYear()->endOfYear();

        printf("\n[UpdateForecastSP] Date range: %s -> %s", $startDate->toDateString(), $endDate->toDateString());

        printf("\n[UpdateForecastSP] Fetching data KONTRAK...");

        $dataKontrak = QuotationKontrakH::with(['dailyQsd', 'sales', 'detail', 'jadwal'])
            ->select(
                'id',
                'no_document',
                'data_lama',
                'sales_id',
                'biaya_akhir',
                'total_ppn',
                'total_pph',
                'created_at'
            )
            ->where('flag_status', 'sp')
            ->where('status_sampling', '<>', 'SD')
            ->where('is_active', 1)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        printf("\n[UpdateForecastSP] KONTRAK fetched: %d data", $dataKontrak->count());

        printf("\n[UpdateForecastSP] Fetching data NON KONTRAK...");

        $dataNonKontrak = QuotationNonKontrak::with(['dailyQsd', 'sales', 'jadwal'])
            ->select(
                'id',
                'no_document',
                'data_lama',
                'sales_id',
                'biaya_akhir',
                'total_ppn',
                'total_pph',
                'created_at'
            )
            ->where('flag_status', 'sp')
            ->where('status_sampling', '<>', 'SD')
            ->where('is_active', 1)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        printf("\n[UpdateForecastSP] NON KONTRAK fetched: %d data", $dataNonKontrak->count());

        printf("\n[UpdateForecastSP] Merging data...");

        $mergedData = collect($dataKontrak)->merge($dataNonKontrak)->toArray();

        printf("\n[UpdateForecastSP] Total merged data: %d", count($mergedData));

        printf("\n[UpdateForecastSP] Formatting forecast data...");

        $formattedData = collect($mergedData)->map(function ($item) {
            if (!empty($item['detail'])) {
                foreach ($item['detail'] as $value) {
                    if (!empty($item['data_lama'])) {
                        $isLunas = collect($item['daily_qsd'] ?? [])->first(fn($dailyQsd) => $dailyQsd['periode'] == $value['periode_kontrak'] && !empty($dailyQsd['is_lunas']));

                        if (!$isLunas) continue;
                    }

                    $tanggalMinSampling = collect($item['jadwal'] ?? [])->filter(fn($j) => $j['periode'] == $value['periode_kontrak'])->min('tanggal');

                    return [
                        'id' => $item['id'],
                        'no_document' => $item['no_document'],
                        'data_lama' => isset($item['data_lama']) ? json_decode($item['data_lama']) : null,
                        'sales_id' => $item['sales_id'],
                        'periode' => $value['periode_kontrak'],
                        'tanggal_min_sampling' => $tanggalMinSampling,
                        'sales_penanggung_jawab' => $item['sales']['nama_lengkap'] ?? null,
                        'biaya_akhir' => $value['biaya_akhir'],
                        'total_ppn' => $value['total_ppn'],
                        'total_pph' => $value['total_pph'],
                        'revenue_forecast' => $value['biaya_akhir'] - $value['total_ppn'],
                        'created_at' => $item['created_at'],
                    ];
                }

                return null;
            }

            $tanggalMinSampling = collect($item['jadwal'] ?? [])->min('tanggal');

            return [
                'id' => $item['id'],
                'no_document' => $item['no_document'],
                'data_lama' => isset($item['data_lama']) ? json_decode($item['data_lama']) : null,
                'sales_id' => $item['sales_id'],
                'periode' => null,
                'tanggal_min_sampling' => $tanggalMinSampling,
                'sales_penanggung_jawab' => $item['sales']['nama_lengkap'] ?? null,
                'biaya_akhir' => $item['biaya_akhir'],
                'total_ppn' => $item['total_ppn'],
                'total_pph' => $item['total_pph'],
                'revenue_forecast' => $item['biaya_akhir'] - $item['total_ppn'],
                'created_at' => $item['created_at'],
            ];
        })->filter()->values();

        printf("\n[UpdateForecastSP] Forecast data ready: %d row", $formattedData->count());

        printf("\n[UpdateForecastSP] Truncating table forecast_sp...");

        ForecastSP::truncate();

        printf("\n[UpdateForecastSP] Truncated table");

        printf("\n[UpdateForecastSP] Inserting new forecast data...");

        ForecastSP::insert(
            $formattedData->map(function ($row) {
                if ($row['tanggal_min_sampling']) {
                    $noOrder = null;
                    $dataLama = $row['data_lama'];
                    if ($dataLama && $dataLama->no_order) {
                        $noOrder = $dataLama->no_order;
                    }

                    return [
                        'uuid' => (new Crypto())->encrypt(trim($row['no_document']) . '|' . trim($row['periode'])),
                        'no_quotation' => $row['no_document'],
                        'no_order' => $noOrder,
                        'periode' => $row['periode'],
                        'sales_id' => $row['sales_id'],
                        'sales_penanggung_jawab' => $row['sales_penanggung_jawab'],
                        'tanggal_sampling_min' => $row['tanggal_min_sampling'],
                        'biaya_akhir' => $row['biaya_akhir'] ?: null,
                        'total_ppn' => $row['total_ppn'] ?: null,
                        'total_pph' => $row['total_pph'] ?: null,
                        'revenue_forecast' => $row['revenue_forecast'],
                        'created_at' => Carbon::now(),
                    ];
                }
            })->filter()->values()->toArray()
        );

        printf("\n[UpdateForecastSP] Insert done: %d row", $formattedData->count());

        $totalTime = microtime(true) - $startTime;

        printf("\n[UpdateForecastSP] Finished. Total time: %.3f seconds", $totalTime);
    }
}
