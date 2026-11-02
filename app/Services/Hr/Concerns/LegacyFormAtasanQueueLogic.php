<?php

namespace App\Services\Hr\Concerns;

use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\DynamicApprovalChainService;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\Presenters\HrApprovalChainPresenter;
use App\Services\Hr\WorkflowStatus;

/** Rantai atasan legacy + HR — sama dengan portal koreksi absen / izin / cuti. */
trait LegacyFormAtasanQueueLogic
{
    /** @var array<int, list<int>> */
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
        $legacySteps = $this->legacyAtasanStepsForGenericRow($row);

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
    private function legacyAtasanStepsForGenericRow(object $row): array
    {
        $employee = $this->legacySubmitterEmployee($row);
        $ids = $employee ? $this->legacyFormApproverIdsFor($employee) : [];
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
        $last = $total - 1;

        foreach ($ids as $index => $id) {
            $name = $names[$id] ?? null;
            $state = 'waiting';
            $actor = $name;
            $actedAt = null;
            $note = null;

            if ($done) {
                $state = 'done';
                if ($index === $last && !empty($row->approved_atasan_by)) {
                    $actor = $row->approved_atasan_by;
                    $actedAt = $row->approved_atasan_at ?? null;
                }
            } elseif ($rejected) {
                if ($index < $rejectIndex) {
                    $state = 'done';
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

        return $steps;
    }

    /** @return list<int> */
    private function legacyFormApproverIdsFor(MasterKaryawan $employee): array
    {
        $id = (int) $employee->id;
        if (!array_key_exists($id, $this->legacyFormApproverIdsCache)) {
            $this->legacyFormApproverIdsCache[$id] = app(DynamicApprovalChainService::class)->orderedApproverIds($employee);
        }

        return $this->legacyFormApproverIdsCache[$id];
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
