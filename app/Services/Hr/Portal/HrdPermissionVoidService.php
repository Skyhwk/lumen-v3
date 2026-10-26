<?php

namespace App\Services\Hr\Portal;

use App\Models\Hr\HrRequest;
use App\Models\PermissionRequest;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\HrTableMode;
use App\Services\Hr\LegacyHrMirror;
use App\Services\Hr\PortalHrSync;
use Carbon\Carbon;
use InvalidArgumentException;

class HrdPermissionVoidService
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
        $hrRequest = HrRequestResolver::findByPortalSliceId(HrRequest::TYPE_PERMISSION, $apiId);
        if (!$hrRequest || !$hrRequest->is_active) {
            throw new InvalidArgumentException('Permohonan izin tidak ditemukan atau sudah tidak aktif.');
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
        $permission = PermissionRequest::on('intilab_apps')
            ->where('id', $apiId)
            ->where('is_active', 1)
            ->first();

        if (!$permission) {
            throw new InvalidArgumentException('Permohonan izin tidak ditemukan atau sudah tidak aktif.');
        }

        $permission->is_active = 0;
        $permission->description = $this->appendVoidNote((string) $permission->description, $reason, $actorDisplayName);
        $permission->updated_by = $actorDisplayName;
        $permission->updated_at = Carbon::now()->format('Y-m-d H:i:s');
        $permission->save();

        app(PortalHrSync::class)->syncPermissionFromLegacy((int) $permission->id);
    }

    private function appendVoidNote(string $description, string $reason, string $actorDisplayName): string
    {
        $note = sprintf('[Void HRD oleh %s: %s]', $actorDisplayName, $reason);
        $description = trim($description);

        return $description === '' ? $note : $description . "\n" . $note;
    }
}
