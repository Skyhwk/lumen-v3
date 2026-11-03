<?php

namespace App\Services\Greatday;

use App\Models\Gd\GdUserToken;
use App\Models\Greatday\UserToken;
use App\Models\MasterKaryawan;
use App\Support\Greatday\GreatdayImpersonation;

class GreatdayImpersonationService
{
    /** @var GreatdayAuthService */
    private $authStore;

    public function __construct(GreatdayAuthService $authStore)
    {
        $this->authStore = $authStore;
    }

    public function canImpersonate(int $karyawanId): bool
    {
        return GreatdayImpersonation::canImpersonate($karyawanId);
    }

    public function tokenIsImpersonating($tokenRow): bool
    {
        return $tokenRow && (bool) ($tokenRow->is_impersonate ?? false);
    }

    public function resolveActorKaryawanId($tokenRow, ?int $requestedImpersonatorId = null): ?int
    {
        if (!$tokenRow) {
            return null;
        }

        $stored = $this->normalizeKaryawanId($tokenRow->impersonator_karyawan_id ?? null);
        if ($stored) {
            return $stored;
        }

        if ($requestedImpersonatorId && $this->canImpersonate($requestedImpersonatorId)) {
            return $requestedImpersonatorId;
        }

        if (!$this->tokenIsImpersonating($tokenRow)) {
            return $this->sessionKaryawanIdFromToken($tokenRow);
        }

        return $this->sessionKaryawanIdFromToken($tokenRow);
    }

    public function impersonationMetaForToken($tokenRow): array
    {
        if (!$tokenRow) {
            return [
                'can_impersonate' => false,
                'is_impersonating' => false,
                'impersonator_karyawan_id' => null,
                'impersonator_name' => null,
            ];
        }

        $isImpersonating = $this->tokenIsImpersonating($tokenRow);
        $actorId = $this->resolveActorKaryawanId($tokenRow);
        $canImpersonate = $actorId ? $this->canImpersonate($actorId) : false;

        $impersonatorKaryawanId = $this->normalizeKaryawanId($tokenRow->impersonator_karyawan_id ?? null);
        $impersonatorName = null;

        if ($isImpersonating && $impersonatorKaryawanId) {
            $impersonatorName = MasterKaryawan::where('id', $impersonatorKaryawanId)->value('nama_lengkap');
        }

        return [
            'can_impersonate' => $canImpersonate,
            'is_impersonating' => $isImpersonating,
            'impersonator_karyawan_id' => $impersonatorKaryawanId,
            'impersonator_name' => $impersonatorName,
        ];
    }

    public function startImpersonation(string $token, int $targetKaryawanId, ?int $requestedImpersonatorId = null): array
    {
        $tokenRow = $this->findTokenByString($token);
        if (!$tokenRow || !$tokenRow->isActive()) {
            return ['ok' => false, 'status' => 403, 'message' => 'Token is invalid or expired'];
        }

        $actorId = $this->resolveActorKaryawanId($tokenRow, $requestedImpersonatorId);
        if (!$actorId || !$this->canImpersonate($actorId)) {
            return ['ok' => false, 'status' => 403, 'message' => 'Anda tidak memiliki akses impersonate'];
        }

        $target = MasterKaryawan::where('id', $targetKaryawanId)->where('is_active', true)->first();
        if (!$target) {
            return ['ok' => false, 'status' => 404, 'message' => 'Karyawan tujuan tidak ditemukan'];
        }

        if ((int) $target->id === (int) $actorId) {
            return ['ok' => false, 'status' => 400, 'message' => 'Tidak perlu impersonate akun sendiri'];
        }

        $impersonatorId = $this->normalizeKaryawanId($tokenRow->impersonator_karyawan_id ?? null) ?: $actorId;

        $this->updateTokenRow($token, [
            'is_impersonate' => true,
            'impersonator_karyawan_id' => $impersonatorId,
            'session_karyawan_id' => (int) $target->id,
        ]);

        return [
            'ok' => true,
            'status' => 200,
            'message' => 'Berhasil impersonate user',
            'impersonator_karyawan_id' => (int) $impersonatorId,
        ];
    }

    public function stopImpersonation(string $token, ?int $requestedImpersonatorId = null): array
    {
        $tokenRow = $this->findTokenByString($token);
        if (!$tokenRow || !$tokenRow->isActive()) {
            return ['ok' => false, 'status' => 403, 'message' => 'Token is invalid or expired'];
        }

        if (!$this->tokenIsImpersonating($tokenRow)) {
            return ['ok' => false, 'status' => 400, 'message' => 'Anda tidak sedang impersonate'];
        }

        $restoreId = $this->normalizeKaryawanId($tokenRow->impersonator_karyawan_id ?? null);
        if (!$restoreId) {
            $restoreId = $this->normalizeKaryawanId($requestedImpersonatorId);
        }

        if (!$restoreId || !$this->canImpersonate($restoreId)) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Tidak dapat kembali ke akun asli. Silakan logout dan login ulang.',
            ];
        }

        if (!MasterKaryawan::where('id', $restoreId)->where('is_active', true)->exists()) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Akun asli tidak valid. Silakan logout dan login ulang.',
            ];
        }

        $this->updateTokenRow($token, [
            'is_impersonate' => false,
            'impersonator_karyawan_id' => null,
            'session_karyawan_id' => $restoreId,
        ]);

        return ['ok' => true, 'status' => 200, 'message' => 'Berhasil kembali ke akun asli'];
    }

    private function findTokenByString(string $token)
    {
        if ($this->authStore->usesProduksiAuth()) {
            return GdUserToken::where('token', $token)->first();
        }

        return UserToken::where('token', $token)->first();
    }

    private function updateTokenRow(string $token, array $payload): void
    {
        $sessionId = (int) $payload['session_karyawan_id'];
        $impersonate = (bool) $payload['is_impersonate'];
        $impersonatorId = $payload['impersonator_karyawan_id'];

        if ($this->authStore->usesProduksiAuth() || $this->authStore->dualWriteAuth()) {
            GdUserToken::where('token', $token)->update([
                'karyawan_id' => $sessionId,
                'is_impersonate' => $impersonate,
                'impersonator_karyawan_id' => $impersonatorId,
            ]);
        }

        if (!$this->authStore->usesProduksiAuth()) {
            UserToken::where('token', $token)->update([
                'user_id' => $sessionId,
                'is_impersonate' => $impersonate,
                'impersonator_karyawan_id' => $impersonatorId,
            ]);
        }
    }

    private function sessionKaryawanIdFromToken($tokenRow): ?int
    {
        if ($tokenRow instanceof GdUserToken) {
            return $this->normalizeKaryawanId($tokenRow->karyawan_id);
        }

        return $this->normalizeKaryawanId($tokenRow->user_id ?? null);
    }

    private function normalizeKaryawanId($value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
