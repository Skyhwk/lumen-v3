<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\PointEarning;
use App\Models\DailyQsd;
use DB;

class CalculatePoinCustomer extends Command
{
    protected $signature = 'calculate:poin-customer';
    protected $description = 'Calculate poin customer';

    protected $basicPoint = 10000; // 10rb untuk 1 poin

    public function handle()
    {
        $startTime = microtime(true);

        $this->output->newLine();
        $this->info("  START CALCULATING POIN CUSTOMER  ");
        $this->info("  Time: " . Carbon::now()->toDateTimeString());
        // =======================START CALCULATING POIN CUSTOMER================================
        $data = DailyQsd::where('is_lunas', 1)->where('is_point_calculated', 0)
        ->orderBy('no_invoice')
        ->chunk(1000, function ($rows) {

            foreach ($rows as $item) {
        
                DB::beginTransaction();
                try {

                    $daily = DailyQsd::where('uuid', $item->uuid)
                        ->first();
        
                    if (!$daily || $daily->is_point_calculated) {
                        $this->error("  Daily QSD not found or already calculated: " . $item->uuid);
                        DB::rollBack();
                        continue;
                    }
        
                    $exists = PointEarning::where('source_type', 'dailyqsd')
                        ->where('source_id', $item->uuid)
                        ->exists();
        
                    if ($exists) {
                        $this->error("  Point earning already exists: " . $item->uuid);
                        $daily->update(['is_point_calculated' => 1]);
                        DB::commit();
                        continue;
                    }
        
                    $poin = floor($item->revenue_invoice / $this->basicPoint);
                    if ($poin <= 0) {
                        $this->error("  Poin is less than 0: " . $item->uuid);
                        $daily->update(['is_point_calculated' => 1]);
                        DB::commit();
                        continue;
                    }
        
                    if (strpos($item->tanggal_pembayaran, ',') !== false) {
                        $tglPembayaran = explode(',', $item->tanggal_pembayaran);
                        $earnedAt = Carbon::parse(end($tglPembayaran));
                    } else {
                        $earnedAt = Carbon::parse($item->tanggal_pembayaran);
                    }

                    if($earnedAt <= '2025-01-01') {
                        $this->error("  Tanggal pembayaran is less than 2025-01-01: " . $item->pelanggan_ID);
                        $daily->update(['is_point_calculated' => 1]);
                        DB::commit();
                        continue;
                    }
        
                    PointEarning::create([
                        'customer_id'       => $item->pelanggan_ID,
                        'source_type'       => 'dailyqsd',
                        'source_id'         => $item->uuid,
                        'points'            => $poin,
                        'claimed_points'    => 0,
                        'expired_points'    => 0,
                        'earned_at'         => $earnedAt,
                        'claim_expired_at'  => $earnedAt->copy()->addYear(),
                        'tier_expired_at'   => $earnedAt->copy()->addYears(2),
                    ]);
        
                    $daily->update([
                        'is_point_calculated' => 1
                    ]);
        
                    DB::commit();
                    $this->info("  Point earning created successfully: " . $item->pelanggan_ID);
                    $this->info("  Points: " . $poin);
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $this->error("  Error: " . $e->getMessage());
                    if (str_contains($e->getMessage(), 'Duplicate')) {
                        continue;
                    }
                    throw $e;
                }
            }
        });
        // =========================END CALCULATING POIN CUSTOMER================================
        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $this->output->newLine();
        $this->info("========================================================================");
        $this->info("  POIN CUSTOMER CALCULATED SUCCESSFULLY  ");
        $this->info("  Total Execution Time: " . number_format($duration, 2) . " seconds");
        $this->info("========================================================================");
    }
}