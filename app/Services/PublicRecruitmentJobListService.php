<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PublicRecruitmentJobListService
{
    private const EARLY_STATUSES = ['assessment', 'screening'];

    private const HRD_STATUSES = ['interview_hrd', 'profile_completion'];

    private const USER_STATUSES = ['interview_user'];

    private const EARLY_MIN = 5;

    private const HRD_MIN = 2;

    private const USER_MIN = 2;

    public function filterDuplicatePositions(Collection $jobs): Collection
    {
        if ($jobs->count() <= 1) {
            return $jobs->values();
        }

        $requestIds = $jobs->pluck('id')->filter()->unique()->values()->all();
        $countsByRequestId = $this->getCandidateCountsByRequest($requestIds);

        return $jobs
            ->groupBy(fn ($job) => $this->positionGroupKey($job))
            ->flatMap(function (Collection $group) use ($countsByRequestId) {
                if ($group->count() <= 1) {
                    return $group;
                }

                return collect([$this->pickVisibleJob($group, $countsByRequestId)]);
            })
            ->values();
    }

    private function positionGroupKey(object $job): string
    {
        $divisi = trim((string) ($job->divisi ?? ''));
        if ($divisi !== '') {
            return 'divisi:' . $divisi;
        }

        $alias = trim((string) ($job->divisi_alias ?? ''));

        return 'alias:' . mb_strtolower($alias);
    }

    /**
     * @return array<int, array{early: int, hrd: int, user: int, total: int}>
     */
    private function getCandidateCountsByRequest(array $requestIds): array
    {
        if ($requestIds === []) {
            return [];
        }

        $rows = DB::table('new_recruitment')
            ->whereIn('personnel_request_id', $requestIds)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'rejected');
            })
            ->select('personnel_request_id', 'status', DB::raw('COUNT(*) as total'))
            ->groupBy('personnel_request_id', 'status')
            ->get();

        $counts = [];
        foreach ($requestIds as $id) {
            $counts[(int) $id] = ['early' => 0, 'hrd' => 0, 'user' => 0, 'total' => 0];
        }

        foreach ($rows as $row) {
            $id = (int) $row->personnel_request_id;
            if (!isset($counts[$id])) {
                continue;
            }

            $status = strtolower((string) $row->status);
            $total = (int) $row->total;

            $counts[$id]['total'] += $total;

            if (in_array($status, self::EARLY_STATUSES, true)) {
                $counts[$id]['early'] += $total;
            } elseif (in_array($status, self::HRD_STATUSES, true)) {
                $counts[$id]['hrd'] += $total;
            } elseif (in_array($status, self::USER_STATUSES, true)) {
                $counts[$id]['user'] += $total;
            }
        }

        return $counts;
    }

    private function pickVisibleJob(Collection $jobs, array $countsByRequestId): object
    {
        $sorted = $jobs->sortBy(function ($job) {
            return $job->created_at ?? $job->id ?? 0;
        })->values();

        $allEmpty = $sorted->every(function ($job) use ($countsByRequestId) {
            return ($countsByRequestId[(int) $job->id]['total'] ?? 0) === 0;
        });

        if ($allEmpty) {
            return $sorted->first();
        }

        $primary = $sorted
            ->filter(fn ($job) => ($countsByRequestId[(int) $job->id]['total'] ?? 0) > 0)
            ->sortBy(fn ($job) => $job->created_at ?? $job->id ?? 0)
            ->first();

        if (!$primary) {
            return $sorted->first();
        }

        $primaryCounts = $countsByRequestId[(int) $primary->id] ?? [
            'early' => 0,
            'hrd' => 0,
            'user' => 0,
            'total' => 0,
        ];

        if (!$this->hasAnyQuotaFulfilled($primaryCounts)) {
            return $primary;
        }

        $alternates = $sorted->filter(fn ($job) => (int) $job->id !== (int) $primary->id);

        $idleAlternate = $alternates->first(
            fn ($job) => ($countsByRequestId[(int) $job->id]['total'] ?? 0) === 0
        );
        if ($idleAlternate) {
            return $idleAlternate;
        }

        $alternate = $alternates
            ->sortByDesc(fn ($job) => $job->created_at ?? $job->id ?? 0)
            ->first();

        return $alternate ?? $primary;
    }

    private function hasAnyQuotaFulfilled(array $counts): bool
    {
        return $counts['early'] >= self::EARLY_MIN
            || $counts['hrd'] >= self::HRD_MIN
            || $counts['user'] >= self::USER_MIN;
    }
}
