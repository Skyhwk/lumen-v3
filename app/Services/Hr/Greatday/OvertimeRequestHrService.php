<?php

namespace App\Services\Hr\Greatday;

use App\Models\Hr\HrOvertimeDetail;
use App\Models\Hr\HrOvertimeParticipant;
use App\Models\Hr\HrRequest;
use App\Models\MasterDivisi;
use App\Models\MasterKaryawan;
use App\Services\Greatday\FirebaseService;
use App\Services\Greatday\GetAtasan;
use App\Services\Hr\ApprovalService;
use App\Services\Hr\AtasanStepService;
use App\Services\Hr\GreatdayIndexScope;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\LegacyHrMirror;
use App\Services\Hr\Presenters\OvertimeRequestPresenter;
use App\Services\Hr\WorkflowStatus;
use App\Support\Greatday\HrdPayroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OvertimeRequestHrService
{
    public function index(MasterKaryawan $employee)
    {
        $rows = HrRequest::with(['overtimeDetail', 'overtimeParticipants'])
            ->where('request_type', HrRequest::TYPE_OVERTIME)
            ->where('is_active', true);

        GreatdayIndexScope::applyOvertime($rows, $employee);

        $data = $rows->orderByDesc('id')->get()->map(fn ($item) => OvertimeRequestPresenter::toGreatdayJson($item));

        return response()->json([
            'data' => $data,
            'message' => 'Overtime requests retrieved successfully',
        ], 200);
    }

    public function store(Request $request, MasterKaryawan $employee, string $actorName)
    {
        DB::beginTransaction();
        try {
            $now = Carbon::now();
            $existing = $request->id
                ? HrRequestResolver::findByApiId(HrRequest::TYPE_OVERTIME, (int) $request->id)
                : null;

            $header = $existing ?: new HrRequest();
            $isNew = !$existing;

            if ($isNew) {
                $header->uuid = (string) Str::uuid();
                $header->request_type = HrRequest::TYPE_OVERTIME;
                $header->no_document = str_replace('.', '/', microtime(true));
                $header->id_department = $request->department_id;
                $header->karyawan_id = $employee->id;
                $header->workflow_code = 'overtime_3_step_finance';
                $header->created_by_name = $actorName;
                $header->created_at = $now;
                $header->created_by_karyawan_id = $employee->id;
                $header->is_active = true;
                $header->status = WorkflowStatus::PENDING;
            }

            $header->description = $request->description;
            $header->updated_by_name = $actorName;
            $header->updated_at = $now;

            if ($employee->grade === 'MANAGER') {
                $header->status = WorkflowStatus::APPROVED_ATASAN;
            } elseif ($existing) {
                $header->status = WorkflowStatus::PENDING;
            }

            $header->save();

            HrOvertimeDetail::updateOrCreate(
                ['request_id' => $header->id],
                [
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'start_time' => $request->start_time,
                    'end_time' => $request->end_time,
                ]
            );

            if ($existing) {
                HrOvertimeParticipant::where('request_id', $header->id)->update(['is_active' => false]);
            } elseif ($isNew) {
                app(AtasanStepService::class)->seedPendingAtasanStep($header->id);
            }

            $employeeIds = collect($request->employees)
                ->map(fn ($id) => (int) $id)
                ->push((int) $employee->id)
                ->unique()
                ->values()
                ->all();

            foreach ($employeeIds as $employeeId) {
                HrOvertimeParticipant::updateOrCreate(
                    ['request_id' => $header->id, 'karyawan_id' => $employeeId],
                    ['is_active' => true, 'updated_at' => $now, 'created_at' => $now]
                );
            }

            if ($header->status === WorkflowStatus::APPROVED_ATASAN) {
                app(ApprovalService::class)->approveAtasan($header->fresh(), $employee, ApprovalService::CHANNEL_GREATDAY);
            }

            DB::commit();

            $service = new FirebaseService();
            $service->sendNotifications($employeeIds, [
                'title' => 'Permohonan Lembur Diajukan!',
                'body'  => 'Anda termasuk kedalam tim lembur yang diajukan oleh: ' . $header->created_by_name,
                'url'   => '/forms/overtimeRequests',
            ]);

            if ($employee->grade !== 'MANAGER' && $header->status === WorkflowStatus::PENDING) {
                foreach (GetAtasan::where('id', $employee->id)->get() as $atasan) {
                    if ($atasan->grade === 'MANAGER') {
                        $department = MasterDivisi::find($header->id_department);
                        $service->sendNotifications([$atasan->id], [
                            'title' => 'Permohonan Lembur Diajukan!',
                            'body'  => 'Permohonan Lembur dari divisi: ' . ($department->nama_divisi ?? '') . ' menunggu persetujuan Anda',
                            'url'   => '/forms/overtimeRequests',
                        ]);
                    }
                }
            }

            app(LegacyHrMirror::class)->mirrorCreateFromHrRequest(
                $header->fresh(['overtimeDetail', 'overtimeParticipants'])
            );

            return response()->json(['message' => 'Your permission request has been submitted successfully'], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function approve(int $apiId, MasterKaryawan $approver)
    {
        $row = HrRequestResolver::findByApiId(HrRequest::TYPE_OVERTIME, $apiId);
        if (!$row) {
            return response()->json(['message' => 'Overtime request not found'], 404);
        }

        $row->load('overtimeParticipants');

        app(ApprovalService::class)->approveAtasan($row, $approver, ApprovalService::CHANNEL_GREATDAY);
        app(LegacyHrMirror::class)->syncAtasanApproval($row->fresh());

        $department = MasterDivisi::find($row->id_department);
        (new FirebaseService())->sendNotifications(HrdPayroll::queueNotificationUserIds(), [
            'title' => 'Permohonan Lembur Diajukan!',
            'body'  => 'Permohonan Lembur dari divisi: ' . ($department->nama_divisi ?? '') . ' menunggu persetujuan Anda',
            'url'   => '/forms/overtimeRequests',
        ]);

        return response()->json(['message' => 'The overtime request has been approved successfully'], 200);
    }

    public function reject(int $apiId, MasterKaryawan $approver, ?string $reason)
    {
        $row = HrRequestResolver::findByApiId(HrRequest::TYPE_OVERTIME, $apiId);
        if (!$row) {
            return response()->json(['message' => 'Overtime request not found'], 404);
        }

        $row->load('overtimeParticipants');

        app(ApprovalService::class)->rejectAtasan($row, $approver, $reason, ApprovalService::CHANNEL_GREATDAY);
        app(LegacyHrMirror::class)->syncAtasanApproval($row->fresh());

        $ids = $row->overtimeParticipants->where('is_active', true)->pluck('karyawan_id')->toArray();
        (new FirebaseService())->sendNotifications($ids, [
            'title' => 'Permohonan Lembur Ditolak!',
            'body'  => 'Permohonan Lembur telah ditolak Atasan oleh: ' . $approver->nama_lengkap . ' dengan alasan: ' . $reason,
            'url'   => '/forms/overtimeRequests',
        ]);

        return response()->json(['message' => 'The overtime request has been rejected successfully'], 200);
    }
}
