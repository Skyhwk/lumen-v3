<?php

namespace App\Services;

use App\Models\KebijakanDokumen;
use Carbon\Carbon;

class KebijakanDokumenQrService
{
    public static function ensureQrReady(KebijakanDokumen $dokumen, $generatedBy = null): KebijakanDokumen
    {
        if (!$dokumen->legal_verified_at) {
            return $dokumen;
        }

        $needsSync = empty($dokumen->qr_file);

        if (!$needsSync) {
            $svgPath = public_path('qr_documents/' . $dokumen->qr_file . '.svg');
            $needsSync = !file_exists($svgPath);
        }

        if (!$needsSync) {
            return $dokumen;
        }

        self::syncAndStore(
            $dokumen,
            $generatedBy ?? $dokumen->legal_verified_by ?? 'System'
        );

        return $dokumen->fresh();
    }

    public static function syncAndStore(KebijakanDokumen $dokumen, $generatedBy = 'System'): string
    {
        $qrFile = (new GenerateQrDocumentKebijakan())->sync($dokumen, $generatedBy);

        $dokumen->update([
            'qr_file' => $qrFile,
            'updated_at' => Carbon::now(),
        ]);

        return $qrFile;
    }

    public static function buildPayload(KebijakanDokumen $dokumen): array
    {
        return GenerateQrDocumentKebijakan::buildQrData($dokumen);
    }

    public static function formatDisplayDate($value): ?string
    {
        return GenerateQrDocumentKebijakan::formatDisplayDate($value);
    }
}
