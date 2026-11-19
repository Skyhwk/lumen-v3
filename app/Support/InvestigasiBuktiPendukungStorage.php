<?php

namespace App\Support;

/**
 * Lampiran bukti pendukung Investigasi Awal — di DB hanya nama file;
 * folder = dokumentasi/investigasi/{no_document}/.
 */
class InvestigasiBuktiPendukungStorage
{
    public const PREFIX = 'dokumentasi/investigasi';

    public static function caseFolderSegment(string $noDocument): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($noDocument));

        return $safe !== '' ? $safe : 'tanpa-nomor';
    }

    public static function folderRelative(string $noDocument): string
    {
        return self::PREFIX . '/' . self::caseFolderSegment($noDocument);
    }

    public static function publicDirectory(string $noDocument): string
    {
        $relative = self::folderRelative($noDocument);
        $dir = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Folder bukti pendukung investigasi gagal dibuat: ' . $relative);
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

    public static function toRelativePath(string $noDocument, string $stored): string
    {
        $name = self::normalizeStoredName($stored);
        if ($name === '') {
            return '';
        }

        return self::folderRelative($noDocument) . '/' . $name;
    }

    public static function fullPath(string $noDocument, string $stored): string
    {
        $relative = self::toRelativePath($noDocument, $stored);
        if ($relative === '') {
            return '';
        }

        return public_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));
    }

    /**
     * @return array<int, string>
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
     * @param array<int, string> $storedNames
     * @return array<int, string>
     */
    public static function expandForApi(string $noDocument, array $storedNames): array
    {
        $paths = [];
        foreach ($storedNames as $stored) {
            $rel = self::toRelativePath($noDocument, (string) $stored);
            if ($rel !== '') {
                $paths[] = $rel;
            }
        }

        return $paths;
    }
}