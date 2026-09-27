<?php

namespace App\Http\Controllers\Greatday;

use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Greatday\GetAtasan;
use App\Services\Greatday\FirebaseService;
use App\Services\Hr\Greatday\LeaveRequestHrService;
use App\Support\Greatday\HrdPayroll;
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
                $item->attachment = !$item->attachment ?: url('leave-requests/' . $item->attachment);

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
            return app(LeaveRequestHrService::class)->specialLeaveTypes();
        }

        $specialLeaveTypes = SpecialLeaveType::where('is_active', true)->latest()->get();

        return response()->json([
            'data' => $specialLeaveTypes,
            'message' => 'Special leave types retrieved successfully',
        ], 200);
    }

    public function store(Request $request)
    {
        if ($this->usesHrTables()) {
            return app(LeaveRequestHrService::class)->store($request, $this->karyawan, $this->nama_lengkap);
        }

        $employee = $this->karyawan;

        $leaveRequest = $request->id ? LeaveRequest::find($request->id) : new LeaveRequest();

        $leaveRequest->employee_id = $this->user_id;
        $leaveRequest->no_document = str_replace('.', '/', microtime(true));
        $leaveRequest->type = $request->type;
        if ($request->type === 'Special Leave') {
            $leaveRequest->special_leave_id = $request->special_leave_id;
        }
        $leaveRequest->start_date = $request->start_date;
        $leaveRequest->end_date = $request->end_date;
        $leaveRequest->description = $request->description;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $destinationPath = public_path('leave-requests');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            $fileName = str_replace('.', '', microtime(true)) . '.' . $file->getClientOriginalExtension();
            $file->move($destinationPath, $fileName);

            $leaveRequest->attachment = $fileName;
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
                    $service->sendNotifications([$atasan->id], [
                        'title' => 'Permohonan Cuti Diajukan!',
                        'body'  => 'Terdapat Permohonan Cuti yang diajukan oleh: ' . $this->nama_lengkap . ' menunggu persetujuan Anda',
                        'url'   => '/forms/leaveRequests',
                    ]);
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
            if ($deny = $this->assertApproverIsAtasanOf((int) $resolved->karyawan_id)) {
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
        $service->sendNotifications(HrdPayroll::queueNotificationUserIds(), [
            'title' => 'Permohonan Cuti Diajukan!',
            'body'  => 'Terdapat Permohonan Cuti yang diajukan oleh: ' . $leaveRequest->created_by . ' menunggu persetujuan Anda',
            'url'   => '/forms/leaveRequests',
        ]);

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
            if ($deny = $this->assertApproverIsAtasanOf((int) $resolved->karyawan_id)) {
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
        $service->sendNotifications([$leaveRequest->employee_id], [
            'title' => 'Permohonan Cuti Ditolak!',
            'body'  => 'Permohonan Cuti telah ditolak Atasan oleh: ' . $this->nama_lengkap . ' dengan alasan: ' . $request->reject_reason,
            'url'   => '/forms/leaveRequests',
        ]);

        return response()->json(['message' => 'The leave request has been rejected successfully'], 200);
    }
}
