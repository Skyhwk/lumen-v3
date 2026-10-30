<?php

namespace App\Services;

use App\Models\SuratTeguranDisipliner;

class GenerateQrDocumentSuratTeguran
{
    public const TYPE_DOCUMENT = GenerateQrDocumentDisipliner::TYPE_SURAT_TEGURAN;

    public function sync(SuratTeguranDisipliner $letter, object $detailRow, $generatedBy): string
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
}
