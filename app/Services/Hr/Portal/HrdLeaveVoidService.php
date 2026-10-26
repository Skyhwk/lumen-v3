<?php

namespace App\Services\Hr\Portal;

use App\Models\Hr\HrRequest;
use App\Models\LeaveRequest;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\HrTableMode;
use App\Services\Hr\LegacyHrMirror;
use App\Services\Hr\PortalHrSync;
use Carbon\Carbon;
use InvalidArgumentException;

class HrdLeaveVoidService
{
    public function void(int $apiId, string $actorDisplayName, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Alasan void wajib diisi.');
        }

        if (HrTableMode::portalReadsHrTables()) {
            $this->voidOnHrTables($apiId, $actorDisplayName, $reason);

            return;
        }

        $this->voidOnLegacy($apiId, $actorDisplayName, $reason);
    }

    private function voidOnHrTables(int $apiId, string $actorDisplayName, string $reason): void
    {
        $hrRequest = HrRequestResolver::findByPortalSliceId(HrRequest::TYPE_LEAVE, $apiId);
        if (!$hrRequest || !$hrRequest->is_active) {
            throw new InvalidArgumentException('Permohonan cuti tidak ditemukan atau sudah tidak aktif.');
        }

        $now = Carbon::now();
        $hrRequest->is_active = false;
        $hrRequest->description = $this->appendVoidNote((string) $hrRequest->description, $reason, $actorDisplayName);
        $hrRequest->updated_by_name = $actorDisplayName;
        $hrRequest->updated_at = $now;
        $hrRequest->save();

        app(LegacyHrMirror::class)->syncVoid($hrRequest->fresh());
    }

    private function voidOnLegacy(int $apiId, string $actorDisplayName, string $reason): void
    {
        $leave = LeaveRequest::on('intilab_apps')
            ->where('id', $apiId)
            ->where('is_active', 1)
            ->first();

        if (!$leave) {
            throw new InvalidArgumentException('Permohonan cuti tidak ditemukan atau sudah tidak aktif.');
        }

        $leave->is_active = 0;
        $leave->description = $this->appendVoidNote((string) $leave->description, $reason, $actorDisplayName);
        $leave->updated_by = $actorDisplayName;
        $leave->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $leave->save();

        app(PortalHrSync::class)->syncLeaveFromLegacy((int) $leave->id);
    }

    private function appendVoidNote(string $description, string $reason, string $actorDisplayName): string
    {
        $note = sprintf('[Void HRD oleh %s: %s]', $actorDisplayName, $reason);
        $description = trim($description);

        return $description === '' ? $note : $description . "\n" . $note;
    }
}
