<?php

namespace App\Services\Hr;

use App\Models\Hr\HrMigrationMap;
use App\Models\Hr\HrRequest;
use Illuminate\Support\Facades\DB;

class HrRequestResolver
{
    public static function findByApiId(string $requestType, int $id): ?HrRequest
    {
        $fromLegacy = self::findHrByLegacyApiId($requestType, $id);
        if ($fromLegacy) {
            return $fromLegacy;
        }

        $direct = HrRequest::where('request_type', $requestType)
            ->where('id', $id)
            ->where('is_active', true)
            ->first();

        if (!$direct) {
            return null;
        }

        if (self::legacyRowCollidesWithHrRequest($requestType, $id, $direct)) {
            return null;
        }

        return $direct;
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

    /** HR request hanya lewat migration map — aman untuk baris tabel legacy (hindari bentrok id numerik). */
    public static function findMappedFromLegacyRow(string $requestType, int $legacyId): ?HrRequest
    {
        return self::findHrByLegacyApiId($requestType, $legacyId);
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

    /**
     * Baris legacy (attendance_corrections dll.) masih pakai id numerik yang sama dengan hr_request.id
     * tapi pengaju berbeda — antrian Greatday kirim legacy id, jangan resolve ke hr_request bentrok.
     */
    private static function legacyRowCollidesWithHrRequest(string $requestType, int $legacyApiId, HrRequest $hrRequest): bool
    {
        $legacyTable = self::legacyTableForType($requestType);
        if (!$legacyTable) {
            return false;
        }

        $legacyRow = DB::connection(config('greatday.legacy_apps_connection', 'intilab_apps'))
            ->table($legacyTable)
            ->where('id', $legacyApiId)
            ->first();

        if (!$legacyRow) {
            return false;
        }

        $legacyEmployeeId = (int) ($legacyRow->employee_id ?? $legacyRow->karyawan_id ?? 0);
        $hrEmployeeId = (int) $hrRequest->karyawan_id;

        return $legacyEmployeeId > 0 && $hrEmployeeId > 0 && $legacyEmployeeId !== $hrEmployeeId;
    }
}
