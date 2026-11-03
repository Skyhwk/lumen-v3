<?php

namespace App\Services\Hr\Concerns;

use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Hr\FormAtasanApprovalChainService;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\Presenters\HrApprovalChainPresenter;
use App\Services\Hr\WorkflowStatus;

/** Rantai atasan legacy + HR — sama dengan portal koreksi absen / izin / cuti. */
trait LegacyFormAtasanQueueLogic
{
    /** @var array<string, list<int>> */
    private $legacyFormApproverIdsCache = [];

    public function resolveWaitingAtasanName(object $row, string $hrRequestType, int $legacyRowId): string
    {
        foreach ($this->resolveAtasanSteps($row, $hrRequestType, $legacyRowId) as $step) {
            if (($step['state'] ?? '') !== 'active') {
                continue;
            }
            $name = trim((string) ($step['actor'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return '-';
    }

    /**
     * @return list<array{key: string, title: string, state: string, actor: ?string, acted_at: ?string, note: ?string}>
     */
    public function resolveAtasanSteps(object $row, string $hrRequestType, int $legacyRowId): array
    {
        $legacySteps = $this->legacyAtasanStepsForGenericRow($row, $hrRequestType);

        $hrRequest = HrRequestResolver::findMappedFromLegacyRow($hrRequestType, $legacyRowId);
        if (!$hrRequest) {
            return $legacySteps;
        }

        $fromHr = HrApprovalChainPresenter::atasanStepsForPortal($hrRequest);
        if ($fromHr === []) {
            return $legacySteps;
        }

        $status = (string) ($row->status ?? '');
        if ($status === WorkflowStatus::PENDING && !$this->hasActiveAtasanWithActor($fromHr)) {
            return $legacySteps;
        }

        // Koreksi absen lama dapat di-approve tanpa memperbarui step HR.
        // Gabungkan hanya bukti pelaku yang tersimpan, bukan status keseluruhan.
        if ($hrRequestType === HrRequest::TYPE_ATTENDANCE_CORRECTION) {
            $approvedBy = trim((string) ($row->approved_atasan_by ?? ''));
            foreach ($fromHr as &$step) {
                if ($approvedBy !== '' && strcasecmp(trim((string) ($step['actor'] ?? '')), $approvedBy) === 0
                    && in_array($step['state'] ?? '', ['active', 'waiting'], true)) {
                    $step['state'] = 'done';
                    $step['acted_at'] = $row->approved_atasan_at ?? null;
                }
            }
            unset($step);

            $hasRejected = collect($fromHr)->contains('state', 'rejected');
            $foundActive = false;
            foreach ($fromHr as &$step) {
                if (in_array($step['state'] ?? '', ['active', 'waiting'], true)) {
                    $step['state'] = !$hasRejected && !$foundActive ? 'active' : 'waiting';
                    $foundActive = true;
                }
            }
            unset($step);
        }

        return $fromHr;
    }

    public function legacyRowWaitingOnApprover(object $row, string $hrRequestType, int $legacyRowId, int $approverId, string $approverName): bool
    {
        $waitingName = $this->resolveWaitingAtasanName($row, $hrRequestType, $legacyRowId);
        if ($waitingName === '' || $waitingName === '-') {
            return false;
        }

        if ($approverName !== '' && strcasecmp($waitingName, $approverName) === 0) {
            return true;
        }

        $resolvedId = MasterKaryawan::query()
            ->whereRaw('LOWER(TRIM(nama_lengkap)) = ?', [mb_strtolower(trim($waitingName))])
            ->value('id');

        return $resolvedId !== null && (int) $resolvedId === $approverId;
    }

    /**
     * @return list<int> employee_id pengaju (filter portal menunggu atasan).
     */
    public function resolveEmployeeIdsWaitingOnAtasanNamed(string $keyword, iterable $rows, string $hrRequestType): array
    {
        $needle = mb_strtolower(trim($keyword));
        if ($needle === '') {
            return [0];
        }

        $employeeIds = [];
        foreach ($rows as $row) {
            $legacyId = (int) ($row->id ?? 0);
            if ($legacyId <= 0) {
                continue;
            }
            $name = mb_strtolower($this->resolveWaitingAtasanName($row, $hrRequestType, $legacyId));
            if ($name !== '' && $name !== '-' && mb_strpos($name, $needle) !== false) {
                $employeeIds[] = (int) $row->employee_id;
            }
        }

        return $employeeIds !== [] ? array_values(array_unique($employeeIds)) : [0];
    }

    protected function legacySubmitterEmployeeId(object $row): ?int
    {
        if (!isset($row->employee_id) || (int) $row->employee_id <= 0) {
            return null;
        }

        return (int) $row->employee_id;
    }

    protected function legacySubmitterEmployee(object $row): ?MasterKaryawan
    {
        $id = $this->legacySubmitterEmployeeId($row);

        return $id ? MasterKaryawan::query()->find($id) : null;
    }

    /**
     * @param list<array<string, mixed>> $steps
     */
    private function hasActiveAtasanWithActor(array $steps): bool
    {
        foreach ($steps as $step) {
            if (($step['state'] ?? '') === 'active') {
                return trim((string) ($step['actor'] ?? '')) !== '';
            }
        }

        return false;
    }

    /**
     * @return list<array{key: string, title: string, state: string, actor: ?string, acted_at: ?string, note: ?string}>
     */
    private function legacyAtasanStepsForGenericRow(object $row, string $hrRequestType): array
    {
        $employee = $this->legacySubmitterEmployee($row);
        $ids = $employee ? $this->legacyFormApproverIdsFor($employee, $hrRequestType) : [];
        if ($ids === []) {
            return [$this->singleAtasanStepForRow($row)];
        }

        $names = MasterKaryawan::query()->whereIn('id', $ids)->pluck('nama_lengkap', 'id');
        $status = (string) ($row->status ?? '');
        $rejected = $status === WorkflowStatus::REJECTED_ATASAN || !empty($row->rejected_atasan_by);
        $done = !$rejected && (
            !empty($row->approved_atasan_by)
            || in_array($status, [
                WorkflowStatus::APPROVED_ATASAN,
                WorkflowStatus::APPROVED_HRD,
                WorkflowStatus::REJECTED_HRD,
            ], true)
        );

        $rejectIndex = 0;
        if ($rejected) {
            $rejectName = trim((string) ($row->rejected_atasan_by ?? ''));
            foreach ($ids as $index => $id) {
                $name = trim((string) ($names[$id] ?? ''));
                if ($rejectName !== '' && strcasecmp($name, $rejectName) === 0) {
                    $rejectIndex = $index;
                    break;
                }
            }
        }

        $steps = [];
        $total = count($ids);
        $approvedBy = trim((string) ($row->approved_atasan_by ?? ''));
        $approvedAt = $row->approved_atasan_at ?? null;
        $approvedStepIndex = $this->legacyAtasanStepIndexMatchingName($ids, $names, $approvedBy);

        foreach ($ids as $index => $id) {
            $name = $names[$id] ?? null;
            $state = 'waiting';
            $actor = $name;
            $actedAt = null;
            $note = null;

            if ($done) {
                if ($hrRequestType === HrRequest::TYPE_ATTENDANCE_CORRECTION) {
                    // Satu field legacy hanya membuktikan approval pelaku ini.
                    $isApprovedActor = $approvedBy !== ''
                        && strcasecmp(trim((string) $name), $approvedBy) === 0;
                    $state = $isApprovedActor ? 'done' : 'waiting';
                    if ($isApprovedActor) {
                        $actor = $approvedBy;
                        $actedAt = $approvedAt;
                    }
                } else {
                    $state = 'done';
                    if ($approvedBy !== '' && $index === $approvedStepIndex) {
                        $actor = $approvedBy;
                        $actedAt = $approvedAt;
                    }
                }
            } elseif ($rejected) {
                if ($index < $rejectIndex) {
                    $state = $hrRequestType === HrRequest::TYPE_ATTENDANCE_CORRECTION ? 'waiting' : 'done';
                } elseif ($index === $rejectIndex) {
                    $state = 'rejected';
                    $actor = $row->rejected_atasan_by ?: $name;
                    $actedAt = $row->rejected_atasan_at ?? null;
                    $note = $row->reject_atasan_reason ?? null;
                }
            } elseif ($index === 0) {
                $state = 'active';
            }

            $steps[] = [
                'key' => 'atasan-' . ($index + 1),
                'title' => $total > 1 ? 'Persetujuan atasan ' . ($index + 1) : 'Persetujuan Atasan',
                'state' => $state,
                'actor' => $actor,
                'acted_at' => $actedAt ? (string) $actedAt : null,
                'note' => $note,
            ];
        }

        if ($hrRequestType === HrRequest::TYPE_ATTENDANCE_CORRECTION && $status === WorkflowStatus::PENDING && !$rejected) {
            $steps = $this->activateNextLegacyAtasanStep($steps);
        }

        return $steps;
    }

    /** Legacy hanya menyimpan approver terakhir; giliran berikutnya ada setelah step tersebut. */
    private function activateNextLegacyAtasanStep(array $steps): array
    {
        $lastDoneIndex = -1;
        foreach ($steps as $index => $step) {
            if (($step['state'] ?? '') === 'done') {
                $lastDoneIndex = $index;
            }
        }

        $foundActive = false;
        foreach ($steps as $index => &$step) {
            if (in_array($step['state'] ?? '', ['active', 'waiting'], true)) {
                $isNext = !$foundActive && $index > $lastDoneIndex;
                $step['state'] = $isNext ? 'active' : 'waiting';
                if ($isNext) {
                    $foundActive = true;
                }
            }
        }
        unset($step);

        return $steps;
    }

    /**
     * @param array<int, int> $ids
     * @param \Illuminate\Support\Collection<int, string>|array<int, string> $names
     */
    private function legacyAtasanStepIndexMatchingName(array $ids, $names, string $approverName): int
    {
        if ($approverName === '') {
            return max(0, count($ids) - 1);
        }

        foreach ($ids as $index => $id) {
            $name = trim((string) ($names[$id] ?? ''));
            if ($name !== '' && strcasecmp($name, $approverName) === 0) {
                return (int) $index;
            }
        }

        return max(0, count($ids) - 1);
    }

    /** @return list<int> */
    private function legacyFormApproverIdsFor(MasterKaryawan $employee, string $hrRequestType): array
    {
        $cacheKey = (int) $employee->id . '|' . $hrRequestType;
        if (!array_key_exists($cacheKey, $this->legacyFormApproverIdsCache)) {
            $this->legacyFormApproverIdsCache[$cacheKey] = app(FormAtasanApprovalChainService::class)
                ->orderedApproverIds($employee, $hrRequestType);
        }

        return $this->legacyFormApproverIdsCache[$cacheKey];
    }

    /**
     * @return array{key: string, title: string, state: string, actor: ?string, acted_at: ?string, note: ?string}
     */
    private function singleAtasanStepForRow(object $row): array
    {
        $status = (string) ($row->status ?? '');
        $state = 'waiting';
        $actor = null;
        $actedAt = null;
        $note = null;

        if ($status === WorkflowStatus::REJECTED_ATASAN || !empty($row->rejected_atasan_by)) {
            $state = 'rejected';
            $actor = $row->rejected_atasan_by ?? null;
            $actedAt = $row->rejected_atasan_at ?? null;
            $note = $row->reject_atasan_reason ?? null;
        } elseif (
            !empty($row->approved_atasan_by)
            || in_array($status, [
                WorkflowStatus::APPROVED_ATASAN,
                WorkflowStatus::APPROVED_HRD,
                WorkflowStatus::REJECTED_HRD,
            ], true)
        ) {
            $state = 'done';
            $actor = $row->approved_atasan_by ?? null;
            $actedAt = $row->approved_atasan_at ?? null;
        } elseif ($status === WorkflowStatus::PENDING || $status === '') {
            $state = 'active';
        }

        return [
            'key' => 'atasan',
            'title' => 'Persetujuan Atasan',
            'state' => $state,
            'actor' => $actor,
            'acted_at' => $actedAt ? (string) $actedAt : null,
            'note' => $note,
        ];
    }
}
