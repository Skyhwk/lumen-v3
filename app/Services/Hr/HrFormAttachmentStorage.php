<?php

namespace App\Services\Hr;

use App\Support\Greatday\GreatdayAssetPaths;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class HrFormAttachmentStorage
{
    public const DEFAULT_MAX_FILES = 10;

    /**
     * @return list<UploadedFile>
     */
    public static function collectUploadedImages(Request $request): array
    {
        $files = [];

        if ($request->hasFile('attachments')) {
            foreach ((array) $request->file('attachments') as $file) {
                if ($file instanceof UploadedFile) {
                    $files[] = $file;
                }
            }
        }

        if ($request->hasFile('attachment')) {
            $single = $request->file('attachment');
            if (is_array($single)) {
                foreach ($single as $file) {
                    if ($file instanceof UploadedFile) {
                        $files[] = $file;
                    }
                }
            } elseif ($single instanceof UploadedFile) {
                $files[] = $single;
            }
        }

        return $files;
    }

    public static function hasUploadedImages(Request $request): bool
    {
        return self::collectUploadedImages($request) !== [];
    }

    /**
     * Simpan ke public/greatday/{folder}/. Satu file = string nama; banyak = JSON array.
     *
     * @param list<UploadedFile> $files
     * @param string $assetKey salah satu GreatdayAssetPaths::KEY_*
     */
    public static function storeImages(array $files, string $assetKey, int $maxFiles = self::DEFAULT_MAX_FILES): ?string
    {
        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        if ($files === []) {
            return null;
        }

        $destinationPath = GreatdayAssetPaths::publicDir($assetKey);
        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        $names = [];
        foreach (array_slice($files, 0, max(1, $maxFiles)) as $file) {
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $fileName = str_replace('.', '', microtime(true) . mt_rand(100, 999)) . '.' . $ext;
            $file->move($destinationPath, $fileName);
            $names[] = $fileName;
        }

        if (count($names) === 1) {
            return $names[0];
        }

        return json_encode($names, JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return list<string> URL absolut (WEB_PUBLIC)
     * @param string $assetKey salah satu GreatdayAssetPaths::KEY_*
     */
    public static function resolvePublicUrls(?string $stored, string $assetKey): array
    {
        $names = self::decodeStoredNames($stored);
        if ($names === []) {
            return [];
        }

        $urls = [];
        foreach ($names as $name) {
            $url = self::resolveOnePublicUrl($name, $assetKey);
            if ($url !== null && $url !== '') {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return list<string> nama file saja
     */
    public static function decodeStoredNames(?string $stored): array
    {
        if ($stored === null || trim($stored) === '') {
            return [];
        }

        $trim = trim($stored);
        if ($trim !== '' && $trim[0] === '[') {
            $decoded = json_decode($trim, true);
            if (!is_array($decoded)) {
                return [];
            }

            return array_values(array_filter(array_map('strval', $decoded)));
        }

        return [$trim];
    }

    public static function hasStored(?string $stored): bool
    {
        return self::decodeStoredNames($stored) !== [];
    }

    private static function resolveOnePublicUrl(string $stored, string $assetKey): ?string
    {
        $trim = trim($stored);
        if ($trim === '') {
            return null;
        }

        if (self::isAbsoluteUrl($trim)) {
            return $trim;
        }

        if (str_contains($trim, '://')) {
            return $trim;
        }

        if (str_contains($trim, '/')) {
            $relative = ltrim(preg_replace('#^public/#i', '', $trim), '/');

            return self::joinPublicUrl($relative);
        }

        $candidates = GreatdayAssetPaths::resolveSearchRelativeDirs($assetKey);
        foreach ($candidates as $subdir) {
            $relative = $subdir . '/' . ltrim($trim, '/');
            if (file_exists(public_path(str_replace('/', DIRECTORY_SEPARATOR, $relative)))) {
                // URL selalu canonical v3/greatday/{folder}/ agar selaras produksi
                $canonical = GreatdayAssetPaths::relativeDir($assetKey) . '/' . ltrim(basename($trim), '/');

                return self::joinPublicUrl($canonical);
            }
        }

        return GreatdayAssetPaths::publicUrl($assetKey, $trim);
    }

    private static function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    private static function publicBase(): string
    {
        $base = config('greatday.web_public');
        if (is_string($base) && $base !== '') {
            return rtrim($base, '/');
        }

        return rtrim(url(''), '/');
    }

    private static function joinPublicUrl(string $relativePath): string
    {
        return self::publicBase() . '/' . ltrim($relativePath, '/');
    }

    /**
     * Cari file di disk legacy (V3 public lama) untuk migrasi.
     *
     * @return string|null path absolut file sumber
     */
    public static function findLegacySourceFile(string $fileName, string $assetKey): ?string
    {
        $fileName = ltrim($fileName, '/');
        if ($fileName === '' || str_contains($fileName, '/')) {
            return null;
        }

        $roots = config('greatday.form_attachment_legacy_roots', []);
        $subdirs = GreatdayAssetPaths::resolveSearchRelativeDirs($assetKey);

        foreach ((array) $roots as $root) {
            $root = rtrim((string) $root, '/\\');
            if ($root === '') {
                continue;
            }
            foreach ($subdirs as $subdir) {
                $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir)
                    . DIRECTORY_SEPARATOR . $fileName;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }
}
