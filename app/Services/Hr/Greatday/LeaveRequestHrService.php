<?php

namespace App\Services\Hr\Greatday;

use App\Models\Hr\HrLeaveDetail;
use App\Models\Hr\HrRequest;
use App\Models\Hr\HrSpecialLeaveType;
use App\Models\MasterKaryawan;
use App\Services\Greatday\FirebaseService;
use App\Services\Greatday\GetAtasan;
use App\Services\Greatday\LeaveRequestValidationService;
use App\Services\Hr\Greatday\Concerns\BootstrapsHrAtasanChain;
use App\Services\Hr\ApprovalService;
use App\Services\Hr\AtasanStepService;
use App\Services\Hr\HrApprovalChainService;
use App\Services\Hr\GreatdayIndexScope;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\HrFormAttachmentStorage;
use App\Services\Hr\LegacyHrMirror;
use App\Services\Hr\Presenters\LeaveRequestPresenter;
use App\Services\Hr\WorkflowStatus;
use App\Support\Greatday\GreatdayAssetPaths;
use App\Support\Greatday\HrdPayroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeaveRequestHrService
{
    use BootstrapsHrAtasanChain;

    public function index(MasterKaryawan $employee)
    {
        $rows = HrRequest::with(['leaveDetail'])
            ->where('request_type', HrRequest::TYPE_LEAVE)
            ->where('is_active', true);

        GreatdayIndexScope::apply($rows, $employee);

        $data = $rows->orderByDesc('id')->get()->map(function ($item) {
            return LeaveRequestPresenter::toGreatdayJson($item);
        });

        return response()->json([
            'data' => $data,
            'message' => 'Leave requests retrieved successfully',
        ], 200);
    }

    public function specialLeaveTypes(?MasterKaryawan $employee = null)
    {
        $types = HrSpecialLeaveType::where('is_active', true)->orderBy('name')->get()->map(function ($row) use ($employee) {
            $payload = $row->toArray();
            if ($employee) {
                $stats = app(\App\Services\Greatday\LeaveBalanceService::class)->specialLeaveUsageStats(
                    $employee,
                    (int) $row->id
                );
                $payload['used_submissions'] = $stats['submission_count'];
                $maxUses = $row->max_uses !== null ? (int) $row->max_uses : null;
                $payload['remaining_submissions'] = $maxUses === null
                    ? null
                    : max(0, $maxUses - $stats['submission_count']);
            }

            return $payload;
        });

        return response()->json([
            'data' => $types,
            'message' => 'Special leave types retrieved successfully',
        ], 200);
    }

    public function store(Request $request, MasterKaryawan $employee, string $actorName)
    {
        $validationMessage = app(LeaveRequestValidationService::class)->validateForStore(
            $employee,
            (string) $request->type,
            $request->start_date,
            $request->end_date,
            $request->id ? (int) $request->id : null,
            null,
            $request->special_leave_id ? (int) $request->special_leave_id : null,
            HrFormAttachmentStorage::hasUploadedImages($request)
        );
        if ($validationMessage !== null) {
            return response()->json(['message' => $validationMessage], 422);
        }

        $now = Carbon::now();
        $noDocument = str_replace('.', '/', microtime(true));

        $leaveKind = 'annual';
        if ($request->type === 'Special Leave') {
            $leaveKind = 'special';
        } elseif ($request->type === 'Unpaid Leave') {
            $leaveKind = 'unpaid';
        } elseif ($request->type === 'Holiday Replacement Leave') {
            $leaveKind = 'phl';
        }

        $status = WorkflowStatus::PENDING;

        $attachmentPath = HrFormAttachmentStorage::storeImages(
            HrFormAttachmentStorage::collectUploadedImages($request),
            GreatdayAssetPaths::KEY_CUTI
        );

        $header = HrRequest::create([
            'uuid' => (string) Str::uuid(),
            'request_type' => HrRequest::TYPE_LEAVE,
            'no_document' => $noDocument,
            'karyawan_id' => $employee->id,
            'id_cabang' => $employee->id_cabang ?? null,
            'id_department' => $employee->id_department ?? null,
            'status' => $status,
            'workflow_code' => 'default_2_step',
            'description' => $request->description,
            'submitted_at' => $now,
            'created_by_karyawan_id' => $employee->id,
            'created_by_name' => $actorName,
            'created_at' => $now,
            'updated_by_name' => $actorName,
            'updated_at' => $now,
            'is_active' => true,
        ]);

        HrLeaveDetail::create([
            'request_id' => $header->id,
            'leave_kind' => $leaveKind,
            'special_leave_type_id' => $request->type === 'Special Leave' ? $request->special_leave_id : null,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'attachment_path' => $attachmentPath,
        ]);

        $header = $this->bootstrapAtasanChain($header, $employee, '/forms/leaveRequests');

        app(LegacyHrMirror::class)->mirrorCreateFromHrRequest($header->fresh(['leaveDetail']));

        return response()->json(['message' => 'Your leave request has been submitted successfully'], 201);
    }

    public function approve(int $apiId, MasterKaryawan $approver)
    {
        $leave = HrRequestResolver::findByApiId(HrRequest::TYPE_LEAVE, $apiId);
        if (!$leave) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        if (!app(HrApprovalChainService::class)->viewerCanApprove($leave, $approver)) {
            return response()->json(['message' => 'Bukan giliran Anda menyetujui pengajuan ini'], 403);
        }

        app(ApprovalService::class)->approveAtasan($leave, $approver, ApprovalService::CHANNEL_GREATDAY);
        app(LegacyHrMirror::class)->syncAtasanApproval($leave->fresh());

        $leave = $leave->fresh();
        if ($leave->status === WorkflowStatus::APPROVED_ATASAN) {
            $service = new FirebaseService();
            $service->sendNotifications(HrdPayroll::queueNotificationUserIds(), [
                'title' => 'Permohonan Cuti Diajukan!',
                'body'  => 'Terdapat Permohonan Cuti yang diajukan oleh: ' . $leave->created_by_name . ' menunggu persetujuan Anda',
                'url'   => '/forms/leaveRequests',
            ]);
        }

        return response()->json(['message' => 'The leave request has been approved successfully'], 200);
    }

    public function reject(int $apiId, MasterKaryawan $approver, ?string $reason)
    {
        $leave = HrRequestResolver::findByApiId(HrRequest::TYPE_LEAVE, $apiId);
        if (!$leave) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        if (!app(HrApprovalChainService::class)->viewerCanApprove($leave, $approver)) {
            return response()->json(['message' => 'Bukan giliran Anda menolak pengajuan ini'], 403);
        }

        app(ApprovalService::class)->rejectAtasan($leave, $approver, $reason, ApprovalService::CHANNEL_GREATDAY);
        app(LegacyHrMirror::class)->syncAtasanApproval($leave->fresh());

        $service = new FirebaseService();
        $service->sendNotifications([$leave->karyawan_id], [
            'title' => 'Permohonan Cuti Ditolak!',
            'body'  => 'Permohonan Cuti telah ditolak Atasan oleh: ' . $approver->nama_lengkap . ' dengan alasan: ' . $reason,
            'url'   => '/forms/leaveRequests',
        ]);

        return response()->json(['message' => 'The leave request has been rejected successfully'], 200);
    }

    private function notifyManagersOnSubmit(MasterKaryawan $employee, string $actorName, string $status): void
    {
        if ($employee->grade === 'MANAGER' || $status !== WorkflowStatus::PENDING) {
            return;
        }

        $service = new FirebaseService();
        $getAtasan = GetAtasan::where('id', $employee->id)->get();

        foreach ($getAtasan as $atasan) {
            if ($atasan->grade === 'MANAGER') {
                $service->sendNotifications([$atasan->id], [
                    'title' => 'Permohonan Cuti Diajukan!',
                    'body'  => 'Terdapat Permohonan Cuti yang diajukan oleh: ' . $actorName . ' menunggu persetujuan Anda',
                    'url'   => '/forms/leaveRequests',
                ]);
            }
        }
    }
}
