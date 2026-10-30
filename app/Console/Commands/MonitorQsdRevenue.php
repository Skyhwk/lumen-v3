<?php

namespace App\Console\Commands;

use App\Services\LogTransactionQsdService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorQsdRevenue extends Command
{
    protected $signature = 'qsd:monitor-revenue {--periode= : Target bulan format YYYY-MM (misal: 2026-06)}';
    protected $description = 'Monitor pergerakan nilai daily_qsd (Ledger Saldo Berjalan)';

    public function handle()
    {
        $targetPeriode = $this->option('periode');
        $batchId = uniqid('MONITOR-REV-');
        $now = Carbon::now('Asia/Jakarta');

        $periodeInfo = $targetPeriode ? " Periode: {$targetPeriode}" : " Bulan Berjalan";
        $this->info("[$batchId] Memulai monitoring pergerakan data revenue{$periodeInfo}...");

        try {
            Log::channel('monitor_log_qsd_revenue')->info("[$batchId] Memulai monitoring", [
                'waktu'   => $now->toDateTimeString(),
                'periode' => $targetPeriode ?? 'current'
            ]);
        } catch (\Throwable $e) {}

        try {
            $countInsert = LogTransactionQsdService::syncRevenue($targetPeriode);

            $this->info("[$batchId] Selesai! Terdeteksi {$countInsert} pergerakan.");
            try {
                Log::channel('monitor_log_qsd_revenue')->info("[$batchId] Selesai", ['pergerakan' => $countInsert]);
            } catch (\Throwable $e) {}

        } catch (\Throwable $e) {
            $this->error("[$batchId] Kesalahan fatal sistem: " . $e->getMessage());
            $this->error("File: " . $e->getFile() . " (Baris: " . $e->getLine() . ")");
            try {
                Log::channel('monitor_log_qsd_revenue')->error("[$batchId] FATAL ERROR", [
                    'pesan' => $e->getMessage(),
                    'file'  => $e->getFile(),
                    'baris' => $e->getLine()
                ]);
            } catch (\Throwable $ex) {}
        }
    }
}
