<?php

namespace App\Services;

use App\Models\MonitoringPeralatan;
use App\Models\Peralatan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonitoringPeralatanService
{
    public const STATUS_SEDANG_DIGUNAKAN = 'Sedang Digunakan';
    public const STATUS_TERSEDIA = 'Tersedia';

    /**
     * Sinkron monitoring dari bawaan IO.
     * - Alat baru / ditambah → INSERT status Sedang Digunakan (is_active=1)
     * - Alat dilepas / diganti → UPDATE record aktif: is_active=0 + status_alat=Tersedia
     * - Tidak mengisi start_date / end_date (bukan ranah IO)
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
        // $startDate / $endDate sengaja diabaikan — kolom tanggal bukan ranah sinkron IO.
        unset($startDate, $endDate);

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
        $statusUsed = self::STATUS_SEDANG_DIGUNAKAN;
        $statusFree = self::STATUS_TERSEDIA;
        $statusUsedKey = mb_strtolower($statusUsed);

        $addedIds = array_values(array_diff($afterIds, $beforeIds));
        $removedIds = array_values(array_diff($beforeIds, $afterIds));

        // 1) Lepas alat: nonaktifkan + status jadi Tersedia (record history tetap ada).
        if ($removedIds !== []) {
            MonitoringPeralatan::query()
                ->where('is_active', true)
                ->whereIn('id_peralatan', $removedIds)
                ->whereRaw('LOWER(TRIM(status_alat)) = ?', [$statusUsedKey])
                ->update([
                    'status_alat' => $statusFree,
                    'is_active' => false,
                    'deleted_by' => $actor,
                    'deleted_at' => $now,
                    'updated_by' => $actor,
                    'updated_at' => $now,
                ]);
        }

        // 2) Alat baru: INSERT Sedang Digunakan (hanya status_alat; tanpa start/end date).
        foreach ($addedIds as $idPeralatan) {
            $existsActive = MonitoringPeralatan::query()
                ->where('is_active', true)
                ->where('id_peralatan', $idPeralatan)
                ->whereRaw('LOWER(TRIM(status_alat)) = ?', [$statusUsedKey])
                ->exists();
            if ($existsActive) {
                continue;
            }

            $row = new MonitoringPeralatan();
            $row->id_peralatan = $idPeralatan;
            $row->status_alat = $statusUsed;
            $row->is_active = true;
            $row->created_by = $actor;
            $row->created_at = $now;
            $row->updated_at = $now;
            $row->save();
        }
    }

    /**
     * Map id_peralatan → monitoring terbaru (ORDER BY id DESC), tanpa filter is_active.
     *
     * @param  list<int>  $peralatanIds
     * @return array<int, array{status_alat: string, start_date: ?string, end_date: ?string}>
     */
    public static function latestMonitoringByPeralatanIds(array $peralatanIds): array
    {
        $peralatanIds = array_values(array_unique(array_filter(array_map('intval', $peralatanIds), fn ($id) => $id > 0)));
        if (
            $peralatanIds === []
            || !Schema::hasTable('monitoring_peralatan')
            || !Schema::hasColumn('monitoring_peralatan', 'id_peralatan')
        ) {
            return [];
        }

        $latestIds = DB::table('monitoring_peralatan as mp')
            ->select(DB::raw('MAX(mp.id) as id'))
            ->whereIn('mp.id_peralatan', $peralatanIds)
            ->whereNotNull('mp.status_alat')
            ->whereRaw("TRIM(mp.status_alat) <> ''")
            ->groupBy('mp.id_peralatan')
            ->pluck('id')
            ->all();

        if ($latestIds === []) {
            return [];
        }

        $select = ['id_peralatan', 'status_alat'];
        $hasStart = Schema::hasColumn('monitoring_peralatan', 'start_date');
        $hasEnd = Schema::hasColumn('monitoring_peralatan', 'end_date');
        if ($hasStart) {
            $select[] = 'start_date';
        }
        if ($hasEnd) {
            $select[] = 'end_date';
        }

        $rows = MonitoringPeralatan::query()
            ->whereIn('id', $latestIds)
            ->get($select);

        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row->id_peralatan;
            $status = trim((string) ($row->status_alat ?? ''));
            if ($id <= 0 || $status === '') {
                continue;
            }
            $start = ($hasStart && $row->start_date)
                ? Carbon::parse($row->start_date)->format('Y-m-d')
                : null;
            $end = ($hasEnd && $row->end_date)
                ? Carbon::parse($row->end_date)->format('Y-m-d')
                : null;
            $map[$id] = [
                'status_alat' => $status,
                'start_date' => $start,
                'end_date' => $end,
            ];
        }

        return $map;
    }

    /**
     * Alias ringkas: hanya status terbaru.
     *
     * @param  list<int>  $peralatanIds
     * @return array<int, string>
     */
    public static function latestStatusByPeralatanIds(array $peralatanIds): array
    {
        $map = [];
        foreach (self::latestMonitoringByPeralatanIds($peralatanIds) as $id => $row) {
            $map[(int) $id] = (string) ($row['status_alat'] ?? '');
        }

        return $map;
    }

    /**
     * Apakah alat masih "sibuk" untuk pemilihan IO.
     * Hanya berdasarkan status_alat (start/end date bukan ranah IO):
     * - Tersedia / kosong → bebas
     * - Selain Tersedia → sibuk sampai status diubah jadi Tersedia
     *
     * Param $tanggal dipertahankan untuk kompatibilitas pemanggilan lama (diabaikan).
     */
    public static function isBusyOnTanggal(?array $monitoring, string $tanggal = ''): bool
    {
        unset($tanggal);

        if ($monitoring === null) {
            return false;
        }

        $status = mb_strtolower(trim((string) ($monitoring['status_alat'] ?? '')));
        if ($status === '' || $status === mb_strtolower(self::STATUS_TERSEDIA)) {
            return false;
        }

        return true;
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

        $query = Peralatan::query()
            ->where('is_active', true)
            ->whereIn('kode_aset', $kodes);
        if (Schema::hasColumn('peralatan', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
