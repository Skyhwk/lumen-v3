<?php

namespace App\Services\Hr\Greatday;

use App\Models\Hr\HrAttendanceCorrectionDetail;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\FirebaseService;
use App\Services\Greatday\GetAtasan;
use App\Services\Hr\Greatday\Concerns\BootstrapsHrAtasanChain;
use App\Services\Hr\ApprovalService;
use App\Services\Hr\HrApprovalChainService;
use App\Services\Hr\GreatdayIndexScope;
use App\Services\Hr\HrRequestResolver;
use App\Services\Hr\LegacyHrMirror;
use App\Services\Hr\Presenters\AttendanceCorrectionPresenter;
use App\Services\Hr\WorkflowStatus;
use App\Support\Greatday\HrdPayroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttendanceCorrectionHrService
{
    use BootstrapsHrAtasanChain;

    public function index(MasterKaryawan $employee)
    {
        $rows = HrRequest::with(['attendanceCorrectionDetail'])
            ->where('request_type', HrRequest::TYPE_ATTENDANCE_CORRECTION)
            ->where('is_active', true);

        GreatdayIndexScope::apply($rows, $employee);

        $data = $rows->orderByDesc('id')->get()->map(function ($item) {
            return AttendanceCorrectionPresenter::toGreatdayJson($item);
        });

        return response()->json([
            'data' => $data,
            'message' => 'Attendance corrections retrieved successfully',
        ], 200);
    }

    public function store(Request $request, MasterKaryawan $employee, string $actorName)
    {
        $now = Carbon::now();
        $noDocument = 'AC/' . str_replace('.', '/', microtime(true));

        $status = WorkflowStatus::PENDING;

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $destinationPath = public_path('attendance-corrections');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            $fileName = str_replace('.', '', microtime(true)) . '.' . $file->getClientOriginalExtension();
            $file->move($destinationPath, $fileName);
            $attachmentPath = $fileName;
        }

        $header = HrRequest::create([
            'uuid' => (string) Str::uuid(),
            'request_type' => HrRequest::TYPE_ATTENDANCE_CORRECTION,
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

        HrAttendanceCorrectionDetail::create([
            'request_id' => $header->id,
            'correction_type' => $request->type,
            'correction_date' => $request->date,
            'correction_time' => $request->time,
            'attachment_path' => $attachmentPath,
        ]);

        $header = $this->bootstrapAtasanChain($header, $employee, '/forms/attendanceCorrections');

        app(LegacyHrMirror::class)->mirrorCreateFromHrRequest($header->fresh(['attendanceCorrectionDetail']));

        return response()->json(['message' => 'Your attendance correction has been submitted successfully'], 201);
    }

    public function approve(int $apiId, MasterKaryawan $approver)
    {
        $row = HrRequestResolver::findByApiId(HrRequest::TYPE_ATTENDANCE_CORRECTION, $apiId);
        if (!$row) {
            return response()->json(['message' => 'Attendance correction not found'], 404);
        }

        if (!app(HrApprovalChainService::class)->viewerCanApprove($row, $approver)) {
            return response()->json(['message' => 'Bukan giliran Anda menyetujui pengajuan ini'], 403);
        }

        app(ApprovalService::class)->approveAtasan($row, $approver, ApprovalService::CHANNEL_GREATDAY);
        app(LegacyHrMirror::class)->syncAtasanApproval($row->fresh());

        $row = $row->fresh();
        if ($row->status === WorkflowStatus::APPROVED_ATASAN) {
            $service = new FirebaseService();
            $service->sendNotifications(HrdPayroll::queueNotificationUserIds(), [
                'title' => 'Permohonan Koreksi Kehadiran Diajukan!',
                'body'  => 'Terdapat Permohonan Koreksi Kehadiran yang diajukan oleh: ' . $row->created_by_name . ' menunggu persetujuan Anda',
                'url'   => '/forms/attendanceCorrections',
            ]);
        }

        return response()->json(['message' => 'The attendance correction has been approved successfully'], 200);
    }

    public function reject(int $apiId, MasterKaryawan $approver, ?string $reason)
    {
        $row = HrRequestResolver::findByApiId(HrRequest::TYPE_ATTENDANCE_CORRECTION, $apiId);
        if (!$row) {
            return response()->json(['message' => 'Attendance correction not found'], 404);
        }

        if (!app(HrApprovalChainService::class)->viewerCanApprove($row, $approver)) {
            return response()->json(['message' => 'Bukan giliran Anda menolak pengajuan ini'], 403);
        }

        app(ApprovalService::class)->rejectAtasan($row, $approver, $reason, ApprovalService::CHANNEL_GREATDAY);
        app(LegacyHrMirror::class)->syncAtasanApproval($row->fresh());

        $service = new FirebaseService();
        $service->sendNotifications([$row->karyawan_id], [
            'title' => 'Permohonan Koreksi Kehadiran Ditolak!',
            'body'  => 'Permohonan Koreksi Kehadiran telah ditolak Atasan oleh: ' . $approver->nama_lengkap . ' dengan alasan: ' . $reason,
            'url'   => '/forms/attendanceCorrections',
        ]);

        return response()->json(['message' => 'The attendance correction has been rejected successfully'], 200);
    }

    private function notifyManager(MasterKaryawan $employee, string $actorName): void
    {
        $service = new FirebaseService();
        $getAtasan = GetAtasan::where('id', $employee->id)->get();

        foreach ($getAtasan as $atasan) {
            if ($atasan->grade === 'MANAGER') {
                $service->sendNotifications([$atasan->id], [
                    'title' => 'Permohonan Koreksi Kehadiran Diajukan!',
                    'body'  => 'Terdapat Permohonan Koreksi Kehadiran yang diajukan oleh: ' . $actorName . ' menunggu persetujuan Anda',
                    'url'   => '/forms/attendanceCorrections',
                ]);
            }
        }
    }
}
