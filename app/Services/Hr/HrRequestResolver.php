<?php

namespace App\Services\Hr;

use App\Models\Hr\HrMigrationMap;
use App\Models\Hr\HrRequest;

class HrRequestResolver
{
    public static function findByApiId(string $requestType, int $id): ?HrRequest
    {
        $found = HrRequest::where('request_type', $requestType)
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if ($found) {
            return $found;
        }

        $legacyTable = self::legacyTableForType($requestType);
        if (!$legacyTable) {
            return null;
        }

        $map = HrMigrationMap::where([
            'old_connection' => 'intilab_apps',
            'old_table' => $legacyTable,
            'old_id' => $id,
        ])->first();

        if (!$map) {
            return null;
        }

        return HrRequest::where('id', $map->new_id)
            ->where('request_type', $requestType)
            ->where('is_active', true)
            ->first();
    }

    public static function legacyIdForHrRequest(HrRequest $request): ?int
    {
        $legacyTable = self::legacyTableForType($request->request_type);
        if (!$legacyTable) {
            return null;
        }

        $map = HrMigrationMap::where([
            'old_connection' => 'intilab_apps',
            'old_table' => $legacyTable,
            'new_table' => 'hr_request',
            'new_id' => $request->id,
        ])->first();

        return $map ? (int) $map->old_id : null;
    }

    public static function legacyTableForType(string $type): ?string
    {
        $map = [
            HrRequest::TYPE_LEAVE => 'leave_requests',
            HrRequest::TYPE_PERMISSION => 'permission_requests',
            HrRequest::TYPE_OVERTIME => 'overtime_requests',
            HrRequest::TYPE_ATTENDANCE_CORRECTION => 'attendance_corrections',
        ];

        return $map[$type] ?? null;
    }
}
