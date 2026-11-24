<?php

namespace App\Models;

use Illuminate\Support\Facades\Schema;

class MasterShiftKaryawan extends Sector
{
    public const PAYROLL_MODE_CALENDAR = 'calendar';

    public const PAYROLL_MODE_MASUK_PER_SHIFT = 'masuk_per_shift';

    public const PAYROLL_MODE_PER_SHIFT = 'per_shift';

    public const PAYROLL_MODES = [
        self::PAYROLL_MODE_CALENDAR,
        self::PAYROLL_MODE_MASUK_PER_SHIFT,
        self::PAYROLL_MODE_PER_SHIFT,
    ];

    protected $table = 'master_shift_karyawan';

    protected $fillable = [
        'kode',
        'label',
        'time_in',
        'time_out',
        'sort_order',
        'payroll_workday_mode',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, array<string, array<string, mixed>>> */
    private static array $catalogCache = [];

    public static function clearCache(): void
    {
        self::$catalogCache = [];
    }

    /**
     * Inject katalog ke cache (berguna untuk unit test tanpa koneksi DB).
     *
     * @param  array<string, array<string, mixed>>  $catalog
     */
    public static function seedCacheForTesting(array $catalog, ?string $connection = null): void
    {
        $cacheKey = $connection ?: '__default__';
        $normalized = [];
        foreach ($catalog as $kode => $item) {
            $k = strtoupper(trim((string) ($item['kode'] ?? $kode)));
            if ($k === '') {
                continue;
            }
            $in = self::normalizeHm($item['time_in'] ?? null);
            $out = self::normalizeHm($item['time_out'] ?? null);
            $normalized[$k] = [
                'id' => (int) ($item['id'] ?? 0),
                'kode' => $k,
                'label' => trim((string) ($item['label'] ?? $k)),
                'time_in' => $in,
                'time_out' => $out,
                'time_in_hms' => $in !== null ? ($in . ':00') : null,
                'time_out_hms' => $out !== null ? ($out . ':00') : null,
                'sort_order' => (int) ($item['sort_order'] ?? 0),
                'payroll_workday_mode' => self::normalizePayrollMode($item['payroll_workday_mode'] ?? null),
                'is_active' => (int) ($item['is_active'] ?? 1),
            ];
        }
        self::$catalogCache[$cacheKey] = $normalized;
    }

    public function scopeActiveOrdered($query)
    {
        return $query->where('is_active', 1)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return array<int, array{value: string, text: string, kode: string}>
     */
    public static function dropdownOptions(): array
    {
        return static::query()
            ->activeOrdered()
            ->get(['kode', 'label', 'time_in', 'time_out'])
            ->map(function ($row) {
                $timeIn = self::normalizeHm($row->time_in) ?? '';
                $timeOut = self::normalizeHm($row->time_out) ?? '';

                return [
                    'value' => trim("S {$row->kode} {$timeIn} {$timeOut}"),
                    'text' => $row->label ?: $row->kode,
                    'kode' => $row->kode,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Katalog seluruh master shift (termasuk nonaktif agar data absensi historis tetap terbaca)
     * diindeks berdasarkan kode uppercase.
     *
     * @return array<string, array{
     *   id: int,
     *   kode: string,
     *   label: string,
     *   time_in: ?string,
     *   time_out: ?string,
     *   time_in_hms: ?string,
     *   time_out_hms: ?string,
     *   sort_order: int,
     *   payroll_workday_mode: string,
     *   is_active: int
     * }>
     */
    public static function allIndexedByKode(?string $connection = null): array
    {
        $cacheKey = $connection ?: '__default__';
        if (isset(self::$catalogCache[$cacheKey])) {
            return self::$catalogCache[$cacheKey];
        }

        try {
            $columns = ['id', 'kode', 'label', 'time_in', 'time_out', 'sort_order', 'is_active'];
            $schema = $connection ? Schema::connection($connection) : Schema::connection(config('database.default', 'mysql'));
            $hasPayrollMode = $schema->hasColumn('master_shift_karyawan', 'payroll_workday_mode');
            if ($hasPayrollMode) {
                $columns[] = 'payroll_workday_mode';
            }

            $query = $connection ? static::on($connection) : static::query();
            $rows = $query
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get($columns);

            $indexed = [];
            foreach ($rows as $row) {
                $kode = strtoupper(trim((string) $row->kode));
                if ($kode === '') {
                    continue;
                }

                $timeIn = self::normalizeHm($row->time_in);
                $timeOut = self::normalizeHm($row->time_out);
                $mode = $hasPayrollMode
                    ? self::normalizePayrollMode($row->payroll_workday_mode ?? null)
                    : self::fallbackPayrollModeForCode($kode);

                $indexed[$kode] = [
                    'id' => (int) $row->id,
                    'kode' => $kode,
                    'label' => trim((string) ($row->label ?: $kode)),
                    'time_in' => $timeIn,
                    'time_out' => $timeOut,
                    'time_in_hms' => $timeIn !== null ? ($timeIn . ':00') : null,
                    'time_out_hms' => $timeOut !== null ? ($timeOut . ':00') : null,
                    'sort_order' => (int) ($row->sort_order ?? 0),
                    'payroll_workday_mode' => $mode,
                    'is_active' => (int) ($row->is_active ?? 1),
                ];
            }

            if ($indexed !== []) {
                self::$catalogCache[$cacheKey] = $indexed;

                return $indexed;
            }
        } catch (\Throwable $e) {
            // Fallback ke default di bawah bila koneksi/tabel belum tersedia (mis. unit test tanpa DB)
        }

        $fallback = self::fallbackIndexed();
        self::$catalogCache[$cacheKey] = $fallback;

        return $fallback;
    }

    /**
     * Ambil jam masuk & pulang untuk satu kode shift dari master_shift_karyawan.
     *
     * @return array{in: string, out: string, in_hms: string, out_hms: string}
     */
    public static function getShiftTimes(
        string $kode,
        ?string $connection = null,
        string $defaultIn = '08:00',
        string $defaultOut = '17:00'
    ): array {
        $catalog = self::allIndexedByKode($connection);
        $key = strtoupper(trim($kode));
        $item = $catalog[$key] ?? null;

        $in = ($item['time_in'] ?? null) ?: $defaultIn;
        $out = ($item['time_out'] ?? null) ?: $defaultOut;

        return [
            'in' => $in,
            'out' => $out,
            'in_hms' => $in . ':00',
            'out_hms' => $out . ':00',
        ];
    }

    public static function is24Jam(?string $shiftCode): bool
    {
        return strtoupper(trim((string) $shiftCode)) === '24JAM';
    }

    /**
     * Shift lintas hari (termasuk 24JAM dan seluruh shift malam dimana time_out <= time_in).
     */
    public static function isOvernight(
        ?string $shiftCode,
        ?string $timeIn = null,
        ?string $timeOut = null,
        ?string $connection = null
    ): bool {
        $code = strtoupper(trim((string) $shiftCode));
        if ($code === '' || in_array($code, ['OFF', 'LIBUR'], true)) {
            return false;
        }

        if ($code === '24JAM') {
            return true;
        }

        [$inHm, $outHm] = self::resolveClockPair($code, $timeIn, $timeOut, $connection);
        if ($inHm !== null && $outHm !== null) {
            return self::hmToMinutes($outHm) <= self::hmToMinutes($inHm);
        }

        return $code === 'SHSECURITY2';
    }

    /**
     * Shift malam lintas hari (bukan 24JAM), misalnya SHSECURITY2 (20:00–08:00) atau shift malam baru lainnya.
     */
    public static function isNightShift(
        ?string $shiftCode,
        ?string $timeIn = null,
        ?string $timeOut = null,
        ?string $connection = null
    ): bool {
        if (self::is24Jam($shiftCode)) {
            return false;
        }

        return self::isOvernight($shiftCode, $timeIn, $timeOut, $connection);
    }

    /**
     * Batas jam klasifikasi punch shift malam berdasarkan time_in & time_out.
     * - SHSECURITY2 (20:00–08:00) → masuk_from = 18:00:00, keluar_before = 14:00:00 (identik aturan lama).
     * - Shift malam lain (mis. 21:00–06:00) → masuk_from = 19:00:00, keluar_before = 12:00:00.
     *
     * @return array{masuk_from: string, keluar_before: string, time_in_hms: string, time_out_hms: string}
     */
    public static function resolveNightShiftBounds(
        ?string $shiftCode,
        ?string $timeIn = null,
        ?string $timeOut = null,
        ?string $connection = null
    ): array {
        $code = strtoupper(trim((string) $shiftCode));
        [$inHm, $outHm] = self::resolveClockPair($code, $timeIn, $timeOut, $connection);

        $inHm = $inHm ?? '20:00';
        $outHm = $outHm ?? '08:00';

        $inMin = self::hmToMinutes($inHm);
        $outMin = self::hmToMinutes($outHm);

        // Masuk shift malam mulai 2 jam sebelum time_in (dibatasi 12:00..23:00)
        $masukFromMin = max(12 * 60, min(23 * 60, $inMin - 120));
        // Pulang pagi hari berikutnya: time_out + 6 jam (dibatasi 10:00..14:00, tidak melewati masuk_from)
        $keluarBeforeMin = min($masukFromMin, max(10 * 60, min(14 * 60, $outMin + 360)));

        return [
            'masuk_from' => self::minutesToHms($masukFromMin),
            'keluar_before' => self::minutesToHms($keluarBeforeMin),
            'time_in_hms' => $inHm . ':00',
            'time_out_hms' => $outHm . ':00',
        ];
    }

    /**
     * Kode shift yang expected hari kerja & hari masuk payroll dihitung per jadwal shift (mis. Security + 24JAM).
     *
     * @return list<string>
     */
    public static function perShiftCodes(?string $connection = null): array
    {
        $codes = [];
        foreach (self::allIndexedByKode($connection) as $kode => $item) {
            if (($item['payroll_workday_mode'] ?? self::PAYROLL_MODE_CALENDAR) === self::PAYROLL_MODE_PER_SHIFT) {
                $codes[] = $kode;
            }
        }

        $codes[] = '24jam';
        $codes[] = '24JAM';

        return array_values(array_unique($codes));
    }

    /**
     * Kode shift yang hari masuk payroll-nya dihitung per jadwal shift tanpa filter Sen–Jum (mis. OB + Security + 24JAM).
     *
     * @return list<string>
     */
    public static function masukPerShiftCodes(?string $connection = null): array
    {
        $codes = [];
        foreach (self::allIndexedByKode($connection) as $kode => $item) {
            $mode = $item['payroll_workday_mode'] ?? self::PAYROLL_MODE_CALENDAR;
            if (in_array($mode, [self::PAYROLL_MODE_PER_SHIFT, self::PAYROLL_MODE_MASUK_PER_SHIFT], true)) {
                $codes[] = $kode;
            }
        }

        $codes[] = '24jam';
        $codes[] = '24JAM';

        return array_values(array_unique($codes));
    }

    /**
     * Format daftar shift untuk Greatday AttendanceController.
     *
     * @return list<array{value: array{IN: ?string, OUT: ?string}, text: string}>
     */
    public static function daftarShift(?string $connection = null): array
    {
        $list = [];
        foreach (self::allIndexedByKode($connection) as $item) {
            $list[] = [
                'value' => [
                    'IN' => $item['time_in'],
                    'OUT' => $item['time_out'],
                ],
                'text' => $item['kode'],
            ];
        }

        $list[] = ['value' => ['IN' => null, 'OUT' => null], 'text' => 'OFF'];

        return $list;
    }

    /**
     * Map kode shift => jam pulang (HH:MM:SS) untuk perhitungan lembur / summary.
     *
     * @return array<string, string>
     */
    public static function defaultShiftOutMap(?string $connection = null): array
    {
        $map = [];
        foreach (self::allIndexedByKode($connection) as $kode => $item) {
            if (!empty($item['time_out_hms'])) {
                $map[$kode] = $item['time_out_hms'];
            }
        }

        if (!isset($map['SHREGULAR'])) {
            $map['SHREGULAR'] = '17:00:00';
        }
        if (!isset($map['24JAM'])) {
            $map['24JAM'] = '08:00:00';
        }

        return $map;
    }

    public static function normalizePayrollMode($value): string
    {
        $mode = strtolower(trim((string) $value));

        return in_array($mode, self::PAYROLL_MODES, true)
            ? $mode
            : self::PAYROLL_MODE_CALENDAR;
    }

    public static function normalizeHm($value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '' || strcasecmp($raw, 'null') === 0) {
            return null;
        }

        if (preg_match('/^(\d{1,2}):([0-5]\d)/', $raw, $matches)) {
            $hour = (int) $matches[1];
            if ($hour >= 0 && $hour <= 23) {
                return sprintf('%02d:%s', $hour, $matches[2]);
            }
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private static function resolveClockPair(
        string $code,
        ?string $timeIn,
        ?string $timeOut,
        ?string $connection
    ): array {
        $inHm = self::normalizeHm($timeIn);
        $outHm = self::normalizeHm($timeOut);

        if ($inHm === null || $outHm === null) {
            $catalog = self::allIndexedByKode($connection);
            $item = $catalog[$code] ?? null;
            if ($item) {
                $inHm = $inHm ?? $item['time_in'];
                $outHm = $outHm ?? $item['time_out'];
            }
        }

        return [$inHm, $outHm];
    }

    private static function hmToMinutes(string $hm): int
    {
        [$h, $m] = explode(':', $hm);

        return ((int) $h * 60) + (int) $m;
    }

    private static function minutesToHms(int $minutes): string
    {
        $minutes = max(0, min(23 * 60 + 59, $minutes));

        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }

    private static function fallbackPayrollModeForCode(string $kode): string
    {
        if (in_array($kode, ['SHSECURITY', 'SHSECURITY2', 'SHSECURITYGO1', 'SHSECURITYGO2'], true)) {
            return self::PAYROLL_MODE_PER_SHIFT;
        }
        if (in_array($kode, ['SHOB', 'SHOB2'], true)) {
            return self::PAYROLL_MODE_MASUK_PER_SHIFT;
        }

        return self::PAYROLL_MODE_CALENDAR;
    }

    /**
     * Fallback aman bila DB belum tersedia (mis. unit test terisolasi).
     *
     * @return array<string, array<string, mixed>>
     */
    private static function fallbackIndexed(): array
    {
        return [
            'SHREGULAR' => [
                'id' => 0,
                'kode' => 'SHREGULAR',
                'label' => 'SHREGULAR',
                'time_in' => '08:00',
                'time_out' => '17:00',
                'time_in_hms' => '08:00:00',
                'time_out_hms' => '17:00:00',
                'sort_order' => 10,
                'payroll_workday_mode' => self::PAYROLL_MODE_CALENDAR,
                'is_active' => 1,
            ],
            'SHOB' => [
                'id' => 0,
                'kode' => 'SHOB',
                'label' => 'SHOB',
                'time_in' => '06:00',
                'time_out' => '15:00',
                'time_in_hms' => '06:00:00',
                'time_out_hms' => '15:00:00',
                'sort_order' => 50,
                'payroll_workday_mode' => self::PAYROLL_MODE_MASUK_PER_SHIFT,
                'is_active' => 1,
            ],
            'SHOB2' => [
                'id' => 0,
                'kode' => 'SHOB2',
                'label' => 'SHOB2',
                'time_in' => '11:00',
                'time_out' => '20:00',
                'time_in_hms' => '11:00:00',
                'time_out_hms' => '20:00:00',
                'sort_order' => 60,
                'payroll_workday_mode' => self::PAYROLL_MODE_MASUK_PER_SHIFT,
                'is_active' => 1,
            ],
            'SHSECURITY' => [
                'id' => 0,
                'kode' => 'SHSECURITY',
                'label' => 'SHSECURITY',
                'time_in' => '08:00',
                'time_out' => '20:00',
                'time_in_hms' => '08:00:00',
                'time_out_hms' => '20:00:00',
                'sort_order' => 90,
                'payroll_workday_mode' => self::PAYROLL_MODE_PER_SHIFT,
                'is_active' => 1,
            ],
            'SHSECURITY2' => [
                'id' => 0,
                'kode' => 'SHSECURITY2',
                'label' => 'SHSECURITY2',
                'time_in' => '20:00',
                'time_out' => '08:00',
                'time_in_hms' => '20:00:00',
                'time_out_hms' => '08:00:00',
                'sort_order' => 100,
                'payroll_workday_mode' => self::PAYROLL_MODE_PER_SHIFT,
                'is_active' => 1,
            ],
            'SHSECURITYGO1' => [
                'id' => 0,
                'kode' => 'SHSECURITYGO1',
                'label' => 'SHSECURITYGO1',
                'time_in' => '07:00',
                'time_out' => '19:00',
                'time_in_hms' => '07:00:00',
                'time_out_hms' => '19:00:00',
                'sort_order' => 110,
                'payroll_workday_mode' => self::PAYROLL_MODE_PER_SHIFT,
                'is_active' => 1,
            ],
            'SHSECURITYGO2' => [
                'id' => 0,
                'kode' => 'SHSECURITYGO2',
                'label' => 'SHSECURITYGO2',
                'time_in' => '07:00',
                'time_out' => '15:00',
                'time_in_hms' => '07:00:00',
                'time_out_hms' => '15:00:00',
                'sort_order' => 120,
                'payroll_workday_mode' => self::PAYROLL_MODE_PER_SHIFT,
                'is_active' => 1,
            ],
        ];
    }
}
