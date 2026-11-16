<?php

namespace App\Support;

final class QuotationDocumentSlug
{
    public static function fromDocumentNumber(string $noDocument): string
    {
        return str_replace('/', '-', trim($noDocument));
    }

    public static function normalizeIncoming(?string $value): string
    {
        return trim((string) $value);
    }
}
