<?php

namespace App\Http\Controllers\Greatday;

use App\Models\Greatday\AttendanceCorrection;
use App\Models\Hr\HrRequest;
use App\Models\MasterKaryawan;
use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Greatday\FirebaseService;
use App\Services\Greatday\GetAtasan;
use App\Services\Hr\Greatday\AttendanceCorrectionHrService;
use App\Services\Hr\Greatday\FormSubmitterVoidService;
use App\Services\Hr\HrFormAttachmentStorage;
use App\Services\Hr\HrRequestResolver;
use App\Support\Greatday\GreatdayAssetPaths;
use App\Support\Greatday\HrdPayroll;
use App\Support\Greatday\NotificationCopy;
use Illuminate\Http\Request;

class AttendanceCorrectionsController extends Controller
{
    public function index()
    {
        if ($this->usesHrTables()) {
            return app(AttendanceCorrectionHrService::class)->index($this->karyawan);
        }

        $employee = $this->karyawan;

        $attendanceCorrections = AttendanceCorrection::where('is_active', true);

        $attendanceCorrections->where(function ($query) use ($employee) {
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

        $attendanceCorrections = $attendanceCorrections
            ->latest()
            ->get()
            ->map(function ($item) {
                $employee = MasterKaryawan::find($item->employee_id);
                $item->employee_name = $employee->nama_lengkap;
                $item->employee_position = $employee->jabatan;
                $attachments = HrFormAttachmentStorage::resolvePublicUrls($item->attachment, GreatdayAssetPaths::KEY_KOREKSI_ABSEN);
                $item->attachments = $attachments;
                $item->attachment = $attachments[0] ?? null;

                return $item;
            });

        return response()->json([
            'data' => $attendanceCorrections,
            'message' => 'Attendance corrections retrieved successfully',
        ], 200);
    }

    public function store(Request $request)
    {
        if ($this->usesHrTables()) {
            return app(AttendanceCorrectionHrService::class)->store($request, $this->karyawan, $this->nama_lengkap);
        }

        $employee = $this->karyawan;

        $attendanceCorrection = $request->id ? AttendanceCorrection::find($request->id) : new AttendanceCorrection();

        $attendanceCorrection->employee_id = $this->user_id;
        $attendanceCorrection->type = $request->type;
        $attendanceCorrection->date = $request->date;
        $attendanceCorrection->time = $request->time;
        $attendanceCorrection->description = $request->description;

        $storedAttachments = HrFormAttachmentStorage::storeImages(
            HrFormAttachmentStorage::collectUploadedImages($request),
            GreatdayAssetPaths::KEY_KOREKSI_ABSEN
        );
        if ($storedAttachments !== null) {
            $attendanceCorrection->attachment = $storedAttachments;
        }

        if (!$request->id) {
            $attendanceCorrection->created_by = $this->nama_lengkap;
            $attendanceCorrection->created_at = date('Y-m-d H:i:s');
        }

        $attendanceCorrection->updated_by = $this->nama_lengkap;
        $attendanceCorrection->updated_at = date('Y-m-d H:i:s');

        $attendanceCorrection->save();

        $service = new FirebaseService();

        if ($employee->grade !== 'MANAGER') {
            $getAtasan = GetAtasan::where('id', $employee->id)->get();

            foreach ($getAtasan as $atasan) {
                if ($atasan->grade === 'MANAGER') {
                    $service->sendNotifications(
                        [$atasan->id],
                        NotificationCopy::legacyAtasanPending(
                            'Koreksi kehadiran',
                            $attendanceCorrection->created_by,
                            NotificationCopy::pathForms('approval')
                        )
                    );
                }
            }
        }

        return response()->json(['message' => 'Your attendance correction has been submitted successfully'], 201);
    }

    public function approve(Request $request)
    {
        if ($this->usesHrTables()) {
            $resolved = HrRequestResolver::findByApiId(HrRequest::TYPE_ATTENDANCE_CORRECTION, (int) $request->id);
            if (!$resolved) {
                return response()->json(['message' => 'Attendance correction not found'], 404);
            }
            if ($deny = $this->assertPendingForAtasan($resolved->status)) {
                return $deny;
            }
            if ($deny = $this->assertHrChainApprover($resolved)) {
                return $deny;
            }

            return app(AttendanceCorrectionHrService::class)->approve((int) $request->id, $this->karyawan);
        }

        $attendanceCorrection = AttendanceCorrection::find($request->id);
        if (!$attendanceCorrection) {
            return response()->json(['message' => 'Attendance correction not found'], 404);
        }

        if ($deny = $this->assertPendingForAtasan($attendanceCorrection->status)) {
            return $deny;
        }
        if ($deny = $this->assertApproverIsAtasanOf((int) $attendanceCorrection->employee_id)) {
            return $deny;
        }

        $attendanceCorrection->status = 'Approved Atasan';
        $attendanceCorrection->approved_atasan_by = $this->nama_lengkap;
        $attendanceCorrection->approved_atasan_at = date('Y-m-d H:i:s');
        $attendanceCorrection->save();

        $service = new FirebaseService();
        $service->sendNotifications(
            HrdPayroll::queueNotificationUserIds(),
            NotificationCopy::legacyForwardToHrd(
                'Koreksi kehadiran',
                $attendanceCorrection->created_by,
                NotificationCopy::pathForms('approval')
            )
        );

        return response()->json(['message' => 'The attendance correction has been approved successfully'], 200);
    }

    public function reject(Request $request)
    {
        if ($this->usesHrTables()) {
            $resolved = HrRequestResolver::findByApiId(HrRequest::TYPE_ATTENDANCE_CORRECTION, (int) $request->id);
            if (!$resolved) {
                return response()->json(['message' => 'Attendance correction not found'], 404);
            }
            if ($deny = $this->assertPendingForAtasanReject($resolved->status)) {
                return $deny;
            }
            if ($deny = $this->assertHrChainApprover($resolved)) {
                return $deny;
            }

            return app(AttendanceCorrectionHrService::class)->reject((int) $request->id, $this->karyawan, $request->reject_reason);
        }

        $attendanceCorrection = AttendanceCorrection::find($request->id);
        if (!$attendanceCorrection) {
            return response()->json(['message' => 'Attendance correction not found'], 404);
        }

        if ($deny = $this->assertPendingForAtasanReject($attendanceCorrection->status)) {
            return $deny;
        }
        if ($deny = $this->assertApproverIsAtasanOf((int) $attendanceCorrection->employee_id)) {
            return $deny;
        }

        $attendanceCorrection->status = 'Rejected Atasan';
        $attendanceCorrection->rejected_atasan_by = $this->nama_lengkap;
        $attendanceCorrection->rejected_atasan_at = date('Y-m-d H:i:s');
        $attendanceCorrection->reject_atasan_reason = $request->reject_reason;
        $attendanceCorrection->save();

        $service = new FirebaseService();
        $service->sendNotifications(
            [$attendanceCorrection->employee_id],
            NotificationCopy::legacyRejected(
                'Koreksi kehadiran',
                $this->nama_lengkap,
                $request->reject_reason,
                NotificationCopy::pathForms('submission')
            )
        );

        return response()->json(['message' => 'The attendance correction has been rejected successfully'], 200);
    }

    public function void(Request $request)
    {
        try {
            if ($this->usesHrTables()) {
                $resolved = HrRequestResolver::findByApiId(HrRequest::TYPE_ATTENDANCE_CORRECTION, (int) $request->id);
                if (!$resolved) {
                    return response()->json(['message' => 'Attendance correction not found'], 404);
                }
                app(FormSubmitterVoidService::class)->voidHrRequest($resolved, $this->karyawan);
            } else {
                app(FormSubmitterVoidService::class)->voidLegacyAttendanceCorrection((int) $request->id, $this->karyawan);
            }

            return response()->json(['message' => 'Koreksi absensi berhasil dibatalkan.'], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
