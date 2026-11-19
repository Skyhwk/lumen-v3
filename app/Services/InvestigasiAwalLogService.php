<?php

namespace App\Services;

use App\Models\BakInvestigasiStatusLog;
use Illuminate\Support\Facades\Schema;

class InvestigasiAwalLogService
{
    public static function log(
        int $investigasiId,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        ?int $actorId,
        ?string $actorName,
        ?string $notes = null,
        ?array $metadata = null
    ): void {
        if (!Schema::hasTable('bak_investigasi_status_logs')) {
            return;
        }

        BakInvestigasiStatusLog::create([
            'bak_investigasi_id' => $investigasiId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action' => $action,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'notes' => $notes,
            'metadata' => $metadata,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}