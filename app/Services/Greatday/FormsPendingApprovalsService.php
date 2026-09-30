<?php

namespace App\Services\Greatday;

use App\Services\Hr\HrTableMode;
use App\Http\Controllers\Greatday\AttendanceCorrectionsController;
use App\Http\Controllers\Greatday\ConsultationRequestsController;
use App\Http\Controllers\Greatday\LeaveRequestsController;
use App\Http\Controllers\Greatday\OvertimeReimbursementsController;
use App\Http\Controllers\Greatday\OvertimeRequestsController;
use App\Http\Controllers\Greatday\PermissionRequestsController;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Hr\HrApprovalChainService;
use App\Services\Hr\HrPendingApprovalsService;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\WorkflowStatus;
use App\Support\Greatday\HrdPayroll;
use Illuminate\Http\Request;

/**
 * Satu sumber antrian persetujuan — selaras Intilab Api\HomePageController::pendingApprovals.
 */
class FormsPendingApprovalsService
{
    /** @var array<string, string> */
    private const CONTROLLER_REQUEST_TYPE = [
        'LeaveRequestsController' => HrRequest::TYPE_LEAVE,
        'PermissionRequestsController' => HrRequest::TYPE_PERMISSION,
        'OvertimeRequestsController' => HrRequest::TYPE_OVERTIME,
        'AttendanceCorrectionsController' => HrRequest::TYPE_ATTENDANCE_CORRECTION,
    ];

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

    /**
     * Badge tab persetujuan harus selaras dengan pendingItems().
     * Mode ringan (hanya reimbursement/konsultasi legacy) hanya aman bila semua antrian
     * cuti/izin/lembur/koreksi sudah lewat hr_approval_step — di praktik masih sering
     * mengandalkan index HR scope (GreatdayIndexScope), sehingga count jadi 0.
     */
    public function pendingCount(MasterKaryawan $employee): int
    {
        $lightweightForStats = HrTableMode::usesLegacyHrTables();

        return count($this->buildPendingItems($employee, $lightweightForStats));
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

                if (!$this->legacyPendingVisibleToViewer($employee, $c['name'], $item, $status)) {
                    continue;
                }

                $canApprove = $this->legacyPendingCanApprove($employee, $c['name'], $item, $status);

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
                    'can_approve' => $canApprove,
                ];
            }
        }

        return $allPending;
    }

    /**
     * Index controller Greatday memuat pengajuan sendiri + antrian bawahan; tab Persetujuan hanya untuk yang memang giliran viewer.
     */
    private function legacyPendingVisibleToViewer(
        MasterKaryawan $viewer,
        string $controllerName,
        object $item,
        ?string $status
    ): bool {
        $submitterId = $this->legacyItemSubmitterKaryawanId($item);
        if ($submitterId !== null && $submitterId === (int) $viewer->id) {
            return false;
        }

        if ($status !== WorkflowStatus::PENDING || !AtasanApprovalScope::isAtasanGrade($viewer)) {
            return true;
        }

        $hrRequest = $this->resolveHrRequestForLegacyController($controllerName, $item);
        if ($hrRequest) {
            return app(HrApprovalChainService::class)->viewerCanApprove($hrRequest, $viewer);
        }

        if ($submitterId === null) {
            return false;
        }

        return AtasanApprovalScope::isSubordinateKaryawan($viewer, $submitterId);
    }

    private function legacyPendingCanApprove(
        MasterKaryawan $viewer,
        string $controllerName,
        object $item,
        ?string $status
    ): bool {
        if ($status === WorkflowStatus::PENDING && AtasanApprovalScope::isAtasanGrade($viewer)) {
            $hrRequest = $this->resolveHrRequestForLegacyController($controllerName, $item);
            if ($hrRequest) {
                return app(HrApprovalChainService::class)->viewerCanApprove($hrRequest, $viewer);
            }

            $submitterId = $this->legacyItemSubmitterKaryawanId($item);

            return $submitterId !== null
                && $submitterId !== (int) $viewer->id
                && AtasanApprovalScope::isSubordinateKaryawan($viewer, $submitterId);
        }

        return true;
    }

    private function legacyItemSubmitterKaryawanId(object $item): ?int
    {
        if (isset($item->employee_id) && (int) $item->employee_id > 0) {
            return (int) $item->employee_id;
        }
        if (isset($item->karyawan_id) && (int) $item->karyawan_id > 0) {
            return (int) $item->karyawan_id;
        }

        return null;
    }

    private function resolveHrRequestForLegacyController(string $controllerName, object $item): ?HrRequest
    {
        $type = self::CONTROLLER_REQUEST_TYPE[$controllerName] ?? null;
        if ($type === null || !isset($item->id)) {
            return null;
        }

        return HrRequestResolver::findByApiId($type, (int) $item->id);
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
