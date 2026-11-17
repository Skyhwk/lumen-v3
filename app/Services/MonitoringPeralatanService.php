<?php

namespace App\Services;

use App\Models\MonitoringPeralatan;
use App\Models\Peralatan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class MonitoringPeralatanService
{
    public const STATUS_SEDANG_DIGUNAKAN = 'Sedang Digunakan';

    /**
     * Sinkron status monitoring dari bawaan aktual IO (kode_aset → id_peralatan).
     *
     * @param  array<int, array>  $beforePeralatan
     * @param  array<int, array>  $afterPeralatan
     */
    public static function syncSedangDigunakanFromPeralatan(
        array $beforePeralatan,
        array $afterPeralatan,
        ?string $actor = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): void {
        if (
            !Schema::hasTable('monitoring_peralatan')
            || !Schema::hasColumn('monitoring_peralatan', 'id_peralatan')
            || !Schema::hasTable('peralatan')
        ) {
            return;
        }

        $beforeIds = self::resolvePeralatanIds($beforePeralatan);
        $afterIds = self::resolvePeralatanIds($afterPeralatan);
        if ($beforeIds === [] && $afterIds === []) {
            return;
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $status = self::STATUS_SEDANG_DIGUNAKAN;
        $statusKey = mb_strtolower($status);
        $hasStart = Schema::hasColumn('monitoring_peralatan', 'start_date');
        $hasEnd = Schema::hasColumn('monitoring_peralatan', 'end_date');
        $start = $startDate ? Carbon::parse($startDate)->format('Y-m-d') : null;
        $end = $endDate ? Carbon::parse($endDate)->format('Y-m-d') : $start;

        foreach ($afterIds as $idPeralatan) {
            $existing = MonitoringPeralatan::query()
                ->where('is_active', true)
                ->where('id_peralatan', $idPeralatan)
                ->whereRaw('LOWER(TRIM(status_alat)) = ?', [$statusKey])
                ->first();

            if ($existing) {
                $dirty = false;
                if ($hasStart && $start !== null && (string) $existing->start_date !== $start) {
                    $existing->start_date = $start;
                    $dirty = true;
                }
                if ($hasEnd && $end !== null && (string) $existing->end_date !== $end) {
                    $existing->end_date = $end;
                    $dirty = true;
                }
                if ($dirty) {
                    $existing->updated_by = $actor;
                    $existing->updated_at = $now;
                    $existing->save();
                }
                continue;
            }

            $row = new MonitoringPeralatan();
            $row->id_peralatan = $idPeralatan;
            $row->status_alat = $status;
            if ($hasStart) {
                $row->start_date = $start;
            }
            if ($hasEnd) {
                $row->end_date = $end;
            }
            $row->is_active = true;
            $row->created_by = $actor;
            $row->created_at = $now;
            $row->updated_at = $now;
            $row->save();
        }

        $removedIds = array_values(array_diff($beforeIds, $afterIds));
        if ($removedIds === []) {
            return;
        }

        MonitoringPeralatan::query()
            ->where('is_active', true)
            ->whereIn('id_peralatan', $removedIds)
            ->whereRaw('LOWER(TRIM(status_alat)) = ?', [$statusKey])
            ->update([
                'is_active' => false,
                'deleted_by' => $actor,
                'deleted_at' => $now,
                'updated_by' => $actor,
                'updated_at' => $now,
            ]);
    }

    /**
     * @param  array<int, array>  $peralatan
     * @return list<int>
     */
    public static function resolvePeralatanIds(array $peralatan): array
    {
        $kodes = [];
        foreach (JadwalMobilisasiService::normalizePeralatanList($peralatan) as $item) {
            $list = $item['kode_aset_list'] ?? [];
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $kode) {
                $kode = trim((string) $kode);
                if ($kode !== '') {
                    $kodes[] = $kode;
                }
            }
        }

        $kodes = array_values(array_unique($kodes));
        if ($kodes === []) {
            return [];
        }

        return Peralatan::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereIn('kode_aset', $kodes)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
