<?php

namespace App\Services\Greatday;

use App\Models\MasterKaryawan;

/**
 * Scope bawahan untuk antrian persetujuan — selaras portal V3 (GetBawahan / Request Lembur indexByOwner),
 * bukan hanya atasan_langsung satu tingkat.
 */
final class AtasanApprovalScope
{
    /** @return array<int, int> */
    public static function subordinateKaryawanIds(MasterKaryawan $approver): array
    {
        if (!self::isAtasanGrade($approver)) {
            return [];
        }

        $approverId = (int) $approver->id;

        return GetBawahan::where('id', $approverId)
            ->get()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id !== $approverId)
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public static function subordinateKaryawanNames(MasterKaryawan $approver): array
    {
        if (!self::isAtasanGrade($approver)) {
            return [];
        }

        $approverId = (int) $approver->id;

        return GetBawahan::where('id', $approverId)
            ->get()
            ->filter(fn ($row) => (int) $row->id !== $approverId)
            ->pluck('nama_lengkap')
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    public static function isSubordinateKaryawan(MasterKaryawan $approver, int $employeeId): bool
    {
        if ((int) $approver->id === $employeeId) {
            return false;
        }

        return in_array($employeeId, self::subordinateKaryawanIds($approver), true);
    }

    public static function isAtasanGrade(MasterKaryawan $employee): bool
    {
        return in_array($employee->grade ?? '', ['MANAGER', 'SUPERVISOR', 'SENIOR MANAGER'], true);
    }
}
