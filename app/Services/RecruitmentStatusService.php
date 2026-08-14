<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecruitmentStatusService
{
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
