<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncQsdForecast extends Command
{
    protected $signature = 'qsd:sync-forecast';
    protected $description = 'Migrate forecast_sp to log filtering only by tanggal_sampling_min (Current Month)';

    public function handle()
    {
        $this->info('Memulai migrasi data forecast_sp berdasarkan tanggal_sampling_min bulan ini...');

        $count = 0;
        $now = Carbon::now();
        
        $currentMonth = $now->format('m');
        $currentYear = $now->format('Y');
        $currentPeriodStr = $now->format('Y-m');

        $lastQuotation = null;
        $runningTotal = 0;

        // --- FILTER DATABASE: HANYA BERDASARKAN TANGGAL SAMPLING MIN ---
        DB::table('forecast_sp')
            ->whereNotNull('tanggal_sampling_min')
            ->whereMonth('tanggal_sampling_min', $currentMonth)
            ->whereYear('tanggal_sampling_min', $currentYear)
            ->orderBy('no_quotation')
            ->orderBy('created_at')
            ->chunk(500, function ($records) use (&$count, $now, $currentPeriodStr, &$lastQuotation, &$runningTotal) {
                $insertData = [];

                foreach ($records as $row) {
                    
                    // Gunakan periode dari row jika ada, jika tidak pakai string periode bulan ini
                    $periodeFix = $row->periode; 
                    
                    $currentRevenue = (float) $row->revenue_forecast;

                    // --- LOGIC AKUMULASI KESELURUHAN ---
                    if ($lastQuotation !== $row->no_quotation) {
                        $lastQuotation = $row->no_quotation;
                    }

                    $runningTotal += $currentRevenue;

                    $insertData[] = [
                        'no_penawaran'     => $row->no_quotation,
                        'periode'          => $periodeFix, // Akan terisi bulan/tahun untuk Kontrak, dan NULL untuk Non-Kontrak
                        'tanggal_sampling_min'          => $row->tanggal_sampling_min, // Akan terisi bulan/tahun untuk Kontrak, dan NULL untuk Non-Kontrak
                        'revenue_forecast' => $currentRevenue,       
                        'status'           => 'penambahan',          
                        'total'            => $runningTotal,         
                        'created_at'       => $now,
                    ];
                }

                if (!empty($insertData)) {
                    DB::table('qsd_forecast_transaction_log')->insert($insertData);
                    $count += count($insertData);
                    
                    $this->info("Berhasil memindahkan {$count} baris sejauh ini...");
                }
            });

        $this->info("Migrasi selesai! Total data forecast bulan ini yang dipindahkan: {$count} baris.");
    }
}