<?php

namespace App\Services\Hr;

/** Status string kompatibel legacy greatday / portal. */
final class WorkflowStatus
{
    public const PENDING = 'Pending';

    public const APPROVED_ATASAN = 'Approved Atasan';

    public const REJECTED_ATASAN = 'Rejected Atasan';

    public const APPROVED_HRD = 'Approved HRD';

    public const REJECTED_HRD = 'Rejected HRD';

    public const APPROVED_FINANCE = 'Approved Finance';

    public const REJECTED_FINANCE = 'Rejected Finance';

    /** Pengajuan sendiri yang masih dalam proses (belum final HRD/reject). */
    public static function submitterInProgressStatuses(): array
    {
        return [self::PENDING, self::APPROVED_ATASAN];
    }

    public static function isSubmitterInProgress(?string $status): bool
    {
        return $status !== null && in_array($status, self::submitterInProgressStatuses(), true);
    }

    /** Kartu stat Formulir Greatday — izin & cuti (hitung pengajuan / hari setelah HRD). */
    public static function formsStatApprovedStatuses(): array
    {
        return [self::APPROVED_HRD];
    }

    /** Lembur: setelah HRD (termasuk yang sudah lanjut ke Finance). */
    public static function formsStatApprovedOvertimeStatuses(): array
    {
        return [self::APPROVED_HRD, self::APPROVED_FINANCE];
    }

    public static function countsTowardFormsUserStat(?string $status, string $kind = 'standard'): bool
    {
        if ($status === null || $status === '') {
            return false;
        }

        $allowed = $kind === 'overtime'
            ? self::formsStatApprovedOvertimeStatuses()
            : self::formsStatApprovedStatuses();

        return in_array($status, $allowed, true);
    }
}
