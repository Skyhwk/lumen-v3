<?php

namespace App\Services;

use App\Models\NewRecruitment;
use App\Models\SallaryOffer;
use Carbon\Carbon;

class SallaryOfferService
{
    public static function getActive(int $recruitmentId): ?SallaryOffer
    {
        return SallaryOffer::query()
            ->where('new_recruitment_id', $recruitmentId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    public static function getLatest(int $recruitmentId): ?SallaryOffer
    {
        return SallaryOffer::query()
            ->where('new_recruitment_id', $recruitmentId)
            ->orderByDesc('id')
            ->first();
    }

    public static function deactivateActive(int $recruitmentId, array $extra = []): void
    {
        SallaryOffer::query()
            ->where('new_recruitment_id', $recruitmentId)
            ->where('is_active', true)
            ->update(array_merge(['is_active' => false], $extra));
    }

    public static function markFinanceRejected(int $recruitmentId, string $reason, ?string $by = null): void
    {
        $offer = self::getActive($recruitmentId);

        if (!$offer) {
            return;
        }

        $offer->update([
            'is_active' => false,
            'keterangan_reject' => $reason,
            'rejected_by' => $by,
            'rejected_at' => Carbon::now(),
            'updated_by' => $by,
        ]);
    }

    public static function upsertActive(int $recruitmentId, array $data, ?string $by = null, bool $forceNew = false): SallaryOffer
    {
        if ($forceNew) {
            self::deactivateActive($recruitmentId);

            return SallaryOffer::create(array_merge($data, [
                'new_recruitment_id' => $recruitmentId,
                'is_active' => true,
                'created_by' => $by ?? ($data['created_by'] ?? null),
                'updated_by' => $by ?? ($data['updated_by'] ?? null),
            ]));
        }

        $active = self::getActive($recruitmentId);

        if ($active) {
            $active->update(array_merge($data, [
                'updated_by' => $by ?? ($data['updated_by'] ?? null),
            ]));

            return $active->fresh();
        }

        return SallaryOffer::create(array_merge($data, [
            'new_recruitment_id' => $recruitmentId,
            'is_active' => true,
            'created_by' => $by ?? ($data['created_by'] ?? null),
            'updated_by' => $by ?? ($data['updated_by'] ?? null),
        ]));
    }

    /**
     * Gaji dari User (interview user) — hanya referensi, tidak boleh diubah dari HRD.
     */
    public static function resolveUserReferenceSalary(NewRecruitment $applicant): ?float
    {
        $recruitmentId = (int) $applicant->id;

        $active = self::getActive($recruitmentId);
        if ($active && $active->sallary_offer_user !== null && $active->sallary_offer_user !== '') {
            return (float) $active->sallary_offer_user;
        }

        $latest = self::getLatest($recruitmentId);
        if ($latest && $latest->sallary_offer_user !== null && $latest->sallary_offer_user !== '') {
            return (float) $latest->sallary_offer_user;
        }

        $userInterview = $applicant->relationLoaded('userInterview')
            ? $applicant->userInterview
            : $applicant->userInterview()->first();

        if ($userInterview && $userInterview->ekspetasi_gaji !== null && $userInterview->ekspetasi_gaji !== '') {
            return (float) $userInterview->ekspetasi_gaji;
        }

        return null;
    }

    public static function getLatestRejectReason(int $recruitmentId): ?string
    {
        $offer = SallaryOffer::query()
            ->where('new_recruitment_id', $recruitmentId)
            ->whereNotNull('keterangan_reject')
            ->where('keterangan_reject', '!=', '')
            ->orderByDesc('id')
            ->first();

        if (!$offer) {
            return null;
        }

        $reason = trim((string) $offer->keterangan_reject);

        return $reason !== '' ? $reason : null;
    }
}
