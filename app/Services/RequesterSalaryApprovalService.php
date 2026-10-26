<?php

namespace App\Services;

use App\Models\CandidateDataOffers;
use App\Models\DecisionSalary;
use App\Models\NewRecruitment;
use App\Models\SallaryOffer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RequesterSalaryApprovalService
{
    public const STATUS_NOT_REQUIRED = 'not_required';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_NEGOTIATED = 'negotiated';

    public const HISTORY_NOT_REQUIRED = 'requester_salary_not_required';
    public const HISTORY_PENDING = 'requester_salary_approval_pending';
    public const HISTORY_APPROVED = 'requester_salary_offer_approved';
    public const HISTORY_REJECTED = 'requester_salary_offer_rejected';
    public const HISTORY_NEGOTIATED = 'requester_salary_offer_negotiated';

    public static function normalizeAmount($value, bool $allowZero = false): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = self::parseRupiahToInt($value);
        if ($parsed === null) {
            return null;
        }

        return $parsed > 0 || ($allowZero && $parsed === 0) ? $parsed : null;
    }

    /** API decimal (500000.00), format ID (500.000), atau angka. */
    public static function parseRupiahToInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (int) round((float) $value);
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $str)) {
            return (int) str_replace('.', '', $str);
        }

        if (preg_match('/^\d+\.\d{1,2}$/', $str)) {
            return (int) round((float) $str);
        }

        $digits = preg_replace('/[^0-9]/', '', $str);

        return ($digits === null || $digits === '') ? null : (int) $digits;
    }

    public static function amountsMatch($a, $b): bool
    {
        $left = self::normalizeAmount($a);
        $right = self::normalizeAmount($b);

        if ($left === null || $right === null) {
            return false;
        }

        return $left === $right;
    }

    /** Nominal gaji pokok HRD yang dibandingkan dengan Salary Offer User (bukan GP + tunjangan). */
    public static function resolveHrdComparableAmount(NewRecruitment $applicant, $fallbackHrdAmount = null): ?int
    {
        $fromFallback = self::normalizeAmount($fallbackHrdAmount);
        if ($fromFallback !== null) {
            return $fromFallback;
        }

        $active = SallaryOfferService::getActive((int) $applicant->id);
        if ($active) {
            $fromOffer = self::normalizeAmount($active->sallary_offer_hrd);
            if ($fromOffer !== null) {
                return $fromOffer;
            }
        }

        $cdo = CandidateDataOffers::where('new_recruitment_id', $applicant->id)->first();
        if ($cdo) {
            return self::normalizeAmount($cdo->gaji_pokok);
        }

        return null;
    }

    public static function resolveUserAmount(NewRecruitment $applicant): ?int
    {
        return self::normalizeAmount(
            SallaryOfferService::resolveUserReferenceSalary($applicant), true
        );
    }

    public static function resolveUserTunjanganAmount(NewRecruitment $applicant): int
    {
        if (!SallaryOfferService::hasTunjanganGajiUserColumn()) {
            return 0;
        }

        return self::normalizeAmount(SallaryOfferService::resolveUserTunjangan($applicant), true) ?? 0;
    }

    public static function resolveHrdTunjanganKerja(NewRecruitment $applicant): int
    {
        $cdo = CandidateDataOffers::where('new_recruitment_id', $applicant->id)->first();
        if (!$cdo) {
            return 0;
        }

        return self::normalizeAmount($cdo->tunjangan_kerja, true) ?? 0;
    }

    /**
     * Gate Approval Salary User: GP dan tunjangan HRD harus sama dengan referensi User (bukan total GP+TK saja).
     */
    public static function hrdSalaryMatchesUserReference(
        NewRecruitment $applicant,
        ?int $hrdGajiPokok = null,
        ?int $hrdTunjanganKerja = null
    ): bool {
        $userGajiPokok = self::resolveUserAmount($applicant);
        if ($userGajiPokok === null || $userGajiPokok === 0) {
            return true;
        }

        $userTunjangan = self::resolveUserTunjanganAmount($applicant);
        $hrdGp = $hrdGajiPokok ?? self::resolveHrdComparableAmount($applicant);
        if ($hrdGp === null) {
            return false;
        }
        $hrdTk = $hrdTunjanganKerja ?? self::resolveHrdTunjanganKerja($applicant);

        return self::amountsMatch($userGajiPokok, $hrdGp) && self::amountsMatch($userTunjangan, $hrdTk);
    }

    public static function resolvePencadanganUpah(NewRecruitment $applicant): ?int
    {
        $cdo = CandidateDataOffers::where('new_recruitment_id', $applicant->id)->first();
        if (!$cdo) {
            return null;
        }

        return self::normalizeAmount($cdo->pencadangan_upah) ?? 0;
    }

    /**
     * Dipanggil setelah HRD menyimpan gaji di Final Decision.
     * Mismatch → insert putaran baru di decision_salary (pending).
     */
    public static function syncAfterHrdSalarySave(
        NewRecruitment $applicant,
        SallaryOffer $offer,
        $hrdAmount,
        ?string $by = null
    ): string {
        if ($offer->requester_salary_status === self::STATUS_PENDING) {
            throw new \RuntimeException('Gaji tidak dapat diedit selama menunggu Approval Salary User.');
        }

        $candidateOfferingResubmit = RecruitmentStatusService::isAwaitingCandidateOfferingNegotiateResubmit($applicant)
            || RecruitmentStatusService::isAwaitingCandidateOfferingResubmit($applicant);

        if ($offer->requester_salary_status === self::STATUS_APPROVED && !$candidateOfferingResubmit) {
            throw new \RuntimeException('Gaji sudah disetujui User.');
        }
        $userAmount = self::resolveUserAmount($applicant);
        $hrdNormalized = self::normalizeAmount($hrdAmount)
            ?? self::resolveHrdComparableAmount($applicant, $hrdAmount);
        $pencadangan = self::resolvePencadanganUpah($applicant);

        if ($userAmount === null || $userAmount === 0 || $hrdNormalized === null) {
            self::markOfferStatus($offer, self::STATUS_NOT_REQUIRED);
            self::supersedePendingRounds((int) $applicant->id);
            self::appendHistory(
                (int) $applicant->id,
                self::HISTORY_NOT_REQUIRED,
                $by,
                [
                    'user_amount' => $userAmount,
                    'hrd_amount' => $hrdNormalized,
                    'reason' => $userAmount === 0 ? 'Salary Offer User bernilai 0; approval tidak diperlukan.' : null,
                ]
            );

            return self::STATUS_NOT_REQUIRED;
        }

        $hrdTunjangan = self::resolveHrdTunjanganKerja($applicant);
        $userTunjangan = self::resolveUserTunjanganAmount($applicant);

        if (self::hrdSalaryMatchesUserReference($applicant, $hrdNormalized, $hrdTunjangan)) {
            $previousDecision = DecisionSalary::where('new_recruitment_id', $applicant->id)
                ->orderByDesc('id')->first();
            self::markOfferStatus($offer, self::STATUS_NOT_REQUIRED);
            self::supersedePendingRounds((int) $applicant->id);
            self::appendHistory(
                (int) $applicant->id,
                self::HISTORY_NOT_REQUIRED,
                $by,
                [
                    'user_amount' => $userAmount,
                    'hrd_amount' => $hrdNormalized,
                    'user_tunjangan' => $userTunjangan,
                    'hrd_tunjangan' => $hrdTunjangan,
                    'previous_hrd_amount' => $previousDecision ? $previousDecision->hrd_amount : null,
                    'reason' => 'Gaji pokok dan tunjangan HRD sudah sesuai User; approval tidak diperlukan.',
                ]
            );

            return self::STATUS_NOT_REQUIRED;
        }

        self::supersedePendingRounds((int) $applicant->id);

        $nextRound = (int) DecisionSalary::query()
            ->where('new_recruitment_id', (int) $applicant->id)
            ->max('round');
        $nextRound = $nextRound > 0 ? $nextRound + 1 : 1;

        DecisionSalary::create([
            'new_recruitment_id' => (int) $applicant->id,
            'sallary_offer_id' => (int) $offer->id,
            'personnel_request_id' => $applicant->personnel_request_id
                ? (int) $applicant->personnel_request_id
                : null,
            'user_amount' => $userAmount,
            'hrd_amount' => $hrdNormalized,
            'pencadangan_upah' => $pencadangan,
            'round' => $nextRound,
            'decision' => DecisionSalary::DECISION_PENDING,
            'created_by' => $by,
        ]);

        self::markOfferStatus($offer, self::STATUS_PENDING);
        self::appendHistory(
            (int) $applicant->id,
            self::HISTORY_PENDING,
            $by,
            [
                'user_amount' => $userAmount,
                'hrd_amount' => $hrdNormalized,
                'user_tunjangan' => $userTunjangan,
                'hrd_tunjangan' => $hrdTunjangan,
                'round' => $nextRound,
                'sallary_offer_id' => (int) $offer->id,
            ]
        );

        // Notify only after the approval round is committed, never for a rolled-back save.
        DB::afterCommit(function () use ($applicant, $userAmount, $hrdNormalized, $nextRound) {
            app(AtsNotificationService::class)->requesterSalaryApprovalRequested(
                $applicant, $userAmount, $hrdNormalized, $nextRound
            );
        });

        return self::STATUS_PENDING;
    }

    public static function canSendCandidateOffering(NewRecruitment $applicant, ?SallaryOffer $offer = null): array
    {
        $offer = $offer ?: SallaryOfferService::getActive((int) $applicant->id);
        if (!$offer) {
            return [
                'allowed' => true,
                'status' => self::STATUS_NOT_REQUIRED,
                'message' => null,
            ];
        }

        $userAmount = self::resolveUserAmount($applicant);
        if ($userAmount === null || $userAmount === 0) {
            return [
                'allowed' => true,
                'status' => self::STATUS_NOT_REQUIRED,
                'message' => null,
            ];
        }

        $status = $offer->requester_salary_status ?: self::STATUS_NOT_REQUIRED;

        // User sudah menyetujui putaran decision_salary — HRD boleh beda dari referensi interview User.
        if ($status === self::STATUS_APPROVED) {
            return [
                'allowed' => true,
                'status' => $status,
                'message' => null,
            ];
        }

        if ($status === self::STATUS_NOT_REQUIRED) {
            if (!self::hrdSalaryMatchesUserReference($applicant)) {
                return [
                    'allowed' => false,
                    'status' => self::STATUS_PENDING,
                    'message' => 'Gaji pokok atau tunjangan HRD berbeda dari User. Simpan ulang gaji atau minta Approval Salary User.',
                ];
            }

            return [
                'allowed' => true,
                'status' => $status,
                'message' => null,
            ];
        }

        if ($status === self::STATUS_NEGOTIATED) {
            return [
                'allowed' => false,
                'status' => $status,
                'message' => 'User mengajukan negosiasi gaji. Sesuaikan penawaran HRD lalu minta User menyetujui kembali.',
            ];
        }

        if ($status === self::STATUS_REJECTED) {
            return [
                'allowed' => false,
                'status' => $status,
                'message' => 'Penawaran gaji ditolak User. Silakan input ulang gaji HRD.',
            ];
        }

        if ($status === self::STATUS_PENDING) {
            return [
                'allowed' => false,
                'status' => $status,
                'message' => 'Gaji HRD berbeda dari gaji User. Menunggu persetujuan User di Personnel Request (Approval Salary).',
            ];
        }

        if (self::hrdSalaryMatchesUserReference($applicant)) {
            return [
                'allowed' => true,
                'status' => self::STATUS_NOT_REQUIRED,
                'message' => null,
            ];
        }

        return [
            'allowed' => false,
            'status' => $status,
            'message' => 'Gaji pokok atau tunjangan HRD berbeda dari User. Menunggu persetujuan User di Personnel Request (Approval Salary).',
        ];
    }

    /**
     * User approve / reject satu putaran decision_salary.
     */
    public static function recordDecision(
        DecisionSalary $row,
        string $decision,
        ?string $by = null,
        ?string $reason = null,
        array $extraHistory = []
    ): DecisionSalary {
        return DB::transaction(function () use ($row, $decision, $by, $reason, $extraHistory) {
            NewRecruitment::where('id', $row->new_recruitment_id)->lockForUpdate()->firstOrFail();
            $locked = DecisionSalary::where('id', $row->id)->lockForUpdate()->firstOrFail();
            $offer = SallaryOfferService::getActive((int) $locked->new_recruitment_id);
            $latest = self::getPendingForRecruitment((int) $locked->new_recruitment_id);
            if ($locked->decision !== DecisionSalary::DECISION_PENDING
                || !$latest || (int) $latest->id !== (int) $locked->id
                || !$offer || (int) $offer->id !== (int) $locked->sallary_offer_id
                || $offer->requester_salary_status !== self::STATUS_PENDING
                || !self::amountsMatch($locked->hrd_amount, $offer->sallary_offer_hrd)) {
                throw new \RuntimeException('Pengajuan gaji sudah diproses atau berubah. Silakan muat ulang data.');
            }

            if ($decision === DecisionSalary::DECISION_NEGOTIATED) {
                $locked->update([
                    'user_amount' => self::normalizeAmount($offer->sallary_offer_user),
                ]);
                $locked->refresh();
            } elseif (!self::amountsMatch($locked->user_amount, $offer->sallary_offer_user)) {
                throw new \RuntimeException('Pengajuan gaji sudah diproses atau berubah. Silakan muat ulang data.');
            }

            return self::applyDecision($locked, $decision, $by, $reason, $extraHistory);
        });
    }

    private static function applyDecision(
        DecisionSalary $row,
        string $decision,
        ?string $by,
        ?string $reason,
        array $extraHistory = []
    ): DecisionSalary {
        $decision = strtolower(trim($decision));
        if (!in_array($decision, [
            DecisionSalary::DECISION_APPROVED,
            DecisionSalary::DECISION_REJECTED,
            DecisionSalary::DECISION_NEGOTIATED,
        ], true)) {
            throw new \InvalidArgumentException('Keputusan tidak valid.');
        }

        if ($row->decision !== DecisionSalary::DECISION_PENDING) {
            throw new \RuntimeException('Pengajuan approval gaji sudah diproses.');
        }

        if (in_array($decision, [DecisionSalary::DECISION_REJECTED, DecisionSalary::DECISION_NEGOTIATED], true)) {
            $reason = trim((string) $reason);
            if ($reason === '') {
                throw new \InvalidArgumentException(
                    $decision === DecisionSalary::DECISION_NEGOTIATED
                        ? 'Catatan negosiasi wajib diisi.'
                        : 'Alasan penolakan wajib diisi.'
                );
            }
        }

        $now = Carbon::now();
        $row->update([
            'decision' => $decision,
            'reason' => in_array($decision, [DecisionSalary::DECISION_REJECTED, DecisionSalary::DECISION_NEGOTIATED], true)
                ? $reason
                : null,
            'decided_by' => $by,
            'decided_at' => $now,
        ]);

        $offer = $row->sallary_offer_id
            ? SallaryOffer::find($row->sallary_offer_id)
            : SallaryOfferService::getActive((int) $row->new_recruitment_id);

        if ($decision === DecisionSalary::DECISION_APPROVED) {
            $offerStatus = self::STATUS_APPROVED;
        } elseif ($decision === DecisionSalary::DECISION_NEGOTIATED) {
            $offerStatus = self::STATUS_NEGOTIATED;
        } else {
            $offerStatus = self::STATUS_REJECTED;
        }

        if ($offer) {
            self::markOfferStatus($offer, $offerStatus, $now);
        }

        if ($decision === DecisionSalary::DECISION_APPROVED) {
            $historyStatus = self::HISTORY_APPROVED;
        } elseif ($decision === DecisionSalary::DECISION_NEGOTIATED) {
            $historyStatus = self::HISTORY_NEGOTIATED;
        } else {
            $historyStatus = self::HISTORY_REJECTED;
        }

        self::appendHistory(
            (int) $row->new_recruitment_id,
            $historyStatus,
            $by,
            array_merge([
                'decision_salary_id' => (int) $row->id,
                'user_amount' => self::normalizeAmount($row->user_amount),
                'hrd_amount' => self::normalizeAmount($row->hrd_amount),
                'round' => (int) $row->round,
                'reason' => $reason,
            ], $extraHistory)
        );

        $recruitmentId = (int) $row->new_recruitment_id;
        DB::afterCommit(function () use ($recruitmentId, $decision, $reason) {
            $applicant = NewRecruitment::find($recruitmentId);
            if ($applicant) {
                app(AtsNotificationService::class)->requesterSalaryDecisionMade($applicant, $decision, $reason);
            }
        });

        return $row->fresh();
    }

    public static function getPendingForRecruitment(int $recruitmentId): ?DecisionSalary
    {
        return DecisionSalary::query()
            ->where('new_recruitment_id', $recruitmentId)
            ->pending()
            ->orderByDesc('id')
            ->first();
    }

    public static function historyForRecruitment(int $recruitmentId)
    {
        return DecisionSalary::query()
            ->where('new_recruitment_id', $recruitmentId)
            ->orderByDesc('id')
            ->get();
    }

    private static function markOfferStatus(SallaryOffer $offer, string $status, $decidedAt = null): void
    {
        $payload = [
            'requester_salary_status' => $status,
        ];

        if (in_array($status, [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_NEGOTIATED], true)) {
            $payload['requester_salary_decided_at'] = $decidedAt ?: Carbon::now();
        } else {
            $payload['requester_salary_decided_at'] = null;
        }

        $offer->update($payload);
    }

    private static function supersedePendingRounds(int $recruitmentId): void
    {
        DecisionSalary::query()
            ->where('new_recruitment_id', $recruitmentId)
            ->where('decision', DecisionSalary::DECISION_PENDING)
            ->update([
                'decision' => DecisionSalary::DECISION_SUPERSEDED,
                'updated_at' => Carbon::now(),
            ]);
    }

    private static function appendHistory(int $recruitmentId, string $historyStatus, ?string $by = null, array $extra = []): void
    {
        try {
            $row = NewRecruitment::query()->where('id', $recruitmentId)->first(['id', 'status']);
            if (!$row || empty($row->status)) {
                return;
            }

            $payload = array_filter(array_merge([
                'by' => $by,
            ], $extra), static function ($value) {
                return $value !== null && $value !== '';
            });

            (new RecruitmentStatusService())->update(
                $recruitmentId,
                $row->status,
                Carbon::now(),
                $historyStatus,
                $payload
            );
        } catch (\Throwable $e) {
            Log::warning('RequesterSalaryApprovalService::appendHistory failed', [
                'recruitment_id' => $recruitmentId,
                'status' => $historyStatus,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
