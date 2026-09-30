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

        return self::findHrByLegacyApiId($requestType, $id);
    }

    /**
     * ID dari portal HR (PR-/LR- = old_id legacy bila ada map). Prioritas map dulu — hindari bentrok hr_request.id.
     */
    public static function findByPortalSliceId(string $requestType, int $id): ?HrRequest
    {
        $fromLegacy = self::findHrByLegacyApiId($requestType, $id);
        if ($fromLegacy) {
            return $fromLegacy;
        }

        return HrRequest::where('request_type', $requestType)
            ->where('id', $id)
            ->where('is_active', true)
            ->first();
    }

    private static function findHrByLegacyApiId(string $requestType, int $legacyId): ?HrRequest
    {
        $legacyTable = self::legacyTableForType($requestType);
        if (!$legacyTable) {
            return null;
        }

        $map = HrMigrationMap::where([
            'old_connection' => 'intilab_apps',
            'old_table' => $legacyTable,
            'old_id' => $legacyId,
            'new_table' => 'hr_request',
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

    /** Status hr_request jika baris legacy punya migration map (Greatday baca legacy, portal update hr). */
    public static function hrStatusForLegacyRow(string $legacyTable, int $legacyId): ?string
    {
        $map = HrMigrationMap::where([
            'old_connection' => 'intilab_apps',
            'old_table' => $legacyTable,
            'old_id' => $legacyId,
            'new_table' => 'hr_request',
        ])->first();

        if (!$map) {
            return null;
        }

        $status = HrRequest::where('id', $map->new_id)->where('is_active', true)->value('status');

        return is_string($status) && $status !== '' ? $status : null;
    }
}
