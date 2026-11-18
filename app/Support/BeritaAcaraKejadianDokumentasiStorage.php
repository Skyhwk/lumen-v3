<?php

namespace App\Support;

/**
 * Lampiran BAK: di DB hanya nama file; path folder = dokumentasi/{id}/ (ditambah di backend).
 */
class BeritaAcaraKejadianDokumentasiStorage
{
    public const PREFIX = 'dokumen/disipliner/berita-acara-kejadian/dokumentasi';

    public static function folderRelative(int $documentId): string
    {
        return self::PREFIX . '/' . $documentId;
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

    public static function toRelativePath(int $documentId, string $stored): string
    {
        $name = self::normalizeStoredName($stored);
        if ($name === '') {
            return '';
        }

        return self::folderRelative($documentId) . '/' . $name;
    }

    public static function fullPath(int $documentId, string $stored): string
    {
        $relative = self::toRelativePath($documentId, $stored);
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
    public static function expandForApi(int $documentId, array $storedNames): array
    {
        $paths = [];
        foreach ($storedNames as $stored) {
            $rel = self::toRelativePath($documentId, (string) $stored);
            if ($rel !== '') {
                $paths[] = $rel;
            }
        }

        return $paths;
    }
}