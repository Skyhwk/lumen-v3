<?php

namespace App\Services;

use App\Models\MasterKaryawan;
use App\Models\SalaryAdjustmentRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ContractExpiryReminderService
{
    public const WINDOW_DAYS = 40;
    public const ALLOWED_GRADES = ['MANAGER', 'SENIOR MANAGER'];
    public const ALLOWED_EMPLOYEE_STATUSES = ['CONTRACT', 'TRAINING'];
    public const CLOSED_ADJUSTMENT_STATUSES = [
        SalaryAdjustmentWorkflowService::STATUS_COMPLETED,
        SalaryAdjustmentWorkflowService::STATUS_REJECTED,
        SalaryAdjustmentWorkflowService::STATUS_CANCELLED,
    ];

    /** Hanya pengajuan yang nyentuh masa pelatihan/kontrak yang boleh nutup reminder. */
    public const BLOCKING_REQUEST_TYPES = [
        EmployeeAdjustmentTypeRegistry::TYPE_PERPANJANG_PELATIHAN,
        EmployeeAdjustmentTypeRegistry::TYPE_PENGANGKATAN_KONTRAK,
        EmployeeAdjustmentTypeRegistry::TYPE_PERPANJANG_KONTRAK,
        EmployeeAdjustmentTypeRegistry::TYPE_PENGANGKATAN_TETAP,
        EmployeeAdjustmentTypeRegistry::TYPE_GAGAL_PELATIHAN,
        EmployeeAdjustmentTypeRegistry::TYPE_PHK,
        EmployeeAdjustmentTypeRegistry::TYPE_PENSIUN,
    ];

    public function isAllowedGrade(?string $grade): bool
    {
        return in_array($this->normalizeGrade($grade), self::ALLOWED_GRADES, true);
    }

    public function forManager(int $managerId, ?string $grade): array
    {
        if ($managerId <= 0 || !$this->isAllowedGrade($grade)) {
            return [];
        }

        $today = Carbon::now('Asia/Jakarta')->startOfDay();
        $limit = $today->copy()->addDays(self::WINDOW_DAYS);
        $employees = $this->loadActiveEmployees();
        $descendantIds = $this->resolveDescendantIds($employees, $managerId);

        if (empty($descendantIds)) {
            return [];
        }

        $idSet = array_flip($descendantIds);
        $blockedIds = $this->openAdjustmentEmployeeIds($descendantIds);

        return $employees
            ->filter(function ($employee) use ($idSet, $limit, $blockedIds) {
                $employeeId = (int) $employee->id;
                if (!isset($idSet[$employeeId]) || isset($blockedIds[$employeeId])) {
                    return false;
                }

                if (!$this->isAllowedEmployeeStatus($employee->status_karyawan)) {
                    return false;
                }

                $end = $this->parseContractDate($employee->tgl_berakhir_kontrak);

                return $end !== null && $end->lte($limit);
            })
            ->map(function ($employee) use ($today) {
                return $this->transform($employee, $today);
            })
            ->sortBy('days_left')
            ->values()
            ->all();
    }

    private function loadActiveEmployees(): Collection
    {
        return MasterKaryawan::query()
            ->where('is_active', true)
            ->get([
                'id',
                'nama_lengkap',
                'nik_karyawan',
                'grade',
                'jabatan',
                'department',
                'status_karyawan',
                'tgl_mulai_kerja',
                'tgl_berakhir_kontrak',
                'image',
                'atasan_langsung',
            ]);
    }

    private function resolveDescendantIds(Collection $employees, int $rootId): array
    {
        $children = [];

        foreach ($employees as $employee) {
            $childId = (int) $employee->id;
            if ($childId === $rootId) {
                continue;
            }

            foreach ($this->parseAtasanIds($employee->atasan_langsung) as $atasanId) {
                if ($atasanId <= 0) {
                    continue;
                }
                $children[$atasanId][] = $childId;
            }
        }

        $descendantIds = [];
        $queue = [$rootId];
        $visited = [$rootId => true];

        while (!empty($queue)) {
            $current = array_shift($queue);
            foreach ($children[$current] ?? [] as $childId) {
                if (isset($visited[$childId])) {
                    continue;
                }
                $visited[$childId] = true;
                $descendantIds[] = $childId;
                $queue[] = $childId;
            }
        }

        return $descendantIds;
    }

    private function transform($employee, Carbon $today): array
    {
        $end = $this->parseContractDate($employee->tgl_berakhir_kontrak);
        $start = $this->parseContractDate($employee->tgl_mulai_kerja);
        $daysLeft = $end ? (int) round(($end->getTimestamp() - $today->getTimestamp()) / 86400) : 0;

        return [
            'id' => (int) $employee->id,
            'nama_lengkap' => $employee->nama_lengkap,
            'nik_karyawan' => $employee->nik_karyawan,
            'grade' => $employee->grade,
            'jabatan' => $employee->jabatan,
            'department' => $employee->department,
            'status_karyawan' => $employee->status_karyawan,
            'tgl_mulai_kerja' => $start ? $start->toDateString() : null,
            'tgl_berakhir_kontrak' => $end ? $end->toDateString() : null,
            'image' => $employee->image,
            'days_left' => $daysLeft,
            'urgency' => $this->urgency($daysLeft),
            'status_label' => $this->statusLabel($daysLeft),
        ];
    }

    private function urgency(int $daysLeft): string
    {
        if ($daysLeft < 0) {
            return 'expired';
        }
        if ($daysLeft === 0) {
            return 'today';
        }
        if ($daysLeft <= 7) {
            return 'critical';
        }

        return 'warning';
    }

    private function statusLabel(int $daysLeft): string
    {
        if ($daysLeft < 0) {
            $days = abs($daysLeft);
            return 'Ended ' . $days . ($days === 1 ? ' day ago' : ' days ago');
        }
        if ($daysLeft === 0) {
            return 'Ends today';
        }

        return $daysLeft === 1 ? '1 day left' : $daysLeft . ' days left';
    }

    private function parseContractDate($value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
            return null;
        }

        try {
            return Carbon::parse($raw, 'Asia/Jakarta')->startOfDay();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseAtasanIds($atasanLangsung): array
    {
        $decoded = is_array($atasanLangsung)
            ? $atasanLangsung
            : (json_decode($atasanLangsung ?? '[]', true) ?? []);

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $decoded)));
    }

    private function openAdjustmentEmployeeIds(array $employeeIds): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        $closed = array_map('strtolower', self::CLOSED_ADJUSTMENT_STATUSES);

        $ids = SalaryAdjustmentRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('request_type', self::BLOCKING_REQUEST_TYPES)
            ->whereRaw('LOWER(status) NOT IN (' . implode(',', array_fill(0, count($closed), '?')) . ')', $closed)
            ->pluck('employee_id')
            ->all();

        $blocked = [];
        foreach ($ids as $id) {
            $employeeId = (int) $id;
            if ($employeeId > 0) {
                $blocked[$employeeId] = true;
            }
        }

        return $blocked;
    }

    private function isAllowedEmployeeStatus($status): bool
    {
        return in_array(strtoupper(trim((string) $status)), self::ALLOWED_EMPLOYEE_STATUSES, true);
    }

    private function normalizeGrade(?string $grade): string
    {
        return strtoupper(trim((string) $grade));
    }
}
