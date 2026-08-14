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

    public static function getFinanceRejectReason($recruitment): ?string
    {
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
