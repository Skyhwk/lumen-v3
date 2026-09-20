<?php

namespace App\Console\Commands;

use App\Services\SamplerTrackingTroubleService;
use App\Services\SamplerTrackingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CollectSamplerTrackingTroubles extends Command
{
    protected $signature = 'sampler-tracking:collect-troubles {--date= : Tanggal deadline (YYYY-MM-DD), default kemarin (WIB)} {--sampler-id=* : Batasi ke sampler ID tertentu} {--sync-today : Sinkron activity hari ini sebelum collect trouble}';
    protected $description = 'Catat activity sampler yang belum selesai (trouble). Activity sebelum SAMPLER_TRACKING_TROUBLE_START_DATE tidak diproses.';

    public function handle(SamplerTrackingTroubleService $service, SamplerTrackingService $trackingService)
    {
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

        $startDate = SamplerTrackingTroubleService::startDate();
        $date = $this->option('date') ?: $now->copy()->subDay()->toDateString();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || Carbon::parse($date)->toDateString() !== $date || $date >= $now->toDateString()) {
            $this->error('Tanggal harus tanggal lampau dengan format YYYY-MM-DD.');
            return 1;
        }
        if ($date < $startDate) {
            $this->warn("Collect trouble dilewati: deadline {$date} sebelum tanggal mulai ({$startDate}). Tidak ada activity yang dibaca.");
            return 0;
        }
        $samplerIds = $this->option('sampler-id');
        foreach ($samplerIds as $id) {
            if (!ctype_digit((string) $id) || (int) $id < 1) {
                $this->error('Sampler ID harus bilangan bulat positif.');
                return 1;
            }
        }
        $this->line("Deadline trouble: {$date} | Activity dari {$startDate} s/d {$date}.");
        $this->info('Trouble baru: ' . $service->collect($date, $samplerIds));
        return 0;
    }
}
