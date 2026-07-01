<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Jadwal;
use App\Models\SamplingPlan;
use App\Models\OrderHeader;
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


        $noQuotations = (clone $expiredQuery)
            ->pluck('no_quotation')
            ->filter()
            ->unique();

        $noQuotationsWithOrder = OrderHeader::query()
            ->whereIn('no_document', $noQuotations)
            ->where('is_active', 1)
            ->pluck('no_document')
            ->unique();

        $expiredWithOrderQuery = (clone $expiredQuery)
            ->whereIn('no_quotation', $noQuotationsWithOrder);

        $expiredWithoutOrderQuery = (clone $expiredQuery)
            ->where(function ($query) use ($noQuotationsWithOrder) {
                $query->whereNotIn('no_quotation', $noQuotationsWithOrder)
                    ->orWhereNull('no_quotation');
            });

        $fixCount = (clone $expiredWithOrderQuery)->count();
        $deactivateCount = (clone $expiredWithoutOrderQuery)->count();

        $samplingIdsToCheck = (clone $expiredWithoutOrderQuery)
            ->whereNotNull('id_sampling')
            ->pluck('id_sampling')
            ->unique();

        DB::transaction(function () use ($expiredWithOrderQuery, $expiredWithoutOrderQuery, $now, $samplingIdsToCheck, $fixCount, $deactivateCount) {
            if ($fixCount > 0) {
                (clone $expiredWithOrderQuery)->update([
                    'status' => '1',
                ]);
            }

            if ($deactivateCount > 0) {
                (clone $expiredWithoutOrderQuery)->update([
                    'is_active' => 0,
                    'canceled_by' => 'system',
                    'canceled_at' => $now,
                ]);
            }

            if ($samplingIdsToCheck->isEmpty()) {
                return;
            }

            $samplingIdsWithActiveJadwal = Jadwal::query()
                ->whereIn('id_sampling', $samplingIdsToCheck)
                ->where('is_active', 1)
                ->pluck('id_sampling')
                ->unique();

            $samplingIdsToDeactivate = $samplingIdsToCheck->diff($samplingIdsWithActiveJadwal);

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

        if ($fixCount > 0) {
            $this->info("Berhasil memperbaiki {$fixCount} jadwal (status=1) karena sudah ada di order header.");
        }

        if ($deactivateCount > 0) {
            $this->info("Berhasil menonaktifkan {$deactivateCount} booking expired.");
        }
        $this->info('Memperbaiki status jadwal booking aktif dari hari ini ke depan...');
        $this->fixingStatus();
        $this->info('Selesai memperbaiki status jadwal booking aktif dari hari ini ke depan.');
        return 0;
    }

    public function fixingStatus()
    {
        $today = Carbon::today()->toDateString();

        $bookingQuery = Jadwal::query()
            ->where('is_active', 1)
            ->where('status', '0')
            ->where('tanggal', '>=', $today);

        $bookingCount = (clone $bookingQuery)->count();

        if ($bookingCount === 0) {
            $this->info('Tidak ada jadwal booking aktif dari hari ini ke depan.');
            return 0;
        }

        $noQuotations = (clone $bookingQuery)
            ->pluck('no_quotation')
            ->filter()
            ->unique();

        if ($noQuotations->isEmpty()) {
            $this->info('Tidak ada jadwal booking dengan no quotation yang perlu diperbaiki.');
            return 0;
        }

        $noQuotationsWithOrder = OrderHeader::query()
            ->whereIn('no_document', $noQuotations)
            ->where('is_active', 1)
            ->pluck('no_document')
            ->unique();

        if ($noQuotationsWithOrder->isEmpty()) {
            $this->info('Tidak ada jadwal booking yang sudah terdaftar di order header.');
            return 0;
        }

        $toFixQuery = (clone $bookingQuery)
            ->whereIn('no_quotation', $noQuotationsWithOrder);

        $fixCount = (clone $toFixQuery)->count();

        if ($fixCount === 0) {
            $this->info('Tidak ada jadwal booking yang perlu diperbaiki.');
            return 0;
        }

        DB::transaction(function () use ($toFixQuery) {
            (clone $toFixQuery)->update([
                'status' => '1',
            ]);
        });

        $this->info("Berhasil memperbaiki {$fixCount} jadwal (status=0 → status=1) karena sudah ada di order header.");
        return 0;
    }
}
