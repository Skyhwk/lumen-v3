<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Jadwal;
use App\Models\SamplingPlan;
use App\Models\OrderHeader;
use App\Services\SamplerTrackingService;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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

        $noQuotationsWithOrder = $this->quotationsWithActiveOrder($noQuotations);

        $expiredWithOrderQuery = (clone $expiredQuery)
            ->whereIn('no_quotation', $noQuotationsWithOrder);

        $expiredWithoutOrderQuery = (clone $expiredQuery)
            ->where(function ($query) use ($noQuotationsWithOrder) {
                $query->whereNotIn('no_quotation', $noQuotationsWithOrder)
                    ->orWhereNull('no_quotation');
            });

        $fixCount = (clone $expiredWithOrderQuery)->count();
        $expiredTodayWithoutOrderCount = (clone $expiredWithoutOrderQuery)->count();

        $deactivateQuery = $this->expandDeactivateQueryWithParsialBookings(
            $expiredWithoutOrderQuery,
            $today
        );
        $deactivateCount = (clone $deactivateQuery)->count();

        $samplingIdsToCheck = (clone $deactivateQuery)
            ->whereNotNull('id_sampling')
            ->pluck('id_sampling')
            ->unique();

        $deactivatedJadwalRows = collect();
        $deactivatedSamplingPlanIds = collect();
        $expiredTodaySeedIds = (clone $expiredWithoutOrderQuery)->pluck('id');

        if ($deactivateCount > 0) {
            $deactivatedJadwalRows = (clone $deactivateQuery)->get([
                'id',
                'no_quotation',
                'tanggal',
                'parsial',
                'id_sampling',
                'nama_perusahaan',
                'sampler',
                'status',
            ]);
        }

        DB::transaction(function () use ($expiredWithOrderQuery, $deactivateQuery, $now, $samplingIdsToCheck, $fixCount, $deactivateCount, &$deactivatedSamplingPlanIds) {
            if ($fixCount > 0) {
                (clone $expiredWithOrderQuery)->update([
                    'status' => '1',
                ]);
            }

            if ($deactivateCount > 0) {
                (clone $deactivateQuery)->update([
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
                $deactivatedSamplingPlanIds = $samplingIdsToDeactivate->values();
                SamplingPlan::whereIn('id', $samplingIdsToDeactivate)->update([
                    'is_active' => 0,
                    'status' => 0,
                    'status_jadwal' => 'cancel',
                    'deleted_by' => 'system',
                    'deleted_at' => $now,
                ]);
            }
        });

        if ($deactivateCount > 0) {
            $this->logDeactivatedBookings(
                $today,
                $now,
                $deactivatedJadwalRows,
                $expiredTodaySeedIds,
                $deactivatedSamplingPlanIds
            );
        }

        if ($fixCount > 0) {
            $this->info("Berhasil memperbaiki {$fixCount} jadwal (status=1) karena sudah ada di order header.");
        }

        if ($deactivateCount > 0) {
            $parsialExtra = $deactivateCount - $expiredTodayWithoutOrderCount;
            $message = "Berhasil menonaktifkan {$deactivateCount} booking expired.";
            if ($parsialExtra > 0) {
                $message .= " (termasuk {$parsialExtra} jadwal parsial terkait yang masih booking).";
            }
            $this->info($message);
        }
        $this->info('Memperbaiki status jadwal booking aktif dari hari ini ke depan...');
        $this->fixingStatus();
        $this->info('Selesai memperbaiki status jadwal booking aktif dari hari ini ke depan.');
        $this->info('Sinkronisasi ulang tracking sampler hari ini...');
        app(SamplerTrackingService::class)->sync($today);
        $this->info('Selesai sinkronisasi ulang tracking sampler hari ini.');
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

        $noQuotationsWithOrder = $this->quotationsWithActiveOrder($noQuotations);

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

    /**
     * Satu slot booking expired tanpa order → nonaktifkan seluruh rangkaian parsial
     * (induk + anak) yang masih status booking, tanggal hari ini ke depan, belum punya order.
     */
    private function expandDeactivateQueryWithParsialBookings(
        Builder $expiredWithoutOrderQuery,
        string $today
    ): Builder {
        $seedRows = (clone $expiredWithoutOrderQuery)->get(['id', 'parsial']);

        if ($seedRows->isEmpty()) {
            return $expiredWithoutOrderQuery;
        }

        $rootIds = $seedRows
            ->map(function ($row) {
                return $row->parsial ?: $row->id;
            })
            ->unique()
            ->values();

        if ($rootIds->isEmpty()) {
            return $expiredWithoutOrderQuery;
        }

        $groupQuery = Jadwal::query()
            ->where('is_active', 1)
            ->where('status', '0')
            ->where('tanggal', '>=', $today)
            ->where(function ($query) use ($rootIds) {
                $query->whereIn('id', $rootIds)
                    ->orWhereIn('parsial', $rootIds);
            });

        $groupQuotations = (clone $groupQuery)
            ->pluck('no_quotation')
            ->filter()
            ->unique();

        $noQuotationsWithOrder = $this->quotationsWithActiveOrder($groupQuotations);

        return $this->applyWithoutOrderFilter($groupQuery, $noQuotationsWithOrder);
    }

    private function quotationsWithActiveOrder(Collection $noQuotations): Collection
    {
        if ($noQuotations->isEmpty()) {
            return collect();
        }

        return OrderHeader::query()
            ->whereIn('no_document', $noQuotations)
            ->where('is_active', 1)
            ->pluck('no_document')
            ->unique();
    }

    private function applyWithoutOrderFilter(Builder $query, Collection $noQuotationsWithOrder): Builder
    {
        if ($noQuotationsWithOrder->isEmpty()) {
            return $query;
        }

        return $query->where(function ($inner) use ($noQuotationsWithOrder) {
            $inner->whereNotIn('no_quotation', $noQuotationsWithOrder)
                ->orWhereNull('no_quotation');
        });
    }

    private function logDeactivatedBookings(
        string $today,
        Carbon $now,
        Collection $jadwalRows,
        Collection $expiredTodaySeedIds,
        Collection $samplingPlanIds
    ): void {
        $seedIdSet = $expiredTodaySeedIds->flip();

        $jadwalPayload = $jadwalRows->map(function ($row) use ($seedIdSet) {
            return [
                'id' => $row->id,
                'no_quotation' => $row->no_quotation,
                'tanggal' => $row->tanggal,
                'parsial' => $row->parsial,
                'id_sampling' => $row->id_sampling,
                'nama_perusahaan' => $row->nama_perusahaan,
                'sampler' => $row->sampler,
                'status_sebelum' => $row->status,
                'sumber' => $seedIdSet->has($row->id) ? 'expired_hari_ini' : 'parsial_cascade',
            ];
        })->values()->all();

        Log::channel('deactivate_expired_bookings')->info('deactivate: booking expired dinonaktifkan oleh system', [
            'command' => 'deactivate',
            'tanggal_acuan' => $today,
            'run_at' => $now->toDateTimeString(),
            'total_jadwal' => count($jadwalPayload),
            'jadwal' => $jadwalPayload,
            'sampling_plan_ids' => $samplingPlanIds->values()->all(),
        ]);
    }
}
