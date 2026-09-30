<?php

namespace App\Services\Hr;

final class HrTableMode
{
    public static function usesLegacyHrTables(): bool
    {
        return (bool) config('greatday.use_legacy_hr_tables', true);
    }

    public static function freezeLegacyHrWrites(): bool
    {
        return (bool) config('greatday.freeze_legacy_hr_writes', false);
    }

    public static function dualWriteLegacy(): bool
    {
        if (self::freezeLegacyHrWrites()) {
            return false;
        }

        return (bool) config('greatday.dual_write_legacy_hr', true);
    }

    public static function portalReadsHrTables(): bool
    {
        return (bool) config('greatday.portal_read_hr_tables', false);
    }
}
