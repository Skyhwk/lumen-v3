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

    public static function hasManagementDecisionApproved($recruitment): bool
    {
        foreach (self::parseMetaHistory($recruitment) as $entry) {
            if (strtolower((string) ($entry['status'] ?? '')) === 'management_decision_approved') {
                return true;
            }
        }

        return false;
    }

    public static function getLatestCandidateOfferingRejectEntry(array $history): ?array
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['status'] ?? '') === 'candidate_offering_rejected') {
                return $history[$i];
            }
        }

        return null;
    }

    public static function isAwaitingCandidateOfferingResubmit($recruitment): bool
    {
        $history = self::parseMetaHistory($recruitment);
        $rejectedIndex = null;

        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['status'] ?? '') === 'candidate_offering_rejected') {
                $rejectedIndex = $i;
                break;
            }
        }

        if ($rejectedIndex === null) {
            return false;
        }

        for ($i = $rejectedIndex + 1; $i < count($history); $i++) {
            if (($history[$i]['status'] ?? '') === 'candidate_offering_sent') {
                return false;
            }
        }

        return true;
    }

    public static function hasHrdSalaryInputSavedAfterCandidateReject($recruitment): bool
    {
        if (!self::isAwaitingCandidateOfferingResubmit($recruitment)) {
            return false;
        }

        $history = self::parseMetaHistory($recruitment);
        $rejectedIndex = null;

        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['status'] ?? '') === 'candidate_offering_rejected') {
                $rejectedIndex = $i;
                break;
            }
        }

        if ($rejectedIndex === null) {
            return false;
        }

        for ($i = $rejectedIndex + 1; $i < count($history); $i++) {
            if (($history[$i]['status'] ?? '') === 'hrd_salary_input_saved') {
                return true;
            }
        }

        return false;
    }

    public static function isCandidateOfferingRejected($recruitment): bool
    {
        return self::isAwaitingCandidateOfferingResubmit($recruitment);
    }

    public static function getCandidateOfferingRejectReason($recruitment): ?string
    {
        if (!self::isAwaitingCandidateOfferingResubmit($recruitment)) {
            return null;
        }

        $entry = self::getLatestCandidateOfferingRejectEntry(self::parseMetaHistory($recruitment));
        if (!$entry) {
            return null;
        }

        $reason = trim((string) ($entry['reject_reason'] ?? $entry['alasan_reject'] ?? $entry['reason'] ?? ''));

        return $reason !== '' ? $reason : null;
    }

    public static function isAwaitingIbuDirekturApproval($recruitment): bool
    {
        if (self::isAwaitingCandidateOfferingResubmit($recruitment)) {
            return false;
        }

        if (self::isAwaitingDirectorSalaryResubmit($recruitment)) {
            return false;
        }

        if (self::hasManagementDecisionApproved($recruitment)) {
            return false;
        }

        $status = strtolower(trim((string) (is_object($recruitment) ? ($recruitment->status ?? '') : ($recruitment['status'] ?? ''))));
        return $status === 'management_decision';
    }

    public static function isDirectorSalaryDecisionSuperseded(array $history, int $decisionIndex): bool
    {
        for ($i = $decisionIndex + 1; $i < count($history); $i++) {
            if (($history[$i]['status'] ?? '') === 'finance_approved') {
                return true;
            }
        }

        return false;
    }

    public static function getLatestDirectorSalaryDecision(array $history): ?string
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $status = (string) ($history[$i]['status'] ?? '');
            if (!preg_match('/^internal_sallary_offer_(approved|rejected|negotiated)$/', $status, $matches)) {
                continue;
            }

            if (self::isDirectorSalaryDecisionSuperseded($history, $i)) {
                continue;
            }

            return $matches[1];
        }

        return null;
    }

    public static function isReadyToSendDirectorAfterFinanceApprove($recruitment): bool
    {
        if (!self::hasFinanceApproved($recruitment)) {
            return false;
        }

        if (self::isAwaitingDirectorSalaryResubmit($recruitment)
            || self::isAwaitingFinanceResubmit($recruitment)
            || self::isAwaitingDirectorSalaryApproval($recruitment)) {
            return false;
        }

        $history = self::parseMetaHistory($recruitment);

        return self::getLatestDirectorSalaryDecision($history) === null;
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
        $history = self::parseMetaHistory($recruitment);
        $directorResubmitIndex = self::getLatestDirectorSalaryResubmitIndex($history);

        if ($directorResubmitIndex !== null) {
            for ($i = $directorResubmitIndex + 1; $i < count($history); $i++) {
                if (($history[$i]['status'] ?? '') === 'finance_approved') {
                    return true;
                }
            }

            return false;
        }

        $entry = self::getLatestFinanceHistoryEntry($history);

        return strtolower((string) ($entry['status'] ?? '')) === 'finance_approved';
    }

    public static function hasFinanceRejected($recruitment): bool
    {
        if (!self::isAwaitingFinanceResubmit($recruitment)) {
            return false;
        }

        $entry = self::getLatestFinanceHistoryEntry(self::parseMetaHistory($recruitment));

        return strtolower((string) ($entry['status'] ?? '')) === 'finance_rejected';
    }

    public static function isWaitingCandidateAfterFinanceResubmit($recruitment): bool
    {
        if (!self::hasHistoryStatusAfterFinanceReject($recruitment, 'candidate_offering_sent')) {
            return false;
        }

        $last = self::getLastHistoryEntry($recruitment);

        return ($last['status'] ?? '') === 'candidate_offering_sent';
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

    public static function getLatestDirectorSalaryRejectIndex(array $history): ?int
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['status'] ?? '') === 'internal_sallary_offer_rejected') {
                return $i;
            }
        }

        return null;
    }

    public static function getLatestDirectorSalaryNegotiateIndex(array $history): ?int
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['status'] ?? '') === 'internal_sallary_offer_negotiated') {
                return $i;
            }
        }

        return null;
    }

    public static function getLatestDirectorSalaryResubmitIndex(array $history): ?int
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $status = (string) ($history[$i]['status'] ?? '');
            if (preg_match('/^internal_sallary_offer_(rejected|negotiated)$/', $status)) {
                return $i;
            }
        }

        return null;
    }

    public static function isAwaitingDirectorSalaryResubmit($recruitment): bool
    {
        $history = self::parseMetaHistory($recruitment);
        $decisionIndex = self::getLatestDirectorSalaryResubmitIndex($history);

        if ($decisionIndex === null) {
            return false;
        }

        for ($i = $decisionIndex + 1; $i < count($history); $i++) {
            $status = (string) ($history[$i]['status'] ?? '');
            if (in_array($status, [
                'candidate_offering_sent',
                'finance_approved',
                'candidate_offering_approved',
                'internal_sallary_offer_approved',
                'director_salary_email_sent',
            ], true)) {
                return false;
            }
        }

        return true;
    }

    public static function isAwaitingDirectorSalaryRejectResubmit($recruitment): bool
    {
        if (!self::isAwaitingDirectorSalaryResubmit($recruitment)) {
            return false;
        }

        $history = self::parseMetaHistory($recruitment);
        $decisionIndex = self::getLatestDirectorSalaryResubmitIndex($history);

        return $decisionIndex !== null
            && ($history[$decisionIndex]['status'] ?? '') === 'internal_sallary_offer_rejected';
    }

    public static function isAwaitingDirectorSalaryNegotiateResubmit($recruitment): bool
    {
        if (!self::isAwaitingDirectorSalaryResubmit($recruitment)) {
            return false;
        }

        $history = self::parseMetaHistory($recruitment);
        $decisionIndex = self::getLatestDirectorSalaryResubmitIndex($history);

        return $decisionIndex !== null
            && ($history[$decisionIndex]['status'] ?? '') === 'internal_sallary_offer_negotiated';
    }

    public static function hasHistoryStatusAfterDirectorResubmit($recruitment, string $status): bool
    {
        $history = self::parseMetaHistory($recruitment);
        $decisionIndex = self::getLatestDirectorSalaryResubmitIndex($history);

        if ($decisionIndex === null) {
            return false;
        }

        for ($i = $decisionIndex + 1; $i < count($history); $i++) {
            if (($history[$i]['status'] ?? '') === $status) {
                return true;
            }
        }

        return false;
    }

    /** @deprecated use hasHistoryStatusAfterDirectorResubmit */
    public static function hasHistoryStatusAfterDirectorReject($recruitment, string $status): bool
    {
        return self::hasHistoryStatusAfterDirectorResubmit($recruitment, $status);
    }

    public static function isWaitingCandidateAfterDirectorResubmit($recruitment): bool
    {
        if (!self::hasHistoryStatusAfterDirectorResubmit($recruitment, 'candidate_offering_sent')) {
            return false;
        }

        $last = self::getLastHistoryEntry($recruitment);

        return ($last['status'] ?? '') === 'candidate_offering_sent';
    }

    public static function hasHrdSalaryInputSavedAfterDirectorResubmit($recruitment): bool
    {
        if (!self::isAwaitingDirectorSalaryResubmit($recruitment)) {
            return false;
        }

        $history = self::parseMetaHistory($recruitment);
        $decisionIndex = self::getLatestDirectorSalaryResubmitIndex($history);

        if ($decisionIndex === null) {
            return false;
        }

        for ($i = $decisionIndex + 1; $i < count($history); $i++) {
            if (($history[$i]['status'] ?? '') === 'hrd_salary_input_saved') {
                return true;
            }
        }

        return false;
    }

    public static function hasHrdSalaryInputSavedAfterDirectorReject($recruitment): bool
    {
        return self::isAwaitingDirectorSalaryRejectResubmit($recruitment)
            && self::hasHrdSalaryInputSavedAfterDirectorResubmit($recruitment);
    }

    public static function hasHrdSalaryInputSavedAfterDirectorNegotiate($recruitment): bool
    {
        return self::isAwaitingDirectorSalaryNegotiateResubmit($recruitment)
            && self::hasHrdSalaryInputSavedAfterDirectorResubmit($recruitment);
    }

    public static function getDirectorSalaryNegotiateAmount($recruitment): ?string
    {
        if (!self::isAwaitingDirectorSalaryNegotiateResubmit($recruitment)) {
            return null;
        }

        $history = self::parseMetaHistory($recruitment);
        $decisionIndex = self::getLatestDirectorSalaryResubmitIndex($history);

        if ($decisionIndex === null) {
            return null;
        }

        $amount = trim((string) ($history[$decisionIndex]['negotiated_amount'] ?? ''));

        return $amount !== '' ? $amount : null;
    }

    public static function getDirectorSalaryRejectReason($recruitment): ?string
    {
        if (!self::isAwaitingDirectorSalaryRejectResubmit($recruitment)) {
            return null;
        }

        $history = self::parseMetaHistory($recruitment);

        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['status'] ?? '') !== 'internal_sallary_offer_rejected') {
                continue;
            }

            $reason = trim((string) ($history[$i]['reject_reason'] ?? $history[$i]['alasan_reject'] ?? $history[$i]['reason'] ?? ''));

            return $reason !== '' ? $reason : null;
        }

        return null;
    }

    /**
     * Kandidat sudah ditolak dan menerima notifikasi tidak lolos seleksi
     * (screening, interview HRD, interview user, atau keputusan final HRD).
     */
    public static function isRejectedKandidat($recruitment): bool
    {
        $flag = is_object($recruitment)
            ? ($recruitment->is_rejected_kandidat ?? null)
            : ($recruitment['is_rejected_kandidat'] ?? null);

        if ((int) $flag === 1) {
            return true;
        }

        $rejectInterviewUserAt = is_object($recruitment)
            ? ($recruitment->reject_interview_user_at ?? null)
            : ($recruitment['reject_interview_user_at'] ?? null);

        if ($rejectInterviewUserAt !== null && trim((string) $rejectInterviewUserAt) !== '') {
            return true;
        }

        if (self::hasHrdFinalDecisionRejected($recruitment)) {
            return true;
        }

        $status = strtolower(trim((string) (is_object($recruitment) ? ($recruitment->status ?? '') : ($recruitment['status'] ?? ''))));
        if ($status !== 'rejected') {
            return false;
        }

        $rejectedBy = is_object($recruitment)
            ? ($recruitment->rejected_by ?? null)
            : ($recruitment['rejected_by'] ?? null);

        return $rejectedBy !== null && trim((string) $rejectedBy) !== '';
    }

    public static function markRejectedKandidat(int $recruitmentId, string $by, ?string $reason = null, $at = null): void
    {
        $at = $at ? Carbon::parse($at) : Carbon::now();
        $by = trim($by);
        $reason = trim((string) ($reason ?? ''));

        DB::table('new_recruitment')->where('id', $recruitmentId)->update([
            'is_rejected_kandidat' => true,
            'is_rejected_kandidat_by' => $by !== '' ? $by : null,
            'is_rejected_kandidat_at' => $at,
            'is_rejected_kandidat_reason' => $reason !== '' ? $reason : null,
            'updated_at' => $at,
        ]);
    }

    public static function markFinanceRejected(int $recruitmentId, string $by, ?string $reason = null, $at = null): void
    {
        $at = $at ? Carbon::parse($at) : Carbon::now();
        $by = trim($by);
        $reason = trim((string) ($reason ?? ''));

        DB::table('new_recruitment')->where('id', $recruitmentId)->update([
            'is_reject_finance' => true,
            'is_reject_finance_by' => $by !== '' ? $by : null,
            'is_reject_finance_at' => $at,
            'is_reject_finance_reason' => $reason !== '' ? $reason : null,
            'updated_at' => $at,
        ]);
    }

    public static function clearFinanceRejected(int $recruitmentId, $at = null): void
    {
        $at = $at ? Carbon::parse($at) : Carbon::now();

        DB::table('new_recruitment')->where('id', $recruitmentId)->update([
            'is_reject_finance' => false,
            'is_reject_finance_by' => null,
            'is_reject_finance_at' => null,
            'is_reject_finance_reason' => null,
            'updated_at' => $at,
        ]);
    }

    public static function getFinanceRejectTracking($recruitment): ?array
    {
        $flag = is_object($recruitment)
            ? ($recruitment->is_reject_finance ?? null)
            : ($recruitment['is_reject_finance'] ?? null);

        if ((int) $flag !== 1) {
            return null;
        }

        $by = is_object($recruitment)
            ? ($recruitment->is_reject_finance_by ?? null)
            : ($recruitment['is_reject_finance_by'] ?? null);

        $at = is_object($recruitment)
            ? ($recruitment->is_reject_finance_at ?? null)
            : ($recruitment['is_reject_finance_at'] ?? null);

        $reason = is_object($recruitment)
            ? ($recruitment->is_reject_finance_reason ?? null)
            : ($recruitment['is_reject_finance_reason'] ?? null);

        return [
            'by' => ($by !== null && trim((string) $by) !== '') ? (string) $by : null,
            'at' => ($at !== null && trim((string) $at) !== '') ? (string) $at : null,
            'reason' => ($reason !== null && trim((string) $reason) !== '') ? (string) $reason : null,
        ];
    }

    public static function getRejectedKandidatTracking($recruitment): ?array
    {
        if (!self::isRejectedKandidat($recruitment)) {
            return null;
        }

        $by = is_object($recruitment)
            ? ($recruitment->is_rejected_kandidat_by ?? null)
            : ($recruitment['is_rejected_kandidat_by'] ?? null);

        $at = is_object($recruitment)
            ? ($recruitment->is_rejected_kandidat_at ?? null)
            : ($recruitment['is_rejected_kandidat_at'] ?? null);

        $reason = is_object($recruitment)
            ? ($recruitment->is_rejected_kandidat_reason ?? null)
            : ($recruitment['is_rejected_kandidat_reason'] ?? null);

        if ($by === null || trim((string) $by) === '') {
            $by = is_object($recruitment)
                ? ($recruitment->rejected_by ?? $recruitment->reject_interview_user_by ?? null)
                : ($recruitment['rejected_by'] ?? $recruitment['reject_interview_user_by'] ?? null);
        }

        if ($at === null || trim((string) $at) === '') {
            $at = is_object($recruitment)
                ? ($recruitment->rejected_at ?? $recruitment->reject_interview_user_at ?? null)
                : ($recruitment['rejected_at'] ?? $recruitment['reject_interview_user_at'] ?? null);
        }

        if ($reason === null || trim((string) $reason) === '') {
            $reason = is_object($recruitment)
                ? ($recruitment->alasan_reject ?? null)
                : ($recruitment['alasan_reject'] ?? null);
        }

        if (($reason === null || trim((string) $reason) === '') && self::hasHrdFinalDecisionRejected($recruitment)) {
            foreach (self::parseMetaHistory($recruitment) as $entry) {
                if (($entry['status'] ?? '') !== 'hrd_final_decision_rejected') {
                    continue;
                }

                $reason = trim((string) ($entry['reject_reason'] ?? $entry['alasan_reject'] ?? $entry['reason'] ?? ''));
                $by = $by ?: ($entry['by'] ?? null);
                $at = $at ?: ($entry['at'] ?? null);
                break;
            }
        }

        return [
            'by' => ($by !== null && trim((string) $by) !== '') ? (string) $by : null,
            'at' => ($at !== null && trim((string) $at) !== '') ? (string) $at : null,
            'reason' => ($reason !== null && trim((string) $reason) !== '') ? (string) $reason : null,
        ];
    }

    /**
     * @deprecated Gunakan isRejectedKandidat()
     */
    public static function isHrdRejected($recruitment): bool
    {
        return self::isRejectedKandidat($recruitment);
    }

    /**
     * Kandidat ditolak HRD di tahap interview HRD (legacy heuristic).
     */
    private static function isLegacyRejectedByHrdInterview($recruitment): bool
    {
        $rejectedBy = is_object($recruitment)
            ? ($recruitment->rejected_by ?? null)
            : ($recruitment['rejected_by'] ?? null);

        if ($rejectedBy === null || trim((string) $rejectedBy) === '') {
            return false;
        }

        $isApprovedHrd = is_object($recruitment)
            ? ($recruitment->is_approved_interview_hrd ?? false)
            : ($recruitment['is_approved_interview_hrd'] ?? false);

        $approvedBy = is_object($recruitment)
            ? ($recruitment->approved_interview_hrd_by ?? null)
            : ($recruitment['approved_interview_hrd_by'] ?? null);

        return !((bool) $isApprovedHrd) && ($approvedBy === null || trim((string) $approvedBy) === '');
    }

    /**
     * Kandidat ditolak HRD di tahap screening/interview (belum lulus interview HRD).
     */
    public static function isRejectedByHrdBeforeFinalDecision($recruitment): bool
    {
        if (self::hasHrdFinalDecisionRejected($recruitment)) {
            return false;
        }

        if ((int) (is_object($recruitment)
            ? ($recruitment->is_rejected_kandidat ?? 0)
            : ($recruitment['is_rejected_kandidat'] ?? 0)) === 1) {
            $rejectUserAt = is_object($recruitment)
                ? ($recruitment->reject_interview_user_at ?? null)
                : ($recruitment['reject_interview_user_at'] ?? null);

            if ($rejectUserAt !== null && trim((string) $rejectUserAt) !== '') {
                return false;
            }

            return true;
        }

        return self::isLegacyRejectedByHrdInterview($recruitment);
    }

    public static function shouldExcludeFromFinalDecisionList($recruitment): bool
    {
        return self::isRejectedKandidat($recruitment);
    }

    public static function hasHrdFinalDecisionRejected($recruitment): bool
    {
        foreach (self::parseMetaHistory($recruitment) as $entry) {
            if (($entry['status'] ?? '') === 'hrd_final_decision_rejected') {
                return true;
            }
        }

        $status = strtolower(trim((string) (is_object($recruitment) ? ($recruitment->status ?? '') : ($recruitment['status'] ?? ''))));

        return $status === 'rejected'
            && !self::isAwaitingDirectorSalaryResubmit($recruitment)
            && !self::isAwaitingFinanceResubmit($recruitment)
            && !self::isAwaitingCandidateOfferingResubmit($recruitment);
    }

    public static function canHrdRejectFromFinalDecision($recruitment): bool
    {
        if (self::hasHrdFinalDecisionRejected($recruitment)) {
            return false;
        }

        return self::isAwaitingDirectorSalaryResubmit($recruitment)
            || self::isAwaitingFinanceResubmit($recruitment)
            || self::isAwaitingCandidateOfferingResubmit($recruitment);
    }

    public static function getPriorRejectionSummaryForHrd($recruitment): ?array
    {
        if (self::isAwaitingDirectorSalaryRejectResubmit($recruitment)) {
            return [
                'source' => 'Direktur',
                'reason' => self::getDirectorSalaryRejectReason($recruitment),
            ];
        }

        if (self::isAwaitingDirectorSalaryNegotiateResubmit($recruitment)) {
            $history = self::parseMetaHistory($recruitment);
            $idx = self::getLatestDirectorSalaryNegotiateIndex($history);

            return [
                'source' => 'Direktur (Negosiasi)',
                'reason' => $idx !== null ? ($history[$idx]['negotiated_amount'] ?? null) : null,
            ];
        }

        if (self::isAwaitingFinanceResubmit($recruitment)) {
            return [
                'source' => 'Finance',
                'reason' => self::getFinanceRejectReason($recruitment),
            ];
        }

        if (self::isAwaitingCandidateOfferingResubmit($recruitment)) {
            return [
                'source' => 'Kandidat',
                'reason' => self::getCandidateOfferingRejectReason($recruitment),
            ];
        }

        return null;
    }

    public static function isAwaitingHrdResubmitAfterDirectorNegotiation($recruitment): bool
    {
        return self::isAwaitingDirectorSalaryResubmit($recruitment);
    }

    public static function getLatestFinanceRejectIndex(array $history): ?int
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['status'] ?? '') === 'finance_rejected') {
                return $i;
            }
        }

        return null;
    }

    public static function isAwaitingFinanceResubmit($recruitment): bool
    {
        $history = self::parseMetaHistory($recruitment);
        $rejectedIndex = self::getLatestFinanceRejectIndex($history);

        if ($rejectedIndex === null) {
            return false;
        }

        for ($i = $rejectedIndex + 1; $i < count($history); $i++) {
            $status = (string) ($history[$i]['status'] ?? '');
            if (in_array($status, ['candidate_offering_sent', 'finance_approved', 'candidate_offering_approved'], true)) {
                return false;
            }
        }

        return true;
    }

    public static function hasHrdSalaryInputSavedAfterFinanceReject($recruitment): bool
    {
        if (!self::isAwaitingFinanceResubmit($recruitment)) {
            return false;
        }

        $history = self::parseMetaHistory($recruitment);
        $rejectedIndex = self::getLatestFinanceRejectIndex($history);

        if ($rejectedIndex === null) {
            return false;
        }

        for ($i = $rejectedIndex + 1; $i < count($history); $i++) {
            if (($history[$i]['status'] ?? '') === 'hrd_salary_input_saved') {
                return true;
            }
        }

        return false;
    }

    public static function hasHistoryStatusAfterFinanceReject($recruitment, string $status): bool
    {
        $history = self::parseMetaHistory($recruitment);
        $rejectedIndex = self::getLatestFinanceRejectIndex($history);

        if ($rejectedIndex === null) {
            return false;
        }

        for ($i = $rejectedIndex + 1; $i < count($history); $i++) {
            if (($history[$i]['status'] ?? '') === $status) {
                return true;
            }
        }

        return false;
    }

    public static function isFinanceSalaryLocked($recruitment): bool
    {
        if (self::isAwaitingFinanceResubmit($recruitment)) {
            return false;
        }

        if (self::isAwaitingDirectorSalaryResubmit($recruitment)) {
            return false;
        }

        if (!self::hasFinanceApproved($recruitment)) {
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
        $tracking = self::getFinanceRejectTracking($recruitment);
        if ($tracking && !empty($tracking['reason'])) {
            return $tracking['reason'];
        }

        if (!self::isAwaitingFinanceResubmit($recruitment)) {
            return null;
        }

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

    public static function getLastHistoryEntry($recruitment): ?array
    {
        $history = self::parseMetaHistory($recruitment);

        if (empty($history)) {
            return null;
        }

        return $history[count($history) - 1];
    }

    public static function isReturnedFromDirectorManagementRejection($recruitment): bool
    {
        $status = strtolower(trim((string) (is_object($recruitment) ? ($recruitment->status ?? '') : ($recruitment['status'] ?? ''))));
        if ($status !== 'interview_hrd') {
            return false;
        }

        $last = self::getLastHistoryEntry($recruitment);

        return strtolower((string) ($last['status'] ?? '')) === 'management_decision_rejected';
    }

    public static function getDirectorManagementRejectReason($recruitment): ?string
    {
        if (!self::isReturnedFromDirectorManagementRejection($recruitment)) {
            return null;
        }

        $last = self::getLastHistoryEntry($recruitment);
        $reason = trim((string) ($last['reject_reason'] ?? $last['alasan_reject'] ?? $last['reason'] ?? ''));

        return $reason !== '' ? $reason : null;
    }

    public static function resolvePipelineStatus($recruitment): ?array
    {
        if (self::isReturnedFromDirectorManagementRejection($recruitment)
            && !self::isRejectedKandidat($recruitment)) {
            return [
                'code' => 'reject_ibu_direktur',
                'label' => 'Reject Ibu Direktur',
            ];
        }

        if (self::isRejectedKandidat($recruitment)) {
            return [
                'code' => 'rejected_kandidat',
                'label' => 'Tidak Lolos Seleksi',
            ];
        }

        return null;
    }

    public static function hasCompletedProfile($recruitment): bool
    {
        $recruitmentId = is_object($recruitment)
            ? ($recruitment->id ?? null)
            : (is_array($recruitment) ? ($recruitment['id'] ?? null) : null);

        if (!$recruitmentId) {
            return false;
        }

        return DB::table('candidate_profiles')
            ->where('new_recruitment_id', $recruitmentId)
            ->exists();
    }

    public static function hasDirectorManagementRejectionInHistory($recruitment): bool
    {
        foreach (self::parseMetaHistory($recruitment) as $entry) {
            if (strtolower((string) ($entry['status'] ?? '')) === 'management_decision_rejected') {
                return true;
            }
        }

        return false;
    }

    public static function shouldSkipProfileCompletionOnHrdPass($recruitment): bool
    {
        return self::hasCompletedProfile($recruitment)
            && self::hasDirectorManagementRejectionInHistory($recruitment);
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
