<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayProduksiConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Log absensi Greatday (selfie + geo), terpisah dari baris mesin RFID di `absensi`. */
class AbsensiAndroid extends Model
{
    use UsesGreatdayProduksiConnection;

    protected $table = 'absensi_mobile';

    protected $guarded = ['id'];

    public $timestamps = false;

    /**
     * Log mobile + legacy Super Apps (intilab_apps.absensi: user_id, tgl, selfie).
     */
    public static function logsForKaryawanOnDate(int $karyawanId, string $date): Collection
    {
        $rows = static::query()
            ->where('karyawan_id', $karyawanId)
            ->where('tanggal', $date)
            ->get()
            ->map(fn ($row) => (object) [
                'jam' => $row->jam,
                'selfie' => $row->selfie,
            ]);

        $legacyConn = (string) config('greatday.legacy_apps_connection', 'intilab_apps');
        if ($legacyConn === '' || !Schema::connection($legacyConn)->hasTable('absensi')) {
            return $rows->values();
        }

        if (!Schema::connection($legacyConn)->hasColumn('absensi', 'selfie')) {
            return $rows->values();
        }

        $legacy = DB::connection($legacyConn)->table('absensi')
            ->where('user_id', $karyawanId)
            ->where('tgl', $date)
            ->get(['jam', 'selfie']);

        $byJam = [];
        foreach ($rows as $row) {
            $key = self::normalizeJamKey($row->jam ?? null);
            if ($key === null) {
                continue;
            }
            $byJam[$key] = $row;
        }

        foreach ($legacy as $row) {
            $key = self::normalizeJamKey($row->jam ?? null);
            if ($key === null) {
                continue;
            }
            $existing = $byJam[$key] ?? null;
            if ($existing === null) {
                $byJam[$key] = (object) ['jam' => $key, 'selfie' => $row->selfie];

                continue;
            }
            if (empty($existing->selfie) && !empty($row->selfie)) {
                $existing->selfie = $row->selfie;
            }
        }

        return collect(array_values($byJam))->sortBy('jam')->values();
    }

    private static function normalizeJamKey($jam): ?string
    {
        if ($jam === null || $jam === '') {
            return null;
        }
        $ts = strtotime((string) $jam);

        return $ts ? date('H:i:s', $ts) : null;
    }
}
