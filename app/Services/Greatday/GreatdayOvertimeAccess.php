<?php

namespace App\Services\Greatday;

use App\Models\MasterKaryawan;

final class GreatdayOvertimeAccess
{
    /** @var list<string> */
    private const CREATOR_GRADES = ['SUPERVISOR', 'MANAGER', 'SENIOR MANAGER'];

    public static function canCreate(MasterKaryawan $employee): bool
    {
        $grade = strtoupper(str_replace('_', ' ', trim((string) ($employee->grade ?? ''))));
        if ($grade === 'SPV') {
            $grade = 'SUPERVISOR';
        }

        return in_array($grade, self::CREATOR_GRADES, true);
    }

    public static function denyCreateMessage(): string
    {
        return 'Permohonan lembur hanya dapat dibuat oleh Supervisor, Manager, atau Senior Manager.';
    }
}
