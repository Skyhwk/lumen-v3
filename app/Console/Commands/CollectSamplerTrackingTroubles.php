<?php

namespace App\Console\Commands;

use App\Services\SamplerTrackingTroubleService;
use App\Services\SamplerTrackingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CollectSamplerTrackingTroubles extends Command
{
    protected $signature = 'sampler-tracking:collect-troubles {--date= : Deadline date, defaults to yesterday (WIB)} {--sampler-id=* : Optional sampler IDs for a scoped manual run} {--sync-today : Reconcile today activity before collecting troubles}';
    protected $description = 'Record unfinished sampler activities without clearing or modifying their events.';

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

        $date = $this->option('date') ?: $now->copy()->subDay()->toDateString();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || Carbon::parse($date)->toDateString() !== $date || $date >= $now->toDateString()) {
            $this->error('Tanggal harus tanggal lampau dengan format YYYY-MM-DD.');
            return 1;
        }
        $samplerIds = $this->option('sampler-id');
        foreach ($samplerIds as $id) {
            if (!ctype_digit((string) $id) || (int) $id < 1) {
                $this->error('Sampler ID harus bilangan bulat positif.');
                return 1;
            }
        }
        $this->info('Trouble baru: ' . $service->collect($date, $samplerIds));
        return 0;
    }
}
