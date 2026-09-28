<?php

namespace App\Http\Controllers\Greatday;

use Illuminate\Http\Request;

use App\Models\Hr\HrRequest;
use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Greatday\GetAtasan;
use App\Services\Greatday\FirebaseService;
use App\Services\Hr\Greatday\FormSubmitterVoidService;
use App\Services\Hr\Greatday\PermissionRequestHrService;
use App\Services\Hr\HrFormAttachmentStorage;
use App\Services\Hr\HrRequestResolver;
use App\Support\Greatday\FormSubmissionDates;
use App\Support\Greatday\GreatdayAssetPaths;
use App\Support\Greatday\HrdPayroll;

use App\Models\Greatday\{PermissionRequest};
use App\Models\{MasterKaryawan};

class PermissionRequestsController extends Controller
{
    public function index()
    {
        if ($this->usesHrTables()) {
            return app(PermissionRequestHrService::class)->index($this->karyawan);
        }

        $employee = $this->karyawan;

        $permissionRequests = PermissionRequest::where('is_active', true);

        $permissionRequests->where(function ($query) use ($employee) {
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

        $permissionRequests = $permissionRequests
            ->latest()
            ->get()
            ->map(function ($item) {
                $employee = MasterKaryawan::find($item->employee_id);
                $item->employee_name = $employee->nama_lengkap;
                $item->employee_position = $employee->jabatan;
                $attachments = HrFormAttachmentStorage::resolvePublicUrls($item->attachment, GreatdayAssetPaths::KEY_IZIN);
                $item->attachments = $attachments;
                $item->attachment = $attachments[0] ?? null;

                return $item;
            });

        return response()->json([
            'data' => $permissionRequests,
            'message' => 'Permission requests retrieved successfully',
        ], 200);
    }

    public function store(Request $request)
    {
        if ($this->usesHrTables()) {
            return app(PermissionRequestHrService::class)->store($request, $this->karyawan, $this->nama_lengkap);
        }

        $employee = $this->karyawan;

        $startDate = $request->start_date;
        $endDate = $request->type === 'Event Leave' ? $startDate : ($request->end_date ?: $startDate);

        $dateError = FormSubmissionDates::validateRangeNotBackdated($startDate, $endDate);
        if ($dateError !== null) {
            return response()->json(['message' => $dateError], 422);
        }

        $permissionRequest = $request->id ? PermissionRequest::find($request->id) : new PermissionRequest();

        $permissionRequest->employee_id = $this->user_id;
        $permissionRequest->no_document = str_replace('.', '/', microtime(true));
        $permissionRequest->type = $request->type;
        $permissionRequest->start_date = $startDate;
        $permissionRequest->end_date = $endDate;
        $permissionRequest->start_time = $request->start_time;
        $permissionRequest->end_time = $request->end_time;
        $permissionRequest->description = $request->description;

        if (!$request->id && !HrFormAttachmentStorage::hasUploadedImages($request)) {
            return response()->json(['message' => 'Lampiran wajib — ambil foto dari kamera.'], 422);
        }

        $storedAttachments = HrFormAttachmentStorage::storeImages(
            HrFormAttachmentStorage::collectUploadedImages($request),
            GreatdayAssetPaths::KEY_IZIN
        );
        if ($storedAttachments !== null) {
            $permissionRequest->attachment = $storedAttachments;
        }

        if (!$request->id) {
            $permissionRequest->created_by = $this->nama_lengkap;
            $permissionRequest->created_at = date('Y-m-d H:i:s');
        }

        $permissionRequest->updated_by = $this->nama_lengkap;
        $permissionRequest->updated_at = date('Y-m-d H:i:s');

        if ($employee->grade === 'MANAGER') {
            $permissionRequest->status = 'Approved Atasan';
            $permissionRequest->approved_atasan_by = $this->nama_lengkap;
            $permissionRequest->approved_atasan_at = date('Y-m-d H:i:s');
        }

        $permissionRequest->save();

        $service = new FirebaseService();

        if ($employee->grade !== 'MANAGER') {
            $getAtasan = GetAtasan::where('id', $employee->id)->get();

            foreach ($getAtasan as $atasan) {
                if ($atasan->grade === 'MANAGER') {
                    $service->sendNotifications([$atasan->id], [
                        'title' => 'Permohonan Izin Diajukan!',
                        'body' => 'Permohonan izin baru telah diajukan oleh ' . $employee->nama_lengkap . ' menunggu persetujuan Anda',
                        'url' => '/forms/permissionRequests',
                    ]);
                }
            }
        }


        return response()->json(['message' => 'Your permission request has been submitted successfully'], 201);
    }

    public function approve(Request $request)
    {
        if ($this->usesHrTables()) {
            $resolved = HrRequestResolver::findByApiId(HrRequest::TYPE_PERMISSION, (int) $request->id);
            if (!$resolved) {
                return response()->json(['message' => 'Permission request not found'], 404);
            }
            if ($deny = $this->assertPendingForAtasan($resolved->status)) {
                return $deny;
            }
            if ($deny = $this->assertHrChainApprover($resolved)) {
                return $deny;
            }

            return app(PermissionRequestHrService::class)->approve((int) $request->id, $this->karyawan);
        }

        $permissionRequest = PermissionRequest::find($request->id);
        if (!$permissionRequest) {
            return response()->json(['message' => 'Permission request not found'], 404);
        }

        if ($deny = $this->assertPendingForAtasan($permissionRequest->status)) {
            return $deny;
        }
        if ($deny = $this->assertApproverIsAtasanOf((int) $permissionRequest->employee_id)) {
            return $deny;
        }

        $permissionRequest->status = 'Approved Atasan';
        $permissionRequest->approved_atasan_by = $this->nama_lengkap;
        $permissionRequest->approved_atasan_at = date('Y-m-d H:i:s');
        $permissionRequest->save();

        $service = new FirebaseService();
        $service->sendNotifications(HrdPayroll::queueNotificationUserIds(), [
            'title' => 'Permohonan Izin Diajukan!',
            'body' => 'Terdapat Permohonan Izin yang diajukan oleh: ' . $permissionRequest->created_by . ' menunggu persetujuan Anda',
            'url' => '/forms/permissionRequests',
        ]);

        return response()->json(['message' => 'The permission request has been approved successfully'], 200);
    }

    public function reject(Request $request)
    {
        if ($this->usesHrTables()) {
            $resolved = HrRequestResolver::findByApiId(HrRequest::TYPE_PERMISSION, (int) $request->id);
            if (!$resolved) {
                return response()->json(['message' => 'Permission request not found'], 404);
            }
            if ($deny = $this->assertPendingForAtasanReject($resolved->status)) {
                return $deny;
            }
            if ($deny = $this->assertHrChainApprover($resolved)) {
                return $deny;
            }

            return app(PermissionRequestHrService::class)->reject((int) $request->id, $this->karyawan, $request->reject_reason);
        }

        $permissionRequest = PermissionRequest::find($request->id);
        if (!$permissionRequest) {
            return response()->json(['message' => 'Permission request not found'], 404);
        }

        if ($deny = $this->assertPendingForAtasanReject($permissionRequest->status)) {
            return $deny;
        }
        if ($deny = $this->assertApproverIsAtasanOf((int) $permissionRequest->employee_id)) {
            return $deny;
        }

        $permissionRequest->status = 'Rejected Atasan';
        $permissionRequest->rejected_atasan_by = $this->nama_lengkap;
        $permissionRequest->rejected_atasan_at = date('Y-m-d H:i:s');
        $permissionRequest->reject_atasan_reason = $request->reject_reason;
        $permissionRequest->save();

        $service = new FirebaseService();
        $service->sendNotifications([$permissionRequest->employee_id], [
            'title' => 'Permohonan Izin Ditolak!',
            'body' => 'Permohonan Izin telah ditolak Atasan oleh: ' . $this->nama_lengkap . ' dengan alasan: ' . $request->reject_reason,
            'url' => '/forms/permissionRequests',
        ]);

        return response()->json(['message' => 'The permission request has been rejected successfully'], 200);
    }

    public function void(Request $request)
    {
        try {
            if ($this->usesHrTables()) {
                $resolved = HrRequestResolver::findByApiId(HrRequest::TYPE_PERMISSION, (int) $request->id);
                if (!$resolved) {
                    return response()->json(['message' => 'Permission request not found'], 404);
                }
                app(FormSubmitterVoidService::class)->voidHrRequest($resolved, $this->karyawan);
            } else {
                app(FormSubmitterVoidService::class)->voidLegacyPermission((int) $request->id, $this->karyawan);
            }

            return response()->json(['message' => 'Pengajuan izin berhasil dibatalkan.'], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
