<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecruitmentStatusService
{
    public function update($recruitmentId, $status, $at = null)
    {
        $recruitment = DB::table('new_recruitment')->where('id', $recruitmentId)->lockForUpdate()->first();
        if (!$recruitment) {
            throw new \RuntimeException('Data kandidat tidak ditemukan.');
        }

        $at = $at ?: Carbon::now();
        $history = json_decode($recruitment->meta_history ?: '[]', true);
        $history = is_array($history) ? $history : [];
        $last = end($history);

        if (($last['status'] ?? null) !== $status) {
            $history[] = [
                'status' => $status,
                'at' => Carbon::parse($at)->toDateTimeString(),
            ];
        }

        DB::table('new_recruitment')->where('id', $recruitmentId)->update([
            'status' => $status,
            'meta_history' => json_encode(array_values($history)),
            'updated_at' => $at,
        ]);
    }
}
