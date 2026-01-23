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

        printf("\n[UpdateForecastSP] Processing KONTRAK data...");

        // Proses data kontrak dengan flatMap untuk multiple rows
        $kontrakItems = $dataKontrak->flatMap(function ($item) {
            if (empty($item->detail)) {
                return collect();
            }

            return $item->detail->map(function ($detail) use ($item) {
                // Filter untuk data_lama
                if (!empty($item->data_lama)) {
                    $isLunas = $item->dailyQsd->first(function ($dailyQsd) use ($detail) {
                        return $dailyQsd['periode'] == $detail['periode_kontrak']
                            && !empty($dailyQsd['is_lunas']);
                    });

                    if (!$isLunas) {
                        return null;
                    }
                }

                // Cari tanggal sampling minimal untuk periode ini
                $tanggalMinSampling = $item->jadwal
                    ->where('periode', $detail['periode_kontrak'])
                    ->min('tanggal');

                return [
                    'id' => $item->id,
                    'no_document' => $item->no_document,
                    'data_lama' => $item->data_lama ? json_decode($item->data_lama) : null,
                    'sales_id' => $item->sales_id,
                    'periode' => $detail['periode_kontrak'],
                    'tanggal_min_sampling' => $tanggalMinSampling,
                    'sales_penanggung_jawab' => $item->sales->nama_lengkap ?? null,
                    'biaya_akhir' => $detail['biaya_akhir'] ?? 0,
                    'total_ppn' => $detail['total_ppn'] ?? 0,
                    'total_pph' => $detail['total_pph'] ?? 0,
                    'revenue_forecast' => ($detail['biaya_akhir'] ?? 0) - ($detail['total_ppn'] ?? 0),
                    'created_at' => $item->created_at,
                ];
            })->filter();
        });

        printf("\n[UpdateForecastSP] KONTRAK processed: %d detail rows", $kontrakItems->count());

        printf("\n[UpdateForecastSP] Processing NON KONTRAK data...");

        // Proses data non kontrak
        $nonKontrakItems = $dataNonKontrak->map(function ($item) {
            $tanggalMinSampling = $item->jadwal->min('tanggal');

            return [
                'id' => $item->id,
                'no_document' => $item->no_document,
                'data_lama' => $item->data_lama ? json_decode($item->data_lama) : null,
                'sales_id' => $item->sales_id,
                'periode' => null,
                'tanggal_min_sampling' => $tanggalMinSampling,
                'sales_penanggung_jawab' => $item->sales->nama_lengkap ?? null,
                'biaya_akhir' => $item->biaya_akhir ?? 0,
                'total_ppn' => $item->total_ppn ?? 0,
                'total_pph' => $item->total_pph ?? 0,
                'revenue_forecast' => ($item->biaya_akhir ?? 0) - ($item->total_ppn ?? 0),
                'created_at' => $item->created_at,
            ];
        });

        printf("\n[UpdateForecastSP] NON KONTRAK processed: %d rows", $nonKontrakItems->count());

        // Gabungkan semua data
        $formattedData = $kontrakItems->merge($nonKontrakItems);

        printf("\n[UpdateForecastSP] Total forecast data ready: %d rows", $formattedData->count());

        // Debug info
        $kontrakPerDocument = $formattedData
            ->groupBy('no_document')
            ->map(fn($items) => $items->count());
        
        if ($kontrakPerDocument->isNotEmpty()) {
            printf("\n[UpdateForecastSP] Kontrak details per document:");
            foreach ($kontrakPerDocument as $doc => $count) {
                if ($count > 1) {
                    printf("\n  - %s: %d periode", $doc, $count);
                }
            }
        }

        // printf("\n[UpdateForecastSP] Truncating table forecast_sp...");

        // ForecastSP::truncate();

        // printf("\n[UpdateForecastSP] Truncated table");

        printf("\n[UpdateForecastSP] Preparing data for insertion...");

        $dataToInsert = $formattedData->map(function ($row) {
            if (empty($row['tanggal_min_sampling'])) {
                return null;
            }

            $noOrder = null;
            if ($row['data_lama'] && isset($row['data_lama']->no_order)) {
                $noOrder = $row['data_lama']->no_order;
            }

            // Buat UUID unik berdasarkan no_document + periode
            $uuidString = trim($row['no_document']) . '|' . trim($row['periode'] ?? 'single');
            $uuid = (new Crypto())->encrypt($uuidString);

            return [
                'uuid' => $uuid,
                'no_quotation' => $row['no_document'],
                'no_order' => $noOrder,
                'periode' => $row['periode'],
                'sales_id' => $row['sales_id'],
                'sales_penanggung_jawab' => $row['sales_penanggung_jawab'],
                'tanggal_sampling_min' => $row['tanggal_min_sampling'],
                'biaya_akhir' => $row['biaya_akhir'] ?: 0,
                'total_ppn' => $row['total_ppn'] ?: 0,
                'total_pph' => $row['total_pph'] ?: 0,
                'revenue_forecast' => $row['revenue_forecast'] ?: 0,
                'created_at' => Carbon::now(),
            ];
        })->filter()->values()->toArray();

        printf("\n[UpdateForecastSP] Upserting %d rows to forecast_sp...", count($dataToInsert));

        // Upsert dengan chunk untuk menghindari memory issue
        $chunks = array_chunk($dataToInsert, 1000);
        $totalUpserted = 0;
        
        foreach ($chunks as $chunk) {
            ForecastSP::upsert($chunk, ['uuid'], [
                'no_quotation',
                'no_order',
                'periode',
                'sales_id',
                'sales_penanggung_jawab',
                'tanggal_sampling_min',
                'biaya_akhir',
                'total_ppn',
                'total_pph',
                'revenue_forecast',
            ]);
            $totalUpserted += count($chunk);
            printf("\n[UpdateForecastSP] Upserted %d rows...", $totalUpserted);
        }

        printf("\n[UpdateForecastSP] Upsert completed: %d rows", $totalUpserted);

        // Verifikasi data
        printf("\n[UpdateForecastSP] Verifying data...");
        $forecastCount = ForecastSP::count();
        printf("\n[UpdateForecastSP] Records in forecast_sp table: %d", $forecastCount);

        $totalTime = microtime(true) - $startTime;

        printf("\n[UpdateForecastSP] Finished. Total time: %.3f seconds", $totalTime);
    }
}