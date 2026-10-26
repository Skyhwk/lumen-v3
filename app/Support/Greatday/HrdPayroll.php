<?php

namespace App\Support\Greatday;

use App\Models\MasterKaryawan;

class HrdPayroll
{
    public const STAFF_JABATAN = 'Internal Payroll Staff';

    public static function isHrdStaff($employee): bool
    {
        return $employee && $employee->jabatan === self::STAFF_JABATAN;
    }

    public static function canAccessHrdQueue($employee): bool
    {
        if (!$employee) {
            return false;
        }

        if (self::isHrdStaff($employee)) {
            return true;
        }

        return in_array((int) $employee->id, self::hrdSupervisorIds(), true);
    }

    public static function hrdSupervisorIds(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $ids = [];
        $staffMembers = MasterKaryawan::where('jabatan', self::STAFF_JABATAN)
            ->where('is_active', true)
            ->get(['atasan_langsung']);

        foreach ($staffMembers as $staff) {
            $atasanIds = json_decode($staff->atasan_langsung, true) ?: [];
            foreach ($atasanIds as $atasanId) {
                $ids[] = (int) $atasanId;
            }
        }

        return $cache = array_values(array_unique($ids));
    }

    /** Penerima notifikasi saat pengajuan masuk antrian HRD */
    public static function queueNotificationUserIds(): array
    {
        $staffIds = MasterKaryawan::where('jabatan', self::STAFF_JABATAN)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        return array_values(array_unique(array_merge($staffIds, self::hrdSupervisorIds())));
    }
}
