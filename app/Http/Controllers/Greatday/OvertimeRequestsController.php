<?php

namespace App\Http\Controllers\Greatday;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Hr\HrRequest;
use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Greatday\GreatdayOvertimeAccess;
use App\Services\Greatday\GetAtasan;
use App\Services\Greatday\GetBawahan;
use App\Services\Greatday\FirebaseService;
use App\Services\Hr\Greatday\OvertimeRequestHrService;
use App\Services\Hr\HrRequestResolver;
use App\Support\Greatday\FormSubmissionDates;
use App\Support\Greatday\HrdPayroll;

use App\Models\Greatday\{OvertimeRequest, OvertimeRequestMember};
use App\Models\{MasterDivisi, MasterKaryawan};

class OvertimeRequestsController extends Controller
{
    public function index()
    {
        if ($this->usesHrTables()) {
            return app(OvertimeRequestHrService::class)->index($this->karyawan);
        }

        $employee = $this->karyawan;

        $overtimeRequests = OvertimeRequest::with('members')->where('is_active', true);

        $overtimeRequests->where(function ($query) use ($employee) {
            $query->where(function ($q) use ($employee) {
                $q->whereHas('members', fn ($mq) => $mq->where('employee_id', $employee->id))
                    ->orWhere('created_by', $employee->nama_lengkap);
            });

            if (AtasanApprovalScope::isAtasanGrade($employee)) {
                $subordinateIds = AtasanApprovalScope::subordinateKaryawanIds($employee);
                $subordinateNames = AtasanApprovalScope::subordinateKaryawanNames($employee);

                if (!empty($subordinateIds) || !empty($subordinateNames)) {
                    $query->orWhere(function ($q) use ($subordinateIds, $subordinateNames) {
                        $q->where('status', 'Pending')
                            ->where(function ($inner) use ($subordinateIds, $subordinateNames) {
                                if (!empty($subordinateIds)) {
                                    $inner->whereHas('members', fn ($mq) => $mq->whereIn('employee_id', $subordinateIds));
                                }
                                if (!empty($subordinateNames)) {
                                    $inner->orWhereIn('created_by', $subordinateNames);
                                }
                            });
                    });
                }
            }
        });

        $overtimeRequests = $overtimeRequests
            ->latest()
            ->get()
            ->map(function ($item) {
                $item->department_name = MasterDivisi::find($item->department_id)->nama_divisi;

                $item->members = $item->members->map(function ($member) {
                    $member->employee_name = MasterKaryawan::find($member->employee_id)->nama_lengkap;

                    return $member;
                });

                return $item;
            });

        return response()->json([
            'data' => $overtimeRequests,
            'message' => 'Overtime requests retrieved successfully',
        ], 200);
    }

    public function getEmployees()
    {
        $employee = $this->karyawan;

        $getBawahan = GetBawahan::where('id', $employee->id)->get();
        $getAtasan = GetAtasan::where('id', $employee->id)->get();

        $employees = collect([$getBawahan, $getAtasan])
            ->flatten()
            ->unique('id')
            ->pluck('nama_lengkap', 'id')
            ->toArray();

        return response()->json([
            'data' => $employees,
            'message' => 'Employees retrieved successfully',
        ], 200);
    }

    public function store(Request $request)
    {
        if ($this->usesHrTables()) {
            return app(OvertimeRequestHrService::class)->store($request, $this->karyawan, $this->nama_lengkap);
        }

        DB::beginTransaction();
        try {
            $employee = $this->karyawan;

            if (!$request->id && !GreatdayOvertimeAccess::canCreate($employee)) {
                return response()->json(['message' => GreatdayOvertimeAccess::denyCreateMessage()], 403);
            }

            $dateError = FormSubmissionDates::validateRangeNotBackdated($request->start_date, $request->end_date);
            if ($dateError !== null) {
                return response()->json(['message' => $dateError], 422);
            }

            $overtimeRequest = $request->id ? OvertimeRequest::find($request->id) : new OvertimeRequest();

            $overtimeRequest->start_date = $request->start_date;
            $overtimeRequest->end_date = $request->end_date;
            $overtimeRequest->start_time = $request->start_time;
            $overtimeRequest->end_time = $request->end_time;
            $overtimeRequest->description = $request->description;

            if (!$request->id) {
                $overtimeRequest->no_document = str_replace('.', '/', microtime(true));
                $overtimeRequest->department_id = $request->department_id;
                $overtimeRequest->created_by = $this->nama_lengkap;
                $overtimeRequest->created_at = date('Y-m-d H:i:s');
            }

            $overtimeRequest->updated_by = $this->nama_lengkap;
            $overtimeRequest->updated_at = date('Y-m-d H:i:s');

            if ($request->id) {
                $overtimeRequest->status = 'Pending';
                $overtimeRequest->approved_atasan_by = null;
                $overtimeRequest->approved_atasan_at = null;
            } elseif (!$overtimeRequest->status) {
                $overtimeRequest->status = 'Pending';
            }

            $overtimeRequest->save();

            if ($request->id) {
                OvertimeRequestMember::where('overtime_request_id', $overtimeRequest->id)->update([
                    'updated_by' => $this->nama_lengkap,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'is_active' => false,
                ]);
            }

            $employeeIds = collect($request->employees)
                ->map(fn ($id) => (int) $id)
                ->push((int) $employee->id)
                ->unique()
                ->values()
                ->all();

            foreach ($employeeIds as $employeeId) {
                $member = OvertimeRequestMember::where([
                    'overtime_request_id' => $overtimeRequest->id,
                    'employee_id' => $employeeId,
                ])->first();

                if (!$member) {
                    OvertimeRequestMember::create([
                        'overtime_request_id' => $overtimeRequest->id,
                        'no_document' => $overtimeRequest->no_document,
                        'employee_id' => $employeeId,
                        'created_by' => $this->nama_lengkap,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_by' => $this->nama_lengkap,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $member->update([
                        'updated_by' => $this->nama_lengkap,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'is_active' => true,
                    ]);
                }
            }

            DB::commit();

            $service = new FirebaseService();

            $service->sendNotifications($employeeIds, [
                'title' => 'Permohonan Lembur Diajukan!',
                'body'  => 'Anda termasuk kedalam tim lembur yang diajukan oleh: ' . $overtimeRequest->created_by,
                'url'   => '/forms/overtimeRequests',
            ]);

            if ($employee->grade !== 'MANAGER') {
                $getAtasan = GetAtasan::where('id', $employee->id)->get();

                foreach ($getAtasan as $atasan) {
                    if ($atasan->grade === 'MANAGER') {
                        $department = MasterDivisi::find($overtimeRequest->department_id);

                        $service->sendNotifications([$atasan->id], [
                            'title' => 'Permohonan Lembur Diajukan!',
                            'body'  => 'Permohonan Lembur dari divisi: ' . $department->nama_divisi . ' menunggu persetujuan Anda',
                            'url'   => '/forms/overtimeRequests',
                        ]);
                    }
                }
            }

            return response()->json(['message' => 'Your permission request has been submitted successfully'], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function approve(Request $request)
    {
        if ($this->usesHrTables()) {
            $resolved = HrRequestResolver::findByApiId(HrRequest::TYPE_OVERTIME, (int) $request->id);
            if (!$resolved) {
                return response()->json(['message' => 'Overtime request not found'], 404);
            }
            $resolved->load('overtimeParticipants');
            if ($deny = $this->assertOvertimeHrAtasanCanAct($resolved)) {
                return $deny;
            }
            if ($deny = $this->assertHrChainApprover($resolved)) {
                return $deny;
            }

            return app(OvertimeRequestHrService::class)->approve((int) $request->id, $this->karyawan);
        }

        $overtimeRequest = OvertimeRequest::with('members')->find($request->id);
        if (!$overtimeRequest) {
            return response()->json(['message' => 'Overtime request not found'], 404);
        }

        if ($deny = $this->assertOvertimeAtasanCanAct($overtimeRequest)) {
            return $deny;
        }

        $department = MasterDivisi::find($overtimeRequest->department_id);
        $overtimeRequest->status = 'Approved Atasan';
        $overtimeRequest->approved_atasan_by = $this->nama_lengkap;
        $overtimeRequest->approved_atasan_at = date('Y-m-d H:i:s');
        $overtimeRequest->save();

        $service = new FirebaseService();
        $service->sendNotifications(HrdPayroll::queueNotificationUserIds(), [
            'title' => 'Permohonan Lembur Diajukan!',
            'body'  => 'Permohonan Lembur dari divisi: ' . ($department->nama_divisi ?? '') . ' menunggu persetujuan Anda',
            'url'   => '/forms/overtimeRequests',
        ]);

        return response()->json(['message' => 'The overtime request has been approved successfully'], 200);
    }

    public function reject(Request $request)
    {
        if ($this->usesHrTables()) {
            $resolved = HrRequestResolver::findByApiId(HrRequest::TYPE_OVERTIME, (int) $request->id);
            if (!$resolved) {
                return response()->json(['message' => 'Overtime request not found'], 404);
            }
            $resolved->load('overtimeParticipants');
            if ($deny = $this->assertPendingForAtasanReject($resolved->status)) {
                return $deny;
            }
            if ($deny = $this->assertOvertimeHrAtasanCanAct($resolved)) {
                return $deny;
            }
            if ($deny = $this->assertHrChainApprover($resolved)) {
                return $deny;
            }

            return app(OvertimeRequestHrService::class)->reject((int) $request->id, $this->karyawan, $request->reject_reason);
        }

        $overtimeRequest = OvertimeRequest::with('members')->find($request->id);
        if (!$overtimeRequest) {
            return response()->json(['message' => 'Overtime request not found'], 404);
        }

        if ($deny = $this->assertPendingForAtasanReject($overtimeRequest->status)) {
            return $deny;
        }
        if ($deny = $this->assertOvertimeAtasanCanAct($overtimeRequest)) {
            return $deny;
        }

        $overtimeRequest->status = 'Rejected Atasan';
        $overtimeRequest->rejected_atasan_by = $this->nama_lengkap;
        $overtimeRequest->rejected_atasan_at = date('Y-m-d H:i:s');
        $overtimeRequest->reject_atasan_reason = $request->reject_reason;
        $overtimeRequest->save();

        $service = new FirebaseService();
        $service->sendNotifications($overtimeRequest->members->where('is_active', true)->pluck('employee_id')->toArray(), [
            'title' => 'Permohonan Lembur Ditolak!',
            'body'  => 'Permohonan Lembur telah ditolak Atasan oleh: ' . $this->nama_lengkap . ' dengan alasan: ' . $request->reject_reason,
            'url'   => '/forms/overtimeRequests',
        ]);

        return response()->json(['message' => 'The overtime request has been rejected successfully'], 200);
    }
}
