<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Jadwal;
use App\Models\SamplingPlan;
use Carbon\Carbon;
use DB;

class DeactivateExpiredBookings extends Command
{
    private const CUTOFF_DATE = '2026-01-01';

    protected $signature = 'deactivate';
    protected $description = 'Deactivate expired bookings';

    public function handle()
    {
        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        $expiredQuery = Jadwal::query()
            ->where('is_active', 1)
            ->where('status', '0')
            ->where('tanggal', $today);
            // ->where('tanggal', '>=', self::CUTOFF_DATE);

        $jadwalCount = (clone $expiredQuery)->count();

        if ($jadwalCount === 0) {
            $this->info('Tidak ada booking expired yang perlu dinonaktifkan.');
            return 0;
        }

        $samplingIds = (clone $expiredQuery)
            ->whereNotNull('id_sampling')
            ->pluck('id_sampling')
            ->unique();

        DB::transaction(function () use ($expiredQuery, $now, $samplingIds) {
            (clone $expiredQuery)->update([
                'is_active' => 0,
                'canceled_by' => 'system',
                'canceled_at' => $now,
            ]);

            if ($samplingIds->isEmpty()) {
                return;
            }

            $samplingIdsWithActiveJadwal = Jadwal::query()
                ->whereIn('id_sampling', $samplingIds)
                ->where('is_active', 1)
                ->pluck('id_sampling')
                ->unique();

            $samplingIdsToDeactivate = $samplingIds->diff($samplingIdsWithActiveJadwal);

            if ($samplingIdsToDeactivate->isNotEmpty()) {
                SamplingPlan::whereIn('id', $samplingIdsToDeactivate)->update([
                    'is_active' => 0,
                    'status' => 0,
                    'status_jadwal' => 'cancel',
                    'deleted_by' => 'system',
                    'deleted_at' => $now,
                ]);
            }
        });

        $this->info("Berhasil menonaktifkan {$jadwalCount} booking expired.");
        return 0;
    }
}
