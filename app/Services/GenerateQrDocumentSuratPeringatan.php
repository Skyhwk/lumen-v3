<?php

namespace App\Services;

use App\Models\SuratPeringatanDisipliner;

/**
 * @deprecated Prefer {@see GenerateQrDocumentDisipliner} — wrapper agar injeksi lama tetap jalan.
 */
class GenerateQrDocumentSuratPeringatan
{
    public const TYPE_DOCUMENT = GenerateQrDocumentDisipliner::TYPE_SURAT_PERINGATAN;

    public function sync(SuratPeringatanDisipliner $letter, object $detailRow, $generatedBy): string
    {
        return app(GenerateQrDocumentDisipliner::class)->sync(
            self::TYPE_DOCUMENT,
            $letter,
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

    public static function buildQrData(SuratPeringatanDisipliner $letter, object $detailRow): array
    {
        return GenerateQrDocumentDisipliner::buildQrPayload(self::TYPE_DOCUMENT, $letter, $detailRow);
    }

    public static function writePublicSvg(string $filename, string $kodeQr): void
    {
        GenerateQrDocumentDisipliner::writePublicSvg($filename, $kodeQr, self::TYPE_DOCUMENT);
    }
}
