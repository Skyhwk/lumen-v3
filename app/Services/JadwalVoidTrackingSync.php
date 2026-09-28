<?php

namespace App\Services;

use App\Models\Jadwal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Sinkronisasi sampler tracking setelah void jadwal dari menu jadwal (cancelJadwal).
 */
class JadwalVoidTrackingSync
{
    public function captureSnapshotForQuotation(string $quotation): Collection
    {
        return app(SamplerTrackingService::class)->snapshotSchedules($quotation);
    }

    /**
     * @param int[] $jadwalIds ID jadwal yang akan di-void (masih aktif saat snapshot)
     * @return array<string, Collection> no_quotation => snapshot jadwal aktif sebelum void
     */
    public function captureSnapshotsForJadwalIds(array $jadwalIds): array
    {
        if ($jadwalIds === []) {
            return [];
        }

        $quotations = Jadwal::whereIn('id', $jadwalIds)
            ->where('is_active', true)
            ->pluck('no_quotation')
            ->unique()
            ->filter()
            ->values()
            ->all();

        $snapshots = [];
        foreach ($quotations as $quotation) {
            $snapshots[$quotation] = $this->captureSnapshotForQuotation($quotation);
        }

        return $snapshots;
    }

    /** @param int[] $jadwalIds */
    public function captureVoidedRows(array $jadwalIds): Collection
    {
        if ($jadwalIds === []) {
            return collect();
        }

        return Jadwal::whereIn('id', $jadwalIds)->where('is_active', true)->get();
    }

    /**
     * @param array<string, Collection> $snapshotsByQuotation
     */
    public function apply(array $snapshotsByQuotation, Collection $voidedRowsBeforeVoid = null): void
    {
        $tracking = app(SamplerTrackingService::class);
        $voidedRowsBeforeVoid = $voidedRowsBeforeVoid ?: collect();

        foreach ($snapshotsByQuotation as $quotation => $before) {
            if (!$quotation || !$before instanceof Collection || $before->isEmpty()) {
                continue;
            }

            try {
                $tracking->syncAfterScheduleVoid($quotation, $before);
            } catch (\Throwable $exception) {
                Log::channel('sampling')->error('Gagal menonaktifkan sampler tracking setelah void jadwal.', [
                    'no_quotation' => $quotation,
                    'message' => $exception->getMessage(),
                    'line' => $exception->getLine(),
                    'file' => $exception->getFile(),
                ]);

                throw $exception;
            }
        }

        if ($voidedRowsBeforeVoid->isNotEmpty()) {
            try {
                $tracking->finalizeMenuJadwalVoid($voidedRowsBeforeVoid);
            } catch (\Throwable $exception) {
                Log::channel('sampling')->error('Gagal finalisasi tracking setelah void jadwal menu.', [
                    'message' => $exception->getMessage(),
                    'line' => $exception->getLine(),
                    'file' => $exception->getFile(),
                ]);

                throw $exception;
            }
        }
    }
}
