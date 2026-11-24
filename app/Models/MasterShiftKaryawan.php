<?php

namespace App\Models;

class MasterShiftKaryawan extends Sector
{
    protected $table = 'master_shift_karyawan';

    protected $fillable = [
        'kode',
        'label',
        'time_in',
        'time_out',
        'sort_order',
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
            $query = $connection ? static::on($connection) : static::query();
            $rows = $query
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'kode', 'label', 'time_in', 'time_out', 'sort_order', 'is_active']);

            $indexed = [];
            foreach ($rows as $row) {
                $kode = strtoupper(trim((string) $row->kode));
                if ($kode === '') {
                    continue;
                }

                $timeIn = self::normalizeHm($row->time_in);
                $timeOut = self::normalizeHm($row->time_out);

                $indexed[$kode] = [
                    'id' => (int) $row->id,
                    'kode' => $kode,
                    'label' => trim((string) ($row->label ?: $kode)),
                    'time_in' => $timeIn,
                    'time_out' => $timeOut,
                    'time_in_hms' => $timeIn !== null ? ($timeIn . ':00') : null,
                    'time_out_hms' => $timeOut !== null ? ($timeOut . ':00') : null,
                    'sort_order' => (int) ($row->sort_order ?? 0),
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

    private static function normalizeHm($value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)/', $raw, $matches)) {
            return $matches[1] . ':' . $matches[2];
        }

        return null;
    }

    /**
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
                'is_active' => 1,
            ],
        ];
    }
}
