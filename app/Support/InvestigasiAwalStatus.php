<?php

namespace App\Support;

final class InvestigasiAwalStatus
{
    public const DRAFT = 'draft';

    public const MENUNGGU_HASIL = 'menunggu_hasil';

    public const MENUNGGU_PENGESAHAN = 'menunggu_pengesahan';

    public const ISSUED = 'issued';

    public const CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function workflowValues(): array
    {
        return [
            self::DRAFT,
            self::MENUNGGU_HASIL,
            self::MENUNGGU_PENGESAHAN,
            self::ISSUED,
            self::CANCELLED,
        ];
    }
}