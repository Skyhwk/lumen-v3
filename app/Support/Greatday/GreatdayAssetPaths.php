<?php

namespace App\Support\Greatday;

/** Semua aset publik Greatday: public/{base}/{folder}/file */
final class GreatdayAssetPaths
{
    public const KEY_CUTI = 'cuti';

    public const KEY_IZIN = 'izin';

    public const KEY_KOREKSI_ABSEN = 'koreksi_absen';

    public const KEY_ABSENSI = 'absensi';

    public const KEY_LEMBUR_REIMBURSE = 'lembur_reimburse';

    public const KEY_LAPORAN_KEGIATAN = 'laporan_kegiatan';

    public static function base(): string
    {
        return trim((string) config('greatday.asset_base', 'greatday'), '/');
    }

    public static function folder(string $assetKey): string
    {
        $folders = config('greatday.asset_folders', []);
        if (isset($folders[$assetKey]) && is_string($folders[$assetKey]) && $folders[$assetKey] !== '') {
            return trim($folders[$assetKey], '/');
        }

        return str_replace('_', '-', $assetKey);
    }

    /** Path relatif dari public/, contoh: greatday/cuti */
    public static function relativeDir(string $assetKey): string
    {
        return self::base() . '/' . self::folder($assetKey);
    }

    public static function publicDir(string $assetKey): string
    {
        return public_path(str_replace('/', DIRECTORY_SEPARATOR, self::relativeDir($assetKey)));
    }

    /** v3/public/greatday/absensi — upload & URL publik selfie absen. */
    public static function absensiPublicDir(): string
    {
        return self::publicDir(self::KEY_ABSENSI);
    }

    public static function absensiPublicUrl(string $fileName): string
    {
        return self::publicUrl(self::KEY_ABSENSI, $fileName);
    }

    /** URL publik v3: {WEB_PUBLIC}greatday/{folder}/{file} */
    public static function publicUrl(string $assetKey, string $fileName): string
    {
        $base = rtrim((string) config('greatday.web_public', ''), '/');
        if ($base === '') {
            $base = rtrim(url(''), '/');
        }

        $file = ltrim(basename($fileName), '/');

        return $base . '/' . self::relativeDir($assetKey) . '/' . $file;
    }

    /**
     * Path absolut file absensi jika ada (canonical dulu, lalu legacy).
     */
    public static function resolveAbsensiFilePath(string $fileName): ?string
    {
        $fileName = basename($fileName);
        if ($fileName === '') {
            return null;
        }

        foreach (self::resolveSearchRelativeDirs(self::KEY_ABSENSI) as $relative) {
            $path = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relative . '/' . $fileName));
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Canonical + folder legacy untuk resolve URL & migrasi file.
     *
     * @return list<string>
     */
    public static function resolveSearchRelativeDirs(string $assetKey): array
    {
        $canonical = self::relativeDir($assetKey);
        $legacy = config('greatday.asset_legacy_relative.' . $assetKey, []);
        $dirs = array_merge([$canonical], is_array($legacy) ? $legacy : []);

        $normalized = [];
        foreach ($dirs as $dir) {
            $d = trim(str_replace('\\', '/', (string) $dir), '/');
            if ($d !== '') {
                $normalized[] = $d;
            }
        }

        return array_values(array_unique($normalized));
    }
}
