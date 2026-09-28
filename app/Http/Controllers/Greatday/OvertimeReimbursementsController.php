<?php

namespace App\Http\Controllers\Greatday;

use Illuminate\Http\Request;

use App\Services\Greatday\AtasanApprovalScope;
use App\Services\Greatday\GetAtasan;
use App\Services\Greatday\FirebaseService;
use App\Services\Hr\HrFormAttachmentStorage;
use App\Support\Greatday\GreatdayAssetPaths;
use App\Support\Greatday\HrdPayroll;

use App\Models\Greatday\{OvertimeReimbursement};
use App\Models\{MasterKaryawan};

class OvertimeReimbursementsController extends Controller
{
    public function index()
    {
        $employee = $this->karyawan;

        $overtimeReimbursements = OvertimeReimbursement::where('is_active', true);

        if (HrdPayroll::canAccessHrdQueue($employee)) {
            $overtimeReimbursements->where('status', 'Approved Atasan');
        } elseif ($employee->jabatan === 'Accounting & Expense Manager') {
            $overtimeReimbursements->where('status', 'Approved HRD');
        } else {
            $overtimeReimbursements->where(function ($query) use ($employee) {
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
        }

        $overtimeReimbursements = $overtimeReimbursements
            ->latest()
            ->get()
            ->map(function ($item) {
                $employee = MasterKaryawan::find($item->employee_id);
                $item->employee_name = $employee->nama_lengkap;
                $item->employee_position = $employee->jabatan;
                $attachments = HrFormAttachmentStorage::resolvePublicUrls(
                    $item->attachment,
                    GreatdayAssetPaths::KEY_LEMBUR_REIMBURSE
                );
                $item->attachments = $attachments;
                $item->attachment = $attachments[0] ?? null;

                return $item;
            });

        return response()->json([
            'data' => $overtimeReimbursements,
            'message' => 'Overtime reimbursements retrieved successfully',
        ], 200);
    }

    public function store(Request $request)
    {
        $employee = $this->karyawan;

        $overtimeReimbursement = $request->id ? OvertimeReimbursement::find($request->id) : new OvertimeReimbursement();

        $overtimeReimbursement->employee_id = $this->user_id;
        $overtimeReimbursement->date = $request->date;
        $overtimeReimbursement->amount = $request->amount;
        $overtimeReimbursement->description = $request->description;

        $storedAttachments = HrFormAttachmentStorage::storeImages(
            HrFormAttachmentStorage::collectUploadedImages($request),
            GreatdayAssetPaths::KEY_LEMBUR_REIMBURSE
        );
        if ($storedAttachments !== null) {
            $overtimeReimbursement->attachment = $storedAttachments;
        }

        if (!$request->id) {
            $overtimeReimbursement->created_by = $this->nama_lengkap;
            $overtimeReimbursement->created_at = date('Y-m-d H:i:s');
        }

        $overtimeReimbursement->updated_by = $this->nama_lengkap;
        $overtimeReimbursement->updated_at = date('Y-m-d H:i:s');

        if ($employee->grade === 'MANAGER') {
            $overtimeReimbursement->status = 'Approved Atasan';
            $overtimeReimbursement->approved_atasan_by = $this->nama_lengkap;
            $overtimeReimbursement->approved_atasan_at = date('Y-m-d H:i:s');
        }

        $overtimeReimbursement->save();

        $service = new FirebaseService();

        if ($employee->grade !== 'MANAGER') {
            $getAtasan = GetAtasan::where('id', $employee->id)->get();

            foreach ($getAtasan as $atasan) {
                if ($atasan->grade === 'MANAGER') {
                    $service->sendNotifications([$atasan->id], [
                        'title' => 'Penggantian Biaya Lembur Diajukan!',
                        'body' => 'Penggantian biaya lembur telah diajukan oleh ' . $employee->nama_lengkap,
                        'url' => '/forms/overtimeReimbursements',
                    ]);
                }
            }
        }

        return response()->json(['message' => 'Your overtime reimbursement has been submitted successfully'], 201);
    }

    public function approve(Request $request)
    {
        return $this->portalOnlyResponse('approve');

        $overtimeReimbursement = OvertimeReimbursement::find($request->id);

        $service = new FirebaseService();

        if ($overtimeReimbursement->status === 'Pending') {
            $overtimeReimbursement->status = 'Approved Atasan';
            $overtimeReimbursement->approved_atasan_by = $this->nama_lengkap;
            $overtimeReimbursement->approved_atasan_at = date('Y-m-d H:i:s');

            $service->sendNotifications(HrdPayroll::queueNotificationUserIds(), [
                'title' => 'Penggantian Biaya Lembur Diajukan!',
                'body'  => 'Penggantian biaya lembur telah diajukan oleh: ' . $overtimeReimbursement->created_by . ' menunggu persetujuan Anda',
                'url'   => '/forms/overtimeReimbursements',
            ]);
        } else if ($overtimeReimbursement->status === 'Approved Atasan') {
            $overtimeReimbursement->status = 'Approved HRD';
            $overtimeReimbursement->approved_hrd_by = $this->nama_lengkap;
            $overtimeReimbursement->approved_hrd_at = date('Y-m-d H:i:s');

            $finance = MasterKaryawan::where('jabatan', 'Accounting & Expense Manager')->where('is_active', true)->first();

            $service->sendNotifications([$finance->id], [
                'title' => 'Penggantian Biaya Lembur Diajukan!',
                'body'  => 'Penggantian biaya lembur telah diajukan oleh: ' . $overtimeReimbursement->created_by . ' menunggu persetujuan Anda',
                'url'   => '/forms/overtimeReimbursements',
            ]);
        } else {
            $overtimeReimbursement->status = 'Approved Finance';
            $overtimeReimbursement->approved_finance_by = $this->nama_lengkap;
            $overtimeReimbursement->approved_finance_at = date('Y-m-d H:i:s');

            $service->sendNotifications([$overtimeReimbursement->employee_id], [
                'title' => 'Penggantian Biaya Lembur Disetujui!',
                'body'  => 'Penggantian biaya lembur Anda telah disetujui oleh: ' . $this->nama_lengkap,
                'url'   => '/forms/overtimeReimbursements',
            ]);
        }
        $overtimeReimbursement->save();

        return response()->json(['message' => "The overtime reimbursement has been approved successfully"], 200);
    }

    public function reject(Request $request)
    {
        return $this->portalOnlyResponse('reject');

        $overtimeReimbursement = OvertimeReimbursement::find($request->id);

        $service = new FirebaseService();

        if ($overtimeReimbursement->status === 'Pending') {
            $overtimeReimbursement->status = 'Rejected Atasan';
            $overtimeReimbursement->rejected_atasan_by = $this->nama_lengkap;
            $overtimeReimbursement->rejected_atasan_at = date('Y-m-d H:i:s');
            $overtimeReimbursement->reject_atasan_reason = $request->reject_reason;

            $service->sendNotifications([$overtimeReimbursement->employee_id], [
                'title' => 'Penggantian Biaya Lembur Ditolak!',
                'body'  => 'Penggantian biaya lembur Anda telah ditolak Atasan oleh: ' . $this->nama_lengkap . ' dengan alasan: ' . $request->reject_reason,
                'url'   => '/forms/overtimeReimbursements',
            ]);
        } else if ($overtimeReimbursement->status === 'Approved Atasan') {
            $overtimeReimbursement->status = 'Rejected HRD';
            $overtimeReimbursement->rejected_hrd_by = $this->nama_lengkap;
            $overtimeReimbursement->rejected_hrd_at = date('Y-m-d H:i:s');
            $overtimeReimbursement->reject_hrd_reason = $request->reject_reason;

            $service->sendNotifications([$overtimeReimbursement->employee_id], [
                'title' => 'Penggantian Biaya Lembur Ditolak!',
                'body'  => 'Penggantian biaya lembur Anda telah ditolak HRD oleh: ' . $this->nama_lengkap . ' dengan alasan: ' . $request->reject_reason,
                'url'   => '/forms/overtimeReimbursements',
            ]);
        } else {
            $overtimeReimbursement->status = 'Rejected Finance';
            $overtimeReimbursement->rejected_finance_by = $this->nama_lengkap;
            $overtimeReimbursement->rejected_finance_at = date('Y-m-d H:i:s');
            $overtimeReimbursement->reject_finance_reason = $request->reject_reason;

            $service->sendNotifications([$overtimeReimbursement->employee_id], [
                'title' => 'Penggantian Biaya Lembur Ditolak!',
                'body'  => 'Penggantian biaya lembur Anda telah ditolak Finance oleh: ' . $this->nama_lengkap . ' dengan alasan: ' . $request->reject_reason,
                'url'   => '/forms/overtimeReimbursements',
            ]);
        }

        $overtimeReimbursement->save();

        return response()->json(['message' => "The overtime reimbursement has been rejected successfully"], 200);
    }
}
