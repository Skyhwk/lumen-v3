<?php

namespace App\Services\Greatday;

use App\Http\Controllers\Greatday\AttendanceCorrectionsController;
use App\Http\Controllers\Greatday\ConsultationRequestsController;
use App\Http\Controllers\Greatday\LeaveRequestsController;
use App\Http\Controllers\Greatday\OvertimeReimbursementsController;
use App\Http\Controllers\Greatday\OvertimeRequestsController;
use App\Http\Controllers\Greatday\PermissionRequestsController;
use App\Models\MasterKaryawan;
use App\Services\Hr\HrPendingApprovalsService;
use App\Services\Hr\HrTableMode;
use App\Services\Hr\WorkflowStatus;
use App\Support\Greatday\HrdPayroll;
use Illuminate\Http\Request;

/**
 * Satu sumber antrian persetujuan — selaras Intilab Api\HomePageController::pendingApprovals.
 */
class FormsPendingApprovalsService
{
    /** @var array<int, array{class: class-string, name: string, title: string}> */
    private const LEGACY_CONTROLLERS = [
        ['class' => OvertimeRequestsController::class, 'name' => 'OvertimeRequestsController', 'title' => 'Overtime Request'],
        ['class' => LeaveRequestsController::class, 'name' => 'LeaveRequestsController', 'title' => 'Leave Request'],
        ['class' => PermissionRequestsController::class, 'name' => 'PermissionRequestsController', 'title' => 'Permission Request'],
        ['class' => AttendanceCorrectionsController::class, 'name' => 'AttendanceCorrectionsController', 'title' => 'Attendance Correction'],
        ['class' => ConsultationRequestsController::class, 'name' => 'ConsultationRequestsController', 'title' => 'Consultation Request'],
        ['class' => OvertimeReimbursementsController::class, 'name' => 'OvertimeReimbursementsController', 'title' => 'Overtime Reimbursement'],
    ];

    public function pendingItems(MasterKaryawan $employee): array
    {
        return $this->buildPendingItems($employee, false);
    }

    /** Hitung badge tanpa memanggil index() legacy untuk form yang sudah di hr_requests. */
    public function pendingCount(MasterKaryawan $employee): int
    {
        return count($this->buildPendingItems($employee, true));
    }

    private function buildPendingItems(MasterKaryawan $employee, bool $lightweightForStats): array
    {
        if (!$this->viewerCanAccessApprovalQueue($employee)) {
            return [];
        }

        $legacyOnlyControllers = [
            'OvertimeReimbursementsController',
            'ConsultationRequestsController',
        ];

        if (!HrTableMode::usesLegacyHrTables()) {
            if (!in_array($employee->grade, ['MANAGER', 'SUPERVISOR', 'SENIOR MANAGER'], true)) {
                $legacyItems = $this->collectLegacyPendingApprovals($employee);

                return $this->finalizeItems($legacyItems);
            }

            $legacyItems = $lightweightForStats
                ? $this->collectLegacyPendingApprovals($employee, $legacyOnlyControllers)
                : $this->collectLegacyPendingApprovals($employee);

            $hrItems = app(HrPendingApprovalsService::class)->listForAtasan($employee);

            return $this->finalizeItems($this->mergeHrAndLegacyExtras($hrItems, $legacyItems));
        }

        $legacyItems = $this->collectLegacyPendingApprovals($employee);

        return $this->finalizeItems($legacyItems);
    }

    /** @deprecated Use pendingItems() */
    public function legacyManagerPendingItems(MasterKaryawan $employee): array
    {
        return $this->pendingItems($employee);
    }

    private function viewerCanAccessApprovalQueue(MasterKaryawan $employee): bool
    {
        if (in_array($employee->grade, ['MANAGER', 'SUPERVISOR', 'SENIOR MANAGER'], true)) {
            return true;
        }

        if (HrdPayroll::canAccessHrdQueue($employee)) {
            return true;
        }

        if ($employee->jabatan === 'Accounting & Expense Manager') {
            return true;
        }

        if ($employee->jabatan === 'HR Counselling & Development Supervisor') {
            return true;
        }

        return false;
    }

    /**
     * @param array<int, string>|null $onlyControllerNames Batasi controller (untuk stats ringan)
     */
    private function collectLegacyPendingApprovals(MasterKaryawan $employee, ?array $onlyControllerNames = null): array
    {
        $this->bindKaryawanOnRequest($employee);

        $isAtasanGrade = in_array($employee->grade, ['MANAGER', 'SUPERVISOR', 'SENIOR MANAGER'], true);
        $isPayroll = HrdPayroll::canAccessHrdQueue($employee);
        $isAccounting = $employee->jabatan === 'Accounting & Expense Manager';
        $isHRCounselling = $employee->jabatan === 'HR Counselling & Development Supervisor';

        $allPending = [];

        foreach (self::LEGACY_CONTROLLERS as $c) {
            if ($onlyControllerNames !== null && !in_array($c['name'], $onlyControllerNames, true)) {
                continue;
            }

            $controllerInstance = app($c['class']);
            $response = $controllerInstance->index();
            $payload = $response->getData();
            $data = is_object($payload) && isset($payload->data) ? $payload->data : [];

            foreach ($data as $item) {
                $status = $item->status ?? null;
                if (!$this->shouldIncludeLegacyPendingItem($c['name'], $status, $isAtasanGrade, $isPayroll, $isAccounting, $isHRCounselling)) {
                    continue;
                }

                $allPending[] = [
                    'id' => $item->id,
                    'controller' => $c['name'],
                    'title' => $c['title'],
                    'name' => $item->employee_name ?? $item->department_name ?? 'Unknown',
                    'position' => $item->employee_position ?? $item->no_document ?? '',
                    'description' => $item->description ?? $item->type ?? '',
                    'status' => $status,
                    'raw' => $item,
                    'highlights' => PendingApprovalSummary::forController($c['name'], $item),
                    'can_approve' => true,
                ];
            }
        }

        return $allPending;
    }

    private function shouldIncludeLegacyPendingItem(
        string $controllerName,
        ?string $status,
        bool $isAtasanGrade,
        bool $isPayroll,
        bool $isAccounting,
        bool $isHRCounselling
    ): bool {
        switch ($controllerName) {
            case 'OvertimeRequestsController':
            case 'OvertimeReimbursementsController':
                return ($isAtasanGrade && $status === WorkflowStatus::PENDING)
                    || ($isPayroll && $status === WorkflowStatus::APPROVED_ATASAN)
                    || ($isAccounting && $status === WorkflowStatus::APPROVED_HRD);
            case 'LeaveRequestsController':
            case 'PermissionRequestsController':
            case 'AttendanceCorrectionsController':
                return ($isAtasanGrade && $status === WorkflowStatus::PENDING)
                    || ($isPayroll && $status === WorkflowStatus::APPROVED_ATASAN);
            case 'ConsultationRequestsController':
                return $isHRCounselling && $status === WorkflowStatus::PENDING;
            default:
                return false;
        }
    }

    /**
     * hr_requests untuk cuti/izin/lembur/koreksi; reimbursement & konsultasi tetap di tabel legacy apps.
     *
     * @param array<int, array<string, mixed>> $hrItems
     * @param array<int, array<string, mixed>> $legacyItems
     * @return array<int, array<string, mixed>>
     */
    private function mergeHrAndLegacyExtras(array $hrItems, array $legacyItems): array
    {
        $merged = $hrItems;
        $keys = [];
        foreach ($hrItems as $item) {
            $keys[$this->pendingItemKey($item)] = true;
        }

        foreach ($legacyItems as $item) {
            $key = $this->pendingItemKey($item);
            if (isset($keys[$key])) {
                continue;
            }
            $keys[$key] = true;
            $merged[] = $item;
        }

        return $merged;
    }

    /** @param array<string, mixed> $item */
    private function pendingItemKey(array $item): string
    {
        $controller = $item['controller'] ?? '';
        $raw = $item['raw'] ?? null;
        $noDocument = is_object($raw) ? ($raw->no_document ?? null) : null;
        if (is_string($noDocument) && $noDocument !== '') {
            return $controller . '#doc:' . $noDocument;
        }

        return $controller . '#id:' . ($item['id'] ?? '');
    }

    /** @param array<int, array<string, mixed>> $items */
    private function finalizeItems(array $items): array
    {
        usort($items, fn ($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));

        return array_values($items);
    }

    private function bindKaryawanOnRequest(MasterKaryawan $employee): void
    {
        $request = app(Request::class);
        $request->attributes->set('greatday_karyawan', $employee);
    }
}
