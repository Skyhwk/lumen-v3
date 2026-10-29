<?php

namespace App\Console\Commands;

use App\Services\SamplerTrackingTroubleService;
use App\Services\SamplerTrackingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CollectSamplerTrackingTroubles extends Command
{
    protected $signature = 'activitysamplertrouble
        {--date= : Batas akhir deadline (YYYY-MM-DD), default kemarin (WIB)}
        {--sampler-id=* : Batasi ke sampler ID tertentu}
        {--sync-today : Sinkron activity hari ini sebelum collect trouble}
        {--prune-stale : Tutup trouble terbuka yang sudah tidak relevan (session kosong/hilang/selesai)}';
    protected $description = 'Cari trouble baru dari 15 tanggal sampling terakhir sampai batas deadline (default kemarin WIB).';

    public function handle(SamplerTrackingTroubleService $service, SamplerTrackingService $trackingService)
    {
        if ($this->option('prune-stale')) {
            $cleared = $service->pruneStaleOpenTroubles();
            $archived = $trackingService->pruneEmptyShellSessions();
            $this->info("Trouble stale di-clear: {$cleared}");
            $this->info("Session shell di-archive: {$archived}");

            return 0;
        }

        $now = Carbon::now('Asia/Jakarta');
        if ($this->option('sync-today')) {
            try {
                $sessions = $trackingService->sync($now->toDateString());
                $this->info('Activity hari ini tersinkron: ' . $sessions->count());
            } catch (\Throwable $exception) {
                $this->error('Sync activity hari ini gagal. Pengecekan trouble dibatalkan: ' . $exception->getMessage());
                return 1;
            }
        }

        $date = $this->option('date') ?: $now->copy()->subDay()->toDateString();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || Carbon::parse($date)->toDateString() !== $date || $date >= $now->toDateString()) {
            $this->error('Tanggal harus tanggal lampau dengan format YYYY-MM-DD.');
            return 1;
        }
        $startDate = SamplerTrackingTroubleService::startDate($date);
        $samplerIds = $this->option('sampler-id');
        foreach ($samplerIds as $id) {
            if (!ctype_digit((string) $id) || (int) $id < 1) {
                $this->error('Sampler ID harus bilangan bulat positif.');
                return 1;
            }
        }
        $this->line("Deadline trouble sampai: {$date} | Activity dari {$startDate} s/d {$date}.");
        $this->info('Trouble baru: ' . $service->collect($date, $samplerIds));
        return 0;
    }
}
