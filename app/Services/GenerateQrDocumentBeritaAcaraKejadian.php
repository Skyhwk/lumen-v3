<?php

namespace App\Services;

use App\Models\BeritaAcaraKejadianDisipliner;

/**
 * @deprecated Prefer {@see GenerateQrDocumentDisipliner} — wrapper agar injeksi lama tetap jalan.
 */
class GenerateQrDocumentBeritaAcaraKejadian
{
    public const TYPE_DOCUMENT = GenerateQrDocumentDisipliner::TYPE_BERITA_ACARA_KEJADIAN;

    public function sync(BeritaAcaraKejadianDisipliner $document, object $detailRow, $generatedBy): string
    {
        return app(GenerateQrDocumentDisipliner::class)->sync(
            self::TYPE_DOCUMENT,
            $document,
            $detailRow,
            $generatedBy
        );
    }

    public static function buildFilename(string $nomorSurat): string
    {
        return GenerateQrDocumentDisipliner::buildFilename($nomorSurat, self::TYPE_DOCUMENT);
    }

    public static function ensurePublicSvgForFile(string $fileBase): bool
    {
        return GenerateQrDocumentDisipliner::ensurePublicSvgForFile($fileBase);
    }

    public static function buildQrData(BeritaAcaraKejadianDisipliner $document, object $detailRow): array
    {
        return GenerateQrDocumentDisipliner::buildQrPayload(self::TYPE_DOCUMENT, $document, $detailRow);
    }

    public static function writePublicSvg(string $filename, string $kodeQr): void
    {
        GenerateQrDocumentDisipliner::writePublicSvg($filename, $kodeQr, self::TYPE_DOCUMENT);
    }
}
