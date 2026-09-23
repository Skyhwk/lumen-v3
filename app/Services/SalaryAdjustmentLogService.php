<?php

namespace App\Services;

use App\Models\SalaryAdjustmentStatusLog;

class SalaryAdjustmentLogService
{
    public static function log(
        int $requestId,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        ?int $actorId,
        ?string $actorName,
        ?string $notes = null,
        ?array $metadata = null
    ): void {
        SalaryAdjustmentStatusLog::create([
            'request_id' => $requestId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action' => $action,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'notes' => $notes,
            'metadata' => $metadata,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        SalaryAdjustmentNotificationService::dispatch(
            $requestId,
            $action,
            $fromStatus,
            $toStatus,
            $actorId,
            $actorName
        );
    }
}
