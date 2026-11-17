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

    public const STATUS_TERSEDIA = 'Tersedia';

    /**
     * Status efektif per tanggal kalender D.
     * Ber-periode: aktif hanya jika D dalam [start, end].
     * Rusak/Tersedia point-in-time: berlaku sampai event pengganti.
     * IO mengunci Sedang Digunakan untuk kode_aset pada tanggal D.
     */
    public static function buildContext(string $asOfDate): MonitoringPeralatanResolutionContext
    {
        $normalized = Carbon::parse($asOfDate)->format('Y-m-d');
        $ctx = new MonitoringPeralatanResolutionContext($normalized);

        if (Schema::hasTable('io_peralatan_tim')) {
            $locked = IoPeralatanTimService::usedKodeAsetByTanggal($normalized, null, 1);
            foreach ($locked['kodes'] ?? [] as $kode) {
                $kode = trim((string) $kode);
                if ($kode !== '') {
                    $ctx->ioKodes[$kode] = true;
                }
            }
            foreach ($locked['by_kode'] ?? [] as $kode => $owner) {
                $ctx->ioKodeOwners[(string) $kode] = (string) $owner;
            }
        }

        if (!Schema::hasTable('monitoring_peralatan')) {
            return $ctx;
        }

        $endOfDay = $normalized . ' 23:59:59';
        $rows = MonitoringPeralatan::query()
            ->where('is_active', true)
            ->whereNotNull('id_peralatan')
            ->where('created_at', '<=', $endOfDay)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'id_peralatan',
                'status_alat',
                'keterangan',
                'start_date',
                'end_date',
                'created_at',
            ]);

        foreach ($rows as $row) {
            $id = (int) $row->id_peralatan;
            if ($id <= 0) {
                continue;
            }
            $ctx->eventsByPeralatan[$id][] = $row;
        }

        return $ctx;
    }

    /**
     * @return array{
     *     status_alat: string,
     *     keterangan: string|null,
     *     start_date: string|null,
     *     end_date: string|null,
     *     id_monitoring: int|null,
     *     source: string
     * }
     */
    public static function resolveEffectiveStatus(
        int $idPeralatan,
        ?string $kodeAset,
        MonitoringPeralatanResolutionContext $ctx
    ): array {
        $default = [
            'status_alat' => self::STATUS_TERSEDIA,
            'keterangan' => null,
            'start_date' => null,
            'end_date' => null,
            'id_monitoring' => null,
            'source' => 'default',
        ];

        $kode = trim((string) $kodeAset);
        if ($kode !== '' && isset($ctx->ioKodes[$kode])) {
            return [
                'status_alat' => self::STATUS_SEDANG_DIGUNAKAN,
                'keterangan' => $ctx->ioKodeOwners[$kode] ?? null,
                'start_date' => $ctx->asOfDate,
                'end_date' => $ctx->asOfDate,
                'id_monitoring' => null,
                'source' => 'io',
            ];
        }

        $events = $ctx->eventsByPeralatan[$idPeralatan] ?? [];

        foreach ($events as $event) {
            if (self::eventCoversDate($event, $ctx->asOfDate)) {
                return self::mapResolvedEvent($event, 'monitoring_period');
            }
        }

        foreach ($events as $event) {
            if (!MonitoringPeralatan::requiresPeriod((string) ($event->status_alat ?? ''))) {
                return self::mapResolvedEvent($event, 'monitoring_point');
            }
        }

        return $default;
    }

    /**
     * @param  iterable<Peralatan>|null  $units
     * @return array{
     *     by_id: array<int, array<string, mixed>>,
     *     group_counts: array<string, array{tersedia: int, belum: int}>,
     *     ctx: MonitoringPeralatanResolutionContext
     * }
     */
    public static function buildResolutionCache(string $asOfDate, ?int $idCabang, iterable $units = null): array
    {
        $ctx = self::buildContext($asOfDate);

        if ($units === null) {
            $query = Peralatan::query()->where('is_active', true);
            if (Schema::hasColumn((new Peralatan())->getTable(), 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            if ($idCabang !== null && $idCabang > 0) {
                $query->where('id_cabang', $idCabang);
            }
            $units = $query->get([
                'id',
                'kode_aset',
                'nama_alat',
                'jenis_peralatan',
                'nama_kategori_pengujian',
                'warna',
                'lokasi_penempatan_alat',
            ]);
        }

        $byId = [];
        foreach ($units as $unit) {
            $id = (int) $unit->id;
            $byId[$id] = self::resolveEffectiveStatus($id, $unit->kode_aset ?? null, $ctx);
        }

        return [
            'by_id' => $byId,
            'group_counts' => self::computeGroupCounts($units, $byId),
            'ctx' => $ctx,
        ];
    }

    /**
     * @param  iterable<Peralatan>  $units
     * @param  array<int, array<string, mixed>>  $byId
     * @return array<string, array{tersedia: int, belum: int}>
     */
    public static function computeGroupCounts(iterable $units, array $byId): array
    {
        $groups = [];
        foreach ($units as $unit) {
            $key = self::buildGroupKey($unit);
            if (!isset($groups[$key])) {
                $groups[$key] = ['tersedia' => 0, 'belum' => 0];
            }
            $status = (string) ($byId[(int) $unit->id]['status_alat'] ?? self::STATUS_TERSEDIA);
            if (strcasecmp($status, self::STATUS_TERSEDIA) === 0) {
                $groups[$key]['tersedia']++;
            } else {
                $groups[$key]['belum']++;
            }
        }

        return $groups;
    }

    public static function buildGroupKey($unit): string
    {
        return implode('||', [
            mb_strtolower(trim((string) ($unit->jenis_peralatan ?? ''))),
            mb_strtolower(trim((string) ($unit->nama_kategori_pengujian ?? ''))),
            mb_strtolower(trim((string) ($unit->nama_alat ?? ''))),
            mb_strtolower(trim((string) ($unit->warna ?? ''))),
            mb_strtolower(trim((string) ($unit->lokasi_penempatan_alat ?? ''))),
        ]);
    }

    /**
     * Cache status efektif per hari untuk rentang [start, start + durasi - 1].
     *
     * @return list<array{date: string, by_id: array<int, array<string, mixed>>}>
     */
    public static function buildByIdCachesForDateRange(?string $startDate, int $durasi = 1): array
    {
        if ($startDate === null || trim($startDate) === '' || !Schema::hasTable('monitoring_peralatan')) {
            return [];
        }

        $durasi = max(1, $durasi);
        $start = Carbon::parse($startDate);
        $caches = [];

        for ($i = 0; $i < $durasi; $i++) {
            $day = $start->copy()->addDays($i)->format('Y-m-d');
            $caches[] = [
                'date' => $day,
                'by_id' => self::buildResolutionCache($day, null)['by_id'],
            ];
        }

        return $caches;
    }

    /**
     * @param  list<array{date?: string, by_id: array<int, array<string, mixed>>}>  $byIdCachesPerDay
     */
    public static function isEffectivelyTersediaForRange(int $idPeralatan, array $byIdCachesPerDay): bool
    {
        if ($byIdCachesPerDay === []) {
            return true;
        }

        foreach ($byIdCachesPerDay as $entry) {
            $byId = is_array($entry['by_id'] ?? null) ? $entry['by_id'] : $entry;
            $status = (string) ($byId[$idPeralatan]['status_alat'] ?? self::STATUS_TERSEDIA);
            if (strcasecmp($status, self::STATUS_TERSEDIA) !== 0) {
                return false;
            }
        }

        return true;
    }

    /** Status efektif pada hari pertama rentang (untuk label API). */
    public static function effectiveStatusOnFirstDay(int $idPeralatan, array $byIdCachesPerDay): string
    {
        if ($byIdCachesPerDay === []) {
            return self::STATUS_TERSEDIA;
        }

        $byId = $byIdCachesPerDay[0]['by_id'] ?? [];

        return (string) ($byId[$idPeralatan]['status_alat'] ?? self::STATUS_TERSEDIA);
    }

    /**
     * @return list<int>
     */
    public static function idsMatchingEffectiveStatus(string $asOfDate, string $keyword, ?int $idCabang): array
    {
        $needle = mb_strtolower(trim($keyword));
        if ($needle === '') {
            return [];
        }

        $query = Peralatan::query()->where('is_active', true);
        if (Schema::hasColumn((new Peralatan())->getTable(), 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if ($idCabang !== null && $idCabang > 0) {
            $query->where('id_cabang', $idCabang);
        }

        $units = $query->get(['id', 'kode_aset']);
        $cache = self::buildResolutionCache($asOfDate, null, $units);
        $ids = [];

        $knownStatuses = array_map(
            static fn ($s) => mb_strtolower(trim((string) $s)),
            MonitoringPeralatan::STATUS_ALAT
        );
        $exactMatch = in_array($needle, $knownStatuses, true);

        foreach ($units as $unit) {
            $status = mb_strtolower((string) ($cache['by_id'][(int) $unit->id]['status_alat'] ?? ''));
            if ($status === '') {
                continue;
            }
            if ($exactMatch ? $status === $needle : str_contains($status, $needle)) {
                $ids[] = (int) $unit->id;
            }
        }

        return $ids;
    }

    public static function eventCoversDate(object $event, string $asOfDate): bool
    {
        $status = (string) ($event->status_alat ?? '');
        if (!MonitoringPeralatan::requiresPeriod($status)) {
            return false;
        }

        $startRaw = $event->start_date ?? null;
        if ($startRaw === null || $startRaw === '') {
            if (strcasecmp($status, self::STATUS_SEDANG_DIGUNAKAN) === 0) {
                $created = $event->created_at ?? null;
                if ($created !== null && $created !== '') {
                    return Carbon::parse($created)->format('Y-m-d') === $asOfDate;
                }
            }

            return false;
        }

        $start = Carbon::parse($startRaw)->format('Y-m-d');
        $endRaw = $event->end_date ?? null;
        $end = ($endRaw !== null && $endRaw !== '')
            ? Carbon::parse($endRaw)->format('Y-m-d')
            : $start;

        return $asOfDate >= $start && $asOfDate <= $end;
    }

    /**
     * @return array{
     *     status_alat: string,
     *     keterangan: string|null,
     *     start_date: string|null,
     *     end_date: string|null,
     *     id_monitoring: int|null,
     *     source: string
     * }
     */
    private static function mapResolvedEvent(object $event, string $source): array
    {
        $start = $event->start_date ?? null;
        $end = $event->end_date ?? null;

        return [
            'status_alat' => (string) ($event->status_alat ?? self::STATUS_TERSEDIA),
            'keterangan' => $event->keterangan !== null && $event->keterangan !== ''
                ? (string) $event->keterangan
                : null,
            'start_date' => $start !== null && $start !== '' ? Carbon::parse($start)->format('Y-m-d') : null,
            'end_date' => $end !== null && $end !== '' ? Carbon::parse($end)->format('Y-m-d') : null,
            'id_monitoring' => isset($event->id) ? (int) $event->id : null,
            'source' => $source,
        ];
    }

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