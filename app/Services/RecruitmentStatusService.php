<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecruitmentStatusService
{
    public static function parseMetaHistory($recruitment): array
    {
        if (is_object($recruitment)) {
            $raw = $recruitment->meta_history ?? '[]';
        } elseif (is_array($recruitment)) {
            $raw = $recruitment['meta_history'] ?? '[]';
        } else {
            $raw = $recruitment ?: '[]';
        }

        $history = json_decode($raw ?: '[]', true);
        return is_array($history) ? $history : [];
    }

    public static function getLatestFinanceHistoryEntry(array $history): ?array
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $status = strtolower((string) ($history[$i]['status'] ?? ''));
            if (in_array($status, ['finance_approved', 'finance_rejected', 'waiting_approve_finance'], true)) {
                return $history[$i];
            }
        }

        return null;
    }

    public static function isAwaitingIbuDirekturApproval($recruitment): bool
    {
        $status = strtolower(trim((string) (is_object($recruitment) ? ($recruitment->status ?? '') : ($recruitment['status'] ?? ''))));
        return $status === 'management_decision';
    }

    public static function getLatestDirectorSalaryDecision(array $history): ?string
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $status = (string) ($history[$i]['status'] ?? '');
            if (preg_match('/^internal_sallary_offer_(approved|rejected|negotiated)$/', $status, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public static function isAwaitingDirectorSalaryApproval($recruitment): bool
    {
        if (self::isAwaitingIbuDirekturApproval($recruitment)) {
            return false;
        }

        if (self::isAwaitingHrdResubmitAfterDirectorNegotiation($recruitment)) {
            return false;
        }

        $recruitmentId = is_object($recruitment) ? ($recruitment->id ?? null) : ($recruitment['id'] ?? null);
        if (!$recruitmentId) {
            return false;
        }

        $offer = SallaryOfferService::getActive((int) $recruitmentId);
        if (!$offer || empty($offer->email_sent_at)) {
            return false;
        }

        $history = self::parseMetaHistory($recruitment);
        return self::getLatestDirectorSalaryDecision($history) === null;
    }

    /** @deprecated use isAwaitingDirectorSalaryApproval */
    public static function isAwaitingDirector1SalaryApproval($recruitment): bool
    {
        return self::isAwaitingDirectorSalaryApproval($recruitment);
    }

    public static function hasFinanceApproved($recruitment): bool
    {
        $entry = self::getLatestFinanceHistoryEntry(self::parseMetaHistory($recruitment));
        return strtolower((string) ($entry['status'] ?? '')) === 'finance_approved';
    }

    public static function hasFinanceRejected($recruitment): bool
    {
        $entry = self::getLatestFinanceHistoryEntry(self::parseMetaHistory($recruitment));
        return strtolower((string) ($entry['status'] ?? '')) === 'finance_rejected';
    }

    public static function getLatestDirectorNegotiationIndex(array $history): ?int
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $status = (string) ($history[$i]['status'] ?? '');
            if (preg_match('/_(negotiated)$/', $status) || $status === 'internal_sallary_offer_negotiated') {
                return $i;
            }
        }

        return null;
    }

    public static function isAwaitingHrdResubmitAfterDirectorNegotiation($recruitment): bool
    {
        $history = self::parseMetaHistory($recruitment);
        $negotiatedIndex = self::getLatestDirectorNegotiationIndex($history);

        if ($negotiatedIndex === null) {
            return false;
        }

        for ($i = $negotiatedIndex + 1; $i < count($history); $i++) {
            $status = strtolower((string) ($history[$i]['status'] ?? ''));
            if (in_array($status, ['waiting_approve_finance', 'finance_approved'], true)) {
                return false;
            }
        }

        return true;
    }

    public static function isFinanceSalaryLocked($recruitment): bool
    {
        if (!self::hasFinanceApproved($recruitment)) {
            return false;
        }

        if (self::isAwaitingHrdResubmitAfterDirectorNegotiation($recruitment)) {
            return false;
        }

        return true;
    }

    public static function hasCandidateOfferingEmailSent($recruitment): bool
    {
        foreach (self::parseMetaHistory($recruitment) as $entry) {
            if (strtolower((string) ($entry['status'] ?? '')) === 'candidate_offering_email_sent') {
                return true;
            }
        }

        return false;
    }

    public static function hasPriorFinanceApproval($recruitment): bool
    {
        $history = self::parseMetaHistory($recruitment);

        foreach ($history as $entry) {
            if (strtolower((string) ($entry['status'] ?? '')) === 'finance_approved') {
                return true;
            }
        }

        return false;
    }

    public static function shouldSendCandidateEmailOnFinanceApprove($recruitment): bool
    {
        return !self::hasCandidateOfferingEmailSent($recruitment);
    }

    public static function getFinanceRejectReason($recruitment): ?string
    {
        $recruitmentId = is_object($recruitment) ? ($recruitment->id ?? null) : (is_array($recruitment) ? ($recruitment['id'] ?? null) : null);

        if ($recruitmentId) {
            $offerReason = SallaryOfferService::getLatestRejectReason((int) $recruitmentId);
            if ($offerReason) {
                return $offerReason;
            }
        }

        $history = self::parseMetaHistory($recruitment);

        for ($i = count($history) - 1; $i >= 0; $i--) {
            $status = strtolower((string) ($history[$i]['status'] ?? ''));
            if ($status !== 'finance_rejected') {
                continue;
            }

            $reason = trim((string) ($history[$i]['reject_reason'] ?? $history[$i]['alasan_reject'] ?? $history[$i]['reason'] ?? ''));
            return $reason !== '' ? $reason : null;
        }

        return null;
    }

    public function update($recruitmentId, $status, $at = null, $historyStatus = null, array $extraData = [])
    {
        $recruitment = DB::table('new_recruitment')->where('id', $recruitmentId)->lockForUpdate()->first();
        if (!$recruitment) {
            throw new \RuntimeException('Data kandidat tidak ditemukan.');
        }

        $at = $at ?: Carbon::now();
        $historyStatus = $historyStatus ?: $status;
        $history = json_decode($recruitment->meta_history ?: '[]', true);
        $history = is_array($history) ? $history : [];
        $last = end($history);

        if (($last['status'] ?? null) !== $historyStatus) {
            $history[] = array_merge([
                'status' => $historyStatus,
                'at'     => Carbon::parse($at)->toDateTimeString(),
            ], $extraData);
        }

        DB::table('new_recruitment')->where('id', $recruitmentId)->update([
            'status'       => $status,
            'meta_history' => json_encode(array_values($history)),
            'updated_at'   => $at,
        ]);
    }
}
