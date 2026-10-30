<?php

namespace App\Support;

final class QrDocumentsStorage
{
    /**
     * Folder fisik SVG QR (harus sama dengan URL .../public/qr_documents/).
     * Override lewat .env QR_DOCUMENTS_PUBLIC_DIR jika DocumentRoot Apache beda dari lumen/public.
     */
    public static function directory(): string
    {
        $custom = trim((string) env('QR_DOCUMENTS_PUBLIC_DIR', ''));
        if ($custom !== '') {
            $dir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $custom), DIRECTORY_SEPARATOR);
            if (stripos(basename($dir), 'qr_documents') === false) {
                $dir .= DIRECTORY_SEPARATOR . 'qr_documents';
            }

            return $dir . DIRECTORY_SEPARATOR;
        }

        return public_path('qr_documents' . DIRECTORY_SEPARATOR);
    }

    public static function svgPath(string $fileBase): string
    {
        $base = preg_replace('/\.svg$/i', '', basename($fileBase));
        if ($base === '' || preg_match('/[^A-Za-z0-9._-]/', $base)) {
            throw new \InvalidArgumentException('Nama file QR tidak valid.');
        }

        return self::directory() . $base . '.svg';
    }

    public static function ensureDirectory(): void
    {
        $dir = self::directory();
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('Folder QR gagal dibuat: ' . $dir);
        }
    }
}
