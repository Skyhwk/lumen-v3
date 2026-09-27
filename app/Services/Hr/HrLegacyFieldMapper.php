<?php

namespace App\Services\Hr;

use App\Models\Hr\HrMigrationMap;

final class HrLegacyFieldMapper
{
    public static function leaveTypeFromKind(string $kind): string
    {
        if ($kind === 'special') {
            return 'Special Leave';
        }
        if ($kind === 'unpaid') {
            return 'Unpaid Leave';
        }

        return 'Annual Leave';
    }

    public static function leaveKindFromType(?string $type): string
    {
        if ($type === 'Special Leave') {
            return 'special';
        }
        if ($type === 'Unpaid Leave') {
            return 'unpaid';
        }

        return 'annual';
    }

    public static function permissionTypeFromKind(string $kind): string
    {
        if ($kind === 'sick') {
            return 'Sick Leave';
        }
        if ($kind === 'late') {
            return 'Late Arrival';
        }

        return 'Event Leave';
    }

    public static function permissionKindFromType(?string $type): string
    {
        if ($type === 'Sick Leave') {
            return 'sick';
        }
        if ($type === 'Late Arrival') {
            return 'late';
        }

        return 'event';
    }

    /** HR special leave type id → legacy intilab_apps.special_leave_types.id */
    public static function legacySpecialLeaveId($hrSpecialLeaveTypeId): ?int
    {
        if (!$hrSpecialLeaveTypeId) {
            return null;
        }

        $map = HrMigrationMap::where([
            'old_connection' => 'intilab_apps',
            'old_table' => 'special_leave_types',
            'new_table' => 'hr_special_leave_type',
            'new_id' => $hrSpecialLeaveTypeId,
        ])->first();

        return $map ? (int) $map->old_id : (int) $hrSpecialLeaveTypeId;
    }
}
