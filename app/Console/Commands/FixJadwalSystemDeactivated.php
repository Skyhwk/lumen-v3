<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Jadwal;
use App\Models\SamplingPlan;
use App\Models\OrderHeader;
use Carbon\Carbon;
use DB;

class FixJadwalSystemDeactivated extends Command
{
    protected $signature = 'fixjadwalsystem {--date= : Tanggal canceled_at (Y-m-d), default hari ini}';
    protected $description = 'Pulihkan jadwal yang dimatikan system (deactivate pagi) padahal sudah ada order header';

    public function handle()
    {
        $targetDate = $this->option('date') ?: Carbon::today()->toDateString();

        $deactivatedQuery = Jadwal::query()
            ->where('is_active', 0)
            ->where('canceled_by', 'system')
            ->whereDate('canceled_at', $targetDate);

        $deactivatedCount = (clone $deactivatedQuery)->count();

        if ($deactivatedCount === 0) {
            $this->info("Tidak ada jadwal yang dinonaktifkan system pada tanggal {$targetDate}.");
            return 0;
        }

        $noQuotations = (clone $deactivatedQuery)
            ->pluck('no_quotation')
            ->filter()
            ->unique();

        if ($noQuotations->isEmpty()) {
            $this->info('Tidak ada jadwal dengan no quotation yang perlu dicek.');
            return 0;
        }
        
        $noQuotationsWithOrder = OrderHeader::query()
            ->whereIn('no_document', $noQuotations)
            ->where('is_active', 1)
            ->pluck('no_document')
            ->unique();

        if ($noQuotationsWithOrder->isEmpty()) {
            $this->info('Tidak ada jadwal yang dinonaktifkan system tapi sudah punya order header.');
            return 0;
        }

        $toRestoreQuery = (clone $deactivatedQuery)
            ->whereIn('no_quotation', $noQuotationsWithOrder);

        $restoreCount = (clone $toRestoreQuery)->count();
        
        if ($restoreCount === 0) {
            $this->info('Tidak ada jadwal yang perlu dipulihkan.');
            return 0;
        }

        $samplingIds = (clone $toRestoreQuery)
            ->whereNotNull('id_sampling')
            ->pluck('id_sampling')
            ->unique();

        $samplingRestoredCount = 0;

        DB::transaction(function () use ($toRestoreQuery, $samplingIds, $targetDate, &$samplingRestoredCount) {
            (clone $toRestoreQuery)->update([
                'is_active' => 1,
                'status' => '1',
                'canceled_by' => null,
                'canceled_at' => null,
            ]);

            if ($samplingIds->isEmpty()) {
                return;
            }

            $samplingRestoredCount = SamplingPlan::query()
                ->whereIn('id', $samplingIds)
                ->where('is_active', 0)
                ->where('deleted_by', 'system')
                ->whereDate('deleted_at', $targetDate)
                ->update([
                    'is_active' => 1,
                    'status' => 1,
                    'status_jadwal' => 'jadwal',
                    'deleted_by' => null,
                    'deleted_at' => null,
                ]);
        });

        $this->info("Berhasil memulihkan {$restoreCount} jadwal yang salah dinonaktifkan system pada {$targetDate}.");
        $this->info("Sampling plan dipulihkan: {$samplingRestoredCount} data.");

        return 0;
    }
}
