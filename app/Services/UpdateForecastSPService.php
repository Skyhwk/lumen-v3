<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\{QuotationKontrakH, QuotationNonKontrak, ForecastSP, Jadwal, OrderHeader};

class UpdateForecastSPService
{
    public static function run()
    {
        $startDate = Carbon::create(2024, 1, 1)->startOfDay();
        $endDate   = Carbon::now()->addYear()->endOfYear();
        $startTime = microtime(true);

        printf("\n[UpdateForecastSP] Service started at [%s]", date('Y-m-d H:i:s'));

        // Fetch jadwal kontrak dengan periode
        printf("\n[UpdateForecastSP] Fetching jadwal KONTRAK (with periode)...");
        $jadwalKontrak = Jadwal::selectRaw('no_quotation as no_document, periode, MIN(tanggal) as tanggal')
            ->whereHas('samplingPlan', function ($query) {
                $query->where('is_active', 1);
            })
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->where('is_active', 1)
            ->whereNotNull('periode')
            ->whereNotNull('no_quotation')
            ->groupBy('no_quotation', 'periode')
            ->get();

        printf("\n[UpdateForecastSP] KONTRAK jadwal fetched: %d records", $jadwalKontrak->count());

        // Fetch jadwal non-kontrak tanpa periode
        printf("\n[UpdateForecastSP] Fetching jadwal NON KONTRAK (without periode)...");
        $jadwalNonKontrak = Jadwal::selectRaw('no_quotation as no_document, NULL as periode, MIN(tanggal) as tanggal')
            ->whereHas('samplingPlan', function ($query) {
                $query->where('is_active', 1);
            })
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->where('is_active', 1)
            ->whereNull('periode')
            ->whereNotNull('no_quotation')
            ->groupBy('no_quotation')
            ->get();

        printf("\n[UpdateForecastSP] NON KONTRAK jadwal fetched: %d records", $jadwalNonKontrak->count());

        // Get existing orders to filter out
        printf("\n[UpdateForecastSP] Fetching existing orders...");
        $order = OrderHeader::where('is_active', 1)
            ->where('is_revisi', 0)
            ->pluck('no_document')
            ->toArray();

        printf("\n[UpdateForecastSP] Existing orders: %d", count($order));

        // Combine and filter
        $jadwalFiltered = $jadwalKontrak
            ->concat($jadwalNonKontrak)
            ->filter(function($q) use ($order) {
                return !in_array($q->no_document, $order);
            })
            ->sortBy('tanggal')
            ->values();

        printf("\n[UpdateForecastSP] Filtered jadwal (excluding orders): %d records", $jadwalFiltered->count());

        // Get quotation number from filtered jadwal
        $quotationNumbers = $jadwalFiltered->pluck('no_document')->unique()->toArray();

        if (empty($quotationNumbers)) {
            printf("\n[UpdateForecastSP] No quotations to process. Exiting.");
            return;
        }

        printf("\n[UpdateForecastSP] Fetching quotation data for %d unique quotations...", count($quotationNumbers));

        $dataNonSp = 0;
        // Fetch data KONTRAK
        printf("\n[UpdateForecastSP] Fetching data KONTRAK...");
        $dataKontrak = QuotationKontrakH::with(['dailyQsd', 'sales', 'detail', 'pelanggan'])
            ->select(
                'id',
                'no_document',
                'pelanggan_ID',
                'data_lama',
                'sales_id',
                'biaya_akhir',
                'total_ppn',
                'total_pph',
                'created_at'
            )
            ->whereIn('no_document', $quotationNumbers)
            ->where('flag_status', 'sp')
            ->where('is_active', 1)
            ->get();

        $dataKontrakNonSp = QuotationKontrakH::whereIn('no_document', $quotationNumbers)
                ->where('flag_status', '<>', 'sp')
                ->where('is_active', 1)
                ->count();

        $dataNonSp += $dataKontrakNonSp;

        printf("\n[UpdateForecastSP] KONTRAK fetched: %d data", $dataKontrak->count());

        // Fetch data NON KONTRAK
        printf("\n[UpdateForecastSP] Fetching data NON KONTRAK...");
        $dataNonKontrak = QuotationNonKontrak::with(['dailyQsd', 'sales', 'pelanggan'])
            ->select(
                'id',
                'no_document',
                'pelanggan_ID',
                'data_lama',
                'sales_id',
                'biaya_akhir',
                'total_ppn',
                'total_pph',
                'created_at'
            )
            ->whereIn('no_document', $quotationNumbers)
            ->where('is_active', 1)
            ->where('flag_status', 'sp')
            ->get();

        $dataNonKontrakNonSp = QuotationNonKontrak::whereIn('no_document', $quotationNumbers)
                ->where('flag_status', '<>', 'sp')
                ->where('is_active', 1)
                ->count();

        $dataNonSp += $dataNonKontrakNonSp;

        printf("\n[UpdateForecastSP] NON KONTRAK fetched: %d data", $dataNonKontrak->count());

        // Create lookup map for jadwal
        $jadwalMap = $jadwalFiltered->groupBy('no_document');

        printf("\n[UpdateForecastSP] Processing KONTRAK data...");

        $haveQsd = [];
        $lunas = [];
        // Proses data kontrak dengan flatMap untuk multiple rows
        $kontrakItems = $dataKontrak->flatMap(function ($item) use ($jadwalMap, &$haveQsd, &$lunas) {
            if (empty($item->detail)) {
                return collect();
            }

            return $item->detail->map(function ($detail) use ($item, $jadwalMap , &$haveQsd, &$lunas) {
                // Filter untuk data_lama
                $isLunas = false;

                $dailyQsdIsExist = $item->dailyQsd->contains(function ($dailyQsd) use ($detail) {
                    return $dailyQsd->periode == $detail['periode_kontrak'];
                });

                if (!empty($item->data_lama) && $dailyQsdIsExist) {

                    $haveQsd[] = $item->no_document;

                    $isLunas = $item->dailyQsd->contains(function ($dailyQsd) use ($detail) {
                        return $dailyQsd->periode == $detail['periode_kontrak']
                            && !empty($dailyQsd->is_lunas);
                    });

                }
                
                if ($dailyQsdIsExist && $isLunas) {
                    $lunas[] = $item->no_document;
                    return null;
                }

                // Cari tanggal sampling minimal dari jadwal yang sudah difilter
                $tanggalMinSampling = null;
                if (isset($jadwalMap[$item->no_document])) {
                    $jadwalItem = $jadwalMap[$item->no_document]
                        ->where('periode', $detail['periode_kontrak'])
                        ->first();
                    $tanggalMinSampling = $jadwalItem ? $jadwalItem->tanggal : null;
                }

                return [
                    'id' => $item->id,
                    'pelanggan_ID' => $item->pelanggan_ID,
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
                    'status_quotation' => 'kontrak'
                ];
            })->filter();
        });

        printf("\n[UpdateForecastSP] KONTRAK processed: %d detail rows", $kontrakItems->count());

        printf("\n[UpdateForecastSP] Processing NON KONTRAK data...");

        // Proses data non kontrak
        $nonKontrakItems = $dataNonKontrak->map(function ($item) use ($jadwalMap , &$haveQsd, &$lunas) {
            // Cek jika ada data lama maka lakukan pengecekan jika punya dailyqsd
            if (!empty($item->data_lama)) {
                $dailyQsdIsExist = $item->dailyQsd->isNotEmpty();
                $isLunas = $item->dailyQsd->contains(function ($dailyQsd) {
                    $haveQsd[] = $dailyQsd->no_document;
                    return $dailyQsd->is_lunas;
                });

                if ($dailyQsdIsExist && $isLunas) {
                    $lunas[] = $item->no_document;
                    return null;
                }
            }

            // Cari tanggal sampling minimal dari jadwal yang sudah difilter
            $tanggalMinSampling = null;
            if (isset($jadwalMap[$item->no_document])) {
                $jadwalItem = $jadwalMap[$item->no_document]
                    ->whereNull('periode')
                    ->first();
                $tanggalMinSampling = $jadwalItem ? $jadwalItem->tanggal : null;
            }

            return [
                'id' => $item->id,
                'pelanggan_ID' => $item->pelanggan_ID,
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
                'status_quotation' => 'non_kontrak'
            ];
        })->filter();

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

        ForecastSP::truncate();

        // printf("\n[UpdateForecastSP] Truncated table");

        printf("\n[UpdateForecastSP] Preparing data for insertion...");

        $dataToInsert = $formattedData->map(function ($row) {
            $noOrder = null;
            if ($row['data_lama'] && isset($row['data_lama']->no_order)) {
                $noOrder = $row['data_lama']->no_order;
            }

            // Buat UUID unik berdasarkan no_document + periode
            $uuidString = trim($row['no_document']) . '|' . trim($row['periode'] ?? 'single');
            $uuid = (new Crypto())->encrypt($uuidString);

            return [
                'uuid' => $uuid,
                'pelanggan_ID' => $row['pelanggan_ID'],
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
                'status_customer' => null, // Will be updated later
                'created_at' => $row['created_at'],
                'status_quotation' => $row['status_quotation']
            ];
        })->filter()->values()->toArray();

        printf("\n[UpdateForecastSP] Upserting %d rows to forecast_sp...", count($dataToInsert));

        // Upsert dengan chunk untuk menghindari memory issue
        $chunks = array_chunk($dataToInsert, 1000);
        $totalUpserted = 0;
        
        foreach ($chunks as $chunk) {
            ForecastSP::upsert($chunk, ['uuid'], [
                'pelanggan_ID',
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
                'created_at',
                'status_quotation'
            ]);
            $totalUpserted += count($chunk);
            printf("\n[UpdateForecastSP] Upserted %d rows...", $totalUpserted);
        }

        printf("\n[UpdateForecastSP] Upsert completed: %d rows", $totalUpserted);

        // Update status_customer (new/exist) based on pelanggan_ID and order
        printf("\n[UpdateForecastSP] Updating status_customer...");
        
        DB::statement("
            UPDATE forecast_sp f
            JOIN (
                SELECT 
                    uuid, 
                    ROW_NUMBER() OVER (
                        PARTITION BY pelanggan_ID
                        ORDER BY 
                            COALESCE(tanggal_sampling_min, '9999-12-31'), 
                            CAST(SUBSTRING(no_order, 7, 2) AS UNSIGNED), 
                            CAST(SUBSTRING(no_order, 9, 2) AS UNSIGNED), 
                            uuid
                    ) AS rn
                FROM forecast_sp
                WHERE status_customer IS NULL
            ) x ON x.uuid = f.uuid
            SET f.status_customer = IF(x.rn = 1, 'new', 'exist')
            WHERE f.status_customer IS NULL
        ");

        printf("\n[UpdateForecastSP] status_customer updated");

        // Verifikasi data
        printf("\n[UpdateForecastSP] Verifying data...");
        $forecastCount = ForecastSP::count();
        $newCount = ForecastSP::where('status_customer', 'new')->count();
        $existCount = ForecastSP::where('status_customer', 'exist')->count();
        
        printf("\n[UpdateForecastSP] Pelanggan ID NULL Total Rows: %d", ForecastSP::where('pelanggan_ID', null)->count());
        printf("\n[UpdateForecastSP] Pelanggan ID NOT NULL Total Rows: %d", ForecastSP::whereNotNull('pelanggan_ID')->count());
        printf("\n[UpdateForecastSP] Records in forecast_sp table: %d", $forecastCount);
        printf("\n[UpdateForecastSP] - New customers: %d", $newCount);
        printf("\n[UpdateForecastSP] - Existing customers: %d", $existCount);
        printf("\n[UpdateForecastSP] - Total have qsd: %d", count($haveQsd));
        printf("\n[UpdateForecastSP] - Total lunas: %d", count($lunas));
        printf("\n[UpdateForecastSP] - Quotation Non SP: %d", $dataNonSp);

        $totalTime = microtime(true) - $startTime;

        printf("\n[UpdateForecastSP] Finished. Total time: %.3f seconds", $totalTime);
    }
}