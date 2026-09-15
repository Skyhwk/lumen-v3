<?php

namespace App\Console\Commands;

use App\Services\SamplerTrackingTroubleService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CollectSamplerTrackingTroubles extends Command
{
    protected $signature = 'sampler-tracking:collect-troubles {--date= : Deadline date, defaults to yesterday (WIB)} {--sampler-id=* : Optional sampler IDs for a scoped manual run}';
    protected $description = 'Record unfinished sampler activities without clearing or modifying their events.';

    public function handle(SamplerTrackingTroubleService $service)
    {
        $date = $this->option('date') ?: Carbon::now('Asia/Jakarta')->subDay()->toDateString();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || Carbon::parse($date)->toDateString() !== $date || $date >= Carbon::now('Asia/Jakarta')->toDateString()) {
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
