<?php

namespace App\Services\Hr\Greatday;

use App\Models\Hr\HrPermissionDetail;
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
use App\Services\Hr\Presenters\PermissionRequestPresenter;
use App\Services\Hr\WorkflowStatus;
use App\Support\Greatday\HrdPayroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PermissionRequestHrService
{
    use BootstrapsHrAtasanChain;

    public function index(MasterKaryawan $employee)
    {
        $rows = HrRequest::with(['permissionDetail'])
            ->where('request_type', HrRequest::TYPE_PERMISSION)
            ->where('is_active', true);

        GreatdayIndexScope::apply($rows, $employee);

        $data = $rows->orderByDesc('id')->get()->map(fn ($item) => PermissionRequestPresenter::toGreatdayJson($item));

        return response()->json([
            'data' => $data,
            'message' => 'Permission requests retrieved successfully',
        ], 200);
    }

    public function store(Request $request, MasterKaryawan $employee, string $actorName)
    {
        $now = Carbon::now();
        $kind = 'event';
        if ($request->type === 'Sick Leave') {
            $kind = 'sick';
        } elseif ($request->type === 'Late Arrival') {
            $kind = 'late';
        }

        $status = WorkflowStatus::PENDING;

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $destinationPath = public_path('permission-requests');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            $fileName = str_replace('.', '', microtime(true)) . '.' . $file->getClientOriginalExtension();
            $file->move($destinationPath, $fileName);
            $attachmentPath = $fileName;
        }

        $header = HrRequest::create([
            'uuid' => (string) Str::uuid(),
            'request_type' => HrRequest::TYPE_PERMISSION,
            'no_document' => str_replace('.', '/', microtime(true)),
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

        HrPermissionDetail::create([
            'request_id' => $header->id,
            'permission_kind' => $kind,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'attachment_path' => $attachmentPath,
        ]);

        $header = $this->bootstrapAtasanChain($header, $employee, '/forms/permissionRequests');

        app(LegacyHrMirror::class)->mirrorCreateFromHrRequest($header->fresh(['permissionDetail']));

        return response()->json(['message' => 'Your permission request has been submitted successfully'], 201);
    }

    public function approve(int $apiId, MasterKaryawan $approver)
    {
        $row = HrRequestResolver::findByApiId(HrRequest::TYPE_PERMISSION, $apiId);
        if (!$row) {
            return response()->json(['message' => 'Permission request not found'], 404);
        }

        if (!app(HrApprovalChainService::class)->viewerCanApprove($row, $approver)) {
            return response()->json(['message' => 'Bukan giliran Anda menyetujui pengajuan ini'], 403);
        }

        app(ApprovalService::class)->approveAtasan($row, $approver, ApprovalService::CHANNEL_GREATDAY);
        app(LegacyHrMirror::class)->syncAtasanApproval($row->fresh());

        $row = $row->fresh();
        if ($row->status === WorkflowStatus::APPROVED_ATASAN) {
            (new FirebaseService())->sendNotifications(HrdPayroll::queueNotificationUserIds(), [
                'title' => 'Permohonan Izin Diajukan!',
                'body' => 'Terdapat Permohonan Izin yang diajukan oleh: ' . $row->created_by_name . ' menunggu persetujuan Anda',
                'url' => '/forms/permissionRequests',
            ]);
        }

        return response()->json(['message' => 'The permission request has been approved successfully'], 200);
    }

    public function reject(int $apiId, MasterKaryawan $approver, ?string $reason)
    {
        $row = HrRequestResolver::findByApiId(HrRequest::TYPE_PERMISSION, $apiId);
        if (!$row) {
            return response()->json(['message' => 'Permission request not found'], 404);
        }

        if (!app(HrApprovalChainService::class)->viewerCanApprove($row, $approver)) {
            return response()->json(['message' => 'Bukan giliran Anda menolak pengajuan ini'], 403);
        }

        app(ApprovalService::class)->rejectAtasan($row, $approver, $reason, ApprovalService::CHANNEL_GREATDAY);
        app(LegacyHrMirror::class)->syncAtasanApproval($row->fresh());

        (new FirebaseService())->sendNotifications([$row->karyawan_id], [
            'title' => 'Permohonan Izin Ditolak!',
            'body' => 'Permohonan Izin telah ditolak Atasan oleh: ' . $approver->nama_lengkap . ' dengan alasan: ' . $reason,
            'url' => '/forms/permissionRequests',
        ]);

        return response()->json(['message' => 'The permission request has been rejected successfully'], 200);
    }
}
