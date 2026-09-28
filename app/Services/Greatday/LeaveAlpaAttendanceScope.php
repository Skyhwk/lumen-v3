<?php

namespace App\Services\Greatday;

use App\Models\MasterKaryawan;

/**
 * Alpa dari sync absensi — grade yang tidak wajib absen mesin/Greatday tidak boleh kena potong alpa.
 */
final class LeaveAlpaAttendanceScope
{
    /** @return list<string> */
    public static function exemptGrades(): array
    {
        $fromConfig = config('greatday.leave_alpa_exempt_grades');
        if (is_array($fromConfig) && $fromConfig !== []) {
            return array_values(array_map([self::class, 'normalizeGrade'], $fromConfig));
        }

        return ['MANAGER', 'SENIOR MANAGER', 'DIRECTOR', 'DIREKTUR'];
    }

    public static function isExemptFromAlpaLedger(MasterKaryawan $employee): bool
    {
        $grade = self::normalizeGrade($employee->grade ?? '');

        return in_array($grade, self::exemptGrades(), true);
    }

    public static function normalizeGrade(?string $grade): string
    {
        $g = strtoupper(trim((string) $grade));
        $g = str_replace('_', ' ', $g);
        if ($g === 'SPV') {
            return 'SUPERVISOR';
        }

        return $g;
    }
}
