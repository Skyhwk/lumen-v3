<?php

namespace App\Support;

/**
 * Lampiran BAK: di DB hanya nama file; folder = dokumentasi/bak/{nomor_surat}/.
 */
class BeritaAcaraKejadianDokumentasiStorage
{
    public const PREFIX = 'dokumentasi/bak';

    public static function nomorFolderSegment(string $nomorSurat): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($nomorSurat));

        return $safe !== '' ? $safe : 'tanpa-nomor';
    }

    public static function folderRelative(string $nomorSurat): string
    {
        return self::PREFIX . '/' . self::nomorFolderSegment($nomorSurat);
    }

    public static function publicDirectory(string $nomorSurat): string
    {
        $relative = self::folderRelative($nomorSurat);
        $dir = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Folder lampiran BAK gagal dibuat: ' . $relative);
        }

        return $dir;
    }

    public static function normalizeStoredName(string $item): string
    {
        $item = str_replace('\\', '/', trim($item));
        if ($item === '' || strpos($item, '..') !== false) {
            return '';
        }

        if (strpos($item, '/') !== false) {
            return basename($item);
        }

        return $item;
    }

    public static function toRelativePath(string $nomorSurat, string $stored): string
    {
        $name = self::normalizeStoredName($stored);
        if ($name === '') {
            return '';
        }

        return self::folderRelative($nomorSurat) . '/' . $name;
    }

    public static function fullPath(string $nomorSurat, string $stored): string
    {
        $relative = self::toRelativePath($nomorSurat, $stored);
        if ($relative === '') {
            return '';
        }

        return public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));
    }

    /**
     * @return array<int, string> hanya nama file (kompatibel data lama yang masih simpan path penuh)
     */
    public static function decodeList($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $decoded = json_decode((string) $value, true);
            $items = is_array($decoded) ? $decoded : [];
        }

        $names = [];
        foreach ($items as $item) {
            $name = self::normalizeStoredName((string) $item);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Untuk response API / preview UI (path relatif dari public).
     *
     * @param array<int, string> $storedNames
     * @return array<int, string>
     */
    public static function expandForApi(string $nomorSurat, array $storedNames): array
    {
        $paths = [];
        foreach ($storedNames as $stored) {
            $rel = self::toRelativePath($nomorSurat, (string) $stored);
            if ($rel !== '') {
                $paths[] = $rel;
            }
        }

        return $paths;
    }
}