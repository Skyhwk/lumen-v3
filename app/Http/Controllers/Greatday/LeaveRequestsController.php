<?php

namespace App\Http\Controllers\Greatday;

use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Greatday\GetAtasan;
use App\Services\Greatday\FirebaseService;
use App\Services\Greatday\LeaveRequestValidationService;
use App\Models\Hr\HrRequest;
use App\Services\Hr\Greatday\FormSubmitterVoidService;
use App\Services\Hr\Greatday\LeaveRequestHrService;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\HrFormAttachmentStorage;
use App\Support\Greatday\GreatdayAssetPaths;
use App\Support\Greatday\HrdPayroll;
use App\Support\Greatday\NotificationCopy;
use App\Models\Greatday\{LeaveRequest, SpecialLeaveType};
use App\Models\{MasterKaryawan};
use Illuminate\Http\Request;

class LeaveRequestsController extends Controller
{
    public function index()
    {
        if ($this->usesHrTables()) {
            return app(LeaveRequestHrService::class)->index($this->karyawan);
        }

        $employee = $this->karyawan;

        $leaveRequests = LeaveRequest::with('specialLeaveType')->where('is_active', true);

        $leaveRequests->where(function ($query) use ($employee) {
            $query->where('employee_id', $employee->id);

            if (AtasanApprovalScope::isAtasanGrade($employee)) {
                $subordinateIds = AtasanApprovalScope::subordinateKaryawanIds($employee);

                if (!empty($subordinateIds)) {
                    $query->orWhere(function ($q) use ($subordinateIds) {
                        $q->where('status', 'Pending')
                            ->whereIn('employee_id', $subordinateIds);
                    });
                }
            }
        });

        $leaveRequests = $leaveRequests
            ->latest()
            ->get()
            ->map(function ($item) {
                $employee = MasterKaryawan::find($item->employee_id);
                $item->employee_name = $employee->nama_lengkap;
                $item->employee_position = $employee->jabatan;
                $attachments = HrFormAttachmentStorage::resolvePublicUrls($item->attachment, GreatdayAssetPaths::KEY_CUTI);
                $item->attachments = $attachments;
                $item->attachment = $attachments[0] ?? null;

                return $item;
            });

        return response()->json([
            'data' => $leaveRequests,
            'message' => 'Leave requests retrieved successfully',
        ], 200);
    }

    public function getSpecialLeaveTypes()
    {
        if ($this->usesHrTables()) {
            return app(LeaveRequestHrService::class)->specialLeaveTypes($this->karyawan);
        }

        $specialLeaveTypes = SpecialLeaveType::where('is_active', true)->latest()->get();

        return response()->json([
            'data' => $specialLeaveTypes,
            'message' => 'Special leave types retrieved successfully',
        ], 200);
    }

    public function validateSubmission(Request $request)
    {
        $employee = $this->karyawan;
        if (!$employee) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $message = app(LeaveRequestValidationService::class)->validateForStore(
            $employee,
            (string) $request->input('type', ''),
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('id') ? (int) $request->input('id') : null,
            $request->input('id') ? (int) $request->input('id') : null,
            $request->input('special_leave_id') ? (int) $request->input('special_leave_id') : null,
            HrFormAttachmentStorage::hasUploadedImages($request)
        );

        if ($message !== null) {
            return response()->json(['message' => $message, 'valid' => false], 422);
        }

        return response()->json(['message' => 'OK', 'valid' => true], 200);
    }

    public function store(Request $request)
    {
        if ($this->usesHrTables()) {
            return app(LeaveRequestHrService::class)->store($request, $this->karyawan, $this->nama_lengkap);
        }

        $employee = $this->karyawan;

        $validationMessage = app(LeaveRequestValidationService::class)->validateForStore(
            $employee,
            (string) $request->type,
            $request->start_date,
            $request->end_date,
            null,
            $request->id ? (int) $request->id : null,
            $request->special_leave_id ? (int) $request->special_leave_id : null,
            HrFormAttachmentStorage::hasUploadedImages($request)
        );
        if ($validationMessage !== null) {
            return response()->json(['message' => $validationMessage], 422);
        }

        $leaveRequest = $request->id ? LeaveRequest::find($request->id) : new LeaveRequest();

        $leaveRequest->employee_id = $this->user_id;
        $leaveRequest->no_document = str_replace('.', '/', microtime(true));
        $leaveRequest->type = $request->type;
        if ($request->type === 'Special Leave') {
            $leaveRequest->special_leave_id = $request->special_leave_id;
        } else {
            $leaveRequest->special_leave_id = null;
        }
        $leaveRequest->start_date = $request->start_date;
        $leaveRequest->end_date = $request->end_date;
        $leaveRequest->description = $request->description;

        $storedAttachments = HrFormAttachmentStorage::storeImages(
            HrFormAttachmentStorage::collectUploadedImages($request),
            GreatdayAssetPaths::KEY_CUTI
        );
        if ($storedAttachments !== null) {
            $leaveRequest->attachment = $storedAttachments;
        }

        if (!$request->id) {
            $leaveRequest->created_by = $this->nama_lengkap;
            $leaveRequest->created_at = date('Y-m-d H:i:s');
        }

        $leaveRequest->updated_by = $this->nama_lengkap;
        $leaveRequest->updated_at = date('Y-m-d H:i:s');

        if ($employee->grade === 'MANAGER') {
            $leaveRequest->status = 'Approved Atasan';
            $leaveRequest->approved_atasan_by = $this->nama_lengkap;
            $leaveRequest->approved_atasan_at = date('Y-m-d H:i:s');
        }

        $leaveRequest->save();

        $service = new FirebaseService();

        if ($employee->grade !== 'MANAGER') {
            $getAtasan = GetAtasan::where('id', $employee->id)->get();

            foreach ($getAtasan as $atasan) {
                if ($atasan->grade === 'MANAGER') {
                    $service->sendNotifications(
                        [$atasan->id],
                        NotificationCopy::legacyAtasanPending('Permohonan cuti', $this->nama_lengkap, NotificationCopy::pathForms('approval'))
                    );
                }
            }
        }

        return response()->json(['message' => 'Your leave request has been submitted successfully'], 201);
    }

    public function approve(Request $request)
    {
        if ($this->usesHrTables()) {
            $resolved = \App\Services\Hr\HrRequestResolver::findByApiId(\App\Models\Hr\HrRequest::TYPE_LEAVE, (int) $request->id);
            if (!$resolved) {
                return response()->json(['message' => 'Leave request not found'], 404);
            }
            if ($deny = $this->assertPendingForAtasan($resolved->status)) {
                return $deny;
            }
            if ($deny = $this->assertHrChainApprover($resolved)) {
                return $deny;
            }

            return app(LeaveRequestHrService::class)->approve((int) $request->id, $this->karyawan);
        }

        $leaveRequest = LeaveRequest::find($request->id);
        if (!$leaveRequest) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        if ($deny = $this->assertPendingForAtasan($leaveRequest->status)) {
            return $deny;
        }
        if ($deny = $this->assertApproverIsAtasanOf((int) $leaveRequest->employee_id)) {
            return $deny;
        }

        $leaveRequest->status = 'Approved Atasan';
        $leaveRequest->approved_atasan_by = $this->nama_lengkap;
        $leaveRequest->approved_atasan_at = date('Y-m-d H:i:s');
        $leaveRequest->save();

        $service = new FirebaseService();
        $service->sendNotifications(
            HrdPayroll::queueNotificationUserIds(),
            NotificationCopy::legacyForwardToHrd('Permohonan cuti', $leaveRequest->created_by, NotificationCopy::pathForms('approval'))
        );

        return response()->json(['message' => 'The leave request has been approved successfully'], 200);
    }

    public function reject(Request $request)
    {
        if ($this->usesHrTables()) {
            $resolved = \App\Services\Hr\HrRequestResolver::findByApiId(\App\Models\Hr\HrRequest::TYPE_LEAVE, (int) $request->id);
            if (!$resolved) {
                return response()->json(['message' => 'Leave request not found'], 404);
            }
            if ($deny = $this->assertPendingForAtasanReject($resolved->status)) {
                return $deny;
            }
            if ($deny = $this->assertHrChainApprover($resolved)) {
                return $deny;
            }

            return app(LeaveRequestHrService::class)->reject((int) $request->id, $this->karyawan, $request->reject_reason);
        }

        $leaveRequest = LeaveRequest::find($request->id);
        if (!$leaveRequest) {
            return response()->json(['message' => 'Leave request not found'], 404);
        }

        if ($deny = $this->assertPendingForAtasanReject($leaveRequest->status)) {
            return $deny;
        }
        if ($deny = $this->assertApproverIsAtasanOf((int) $leaveRequest->employee_id)) {
            return $deny;
        }

        $leaveRequest->status = 'Rejected Atasan';
        $leaveRequest->rejected_atasan_by = $this->nama_lengkap;
        $leaveRequest->rejected_atasan_at = date('Y-m-d H:i:s');
        $leaveRequest->reject_atasan_reason = $request->reject_reason;
        $leaveRequest->save();

        $service = new FirebaseService();
        $service->sendNotifications(
            [$leaveRequest->employee_id],
            NotificationCopy::legacyRejected(
                'Permohonan cuti',
                $this->nama_lengkap,
                $request->reject_reason,
                NotificationCopy::pathForms('submission')
            )
        );

        return response()->json(['message' => 'The leave request has been rejected successfully'], 200);
    }

    public function void(Request $request)
    {
        try {
            if ($this->usesHrTables()) {
                $resolved = HrRequestResolver::findByApiId(HrRequest::TYPE_LEAVE, (int) $request->id);
                if (!$resolved) {
                    return response()->json(['message' => 'Leave request not found'], 404);
                }
                app(FormSubmitterVoidService::class)->voidHrRequest($resolved, $this->karyawan);
            } else {
                app(FormSubmitterVoidService::class)->voidLegacyLeave((int) $request->id, $this->karyawan);
            }

            return response()->json(['message' => 'Pengajuan cuti berhasil dibatalkan.'], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
