<?php

namespace App\Services\QuotationGenerate;

final class QuotationGenerateDataPendukung
{
    public static function isUsable($raw): bool
    {
        $raw = trim((string) $raw);
        if ($raw === '' || strcasecmp($raw, 'null') === 0) {
            return false;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $decoded === []) {
            return false;
        }

        return true;
    }
}
