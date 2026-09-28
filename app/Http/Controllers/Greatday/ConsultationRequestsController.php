<?php

namespace App\Http\Controllers\Greatday;

use Illuminate\Http\Request;

use App\Services\Greatday\FirebaseService;
use App\Support\Greatday\NotificationCopy;

use App\Models\Greatday\{ConsultationRequest};
use App\Models\{MasterKaryawan};

class ConsultationRequestsController extends Controller
{
    public function index()
    {
        $employee = $this->karyawan;

        $consultationRequests = ConsultationRequest::where('is_active', true);

        if ($employee->jabatan === 'HR Counselling & Development Supervisor') {
            $consultationRequests->where(function ($query) use ($employee) {
                $query->where('employee_id', $employee->id)
                    ->orWhere('status', 'Pending');
            });
        } else {
            $consultationRequests->where('employee_id', $employee->id);
        }

        $consultationRequests = $consultationRequests
            ->latest()
            ->get()
            ->map(function ($item) {
                $employee = MasterKaryawan::find($item->employee_id);
                $item->employee_name = $employee->nama_lengkap;
                $item->employee_position = $employee->jabatan;

                return $item;
            });

        return response()->json([
            'data' => $consultationRequests,
            'message' => 'Consultation requests retrieved successfully',
        ], 200);
    }

    public function store(Request $request)
    {
        $consultationRequest = $request->id ? ConsultationRequest::find($request->id) : new ConsultationRequest();

        $consultationRequest->employee_id = $this->user_id;
        $consultationRequest->type = $request->type;
        $consultationRequest->date = $request->date;
        $consultationRequest->time = $request->time;
        $consultationRequest->description = $request->description;

        if (!$request->id) {
            $consultationRequest->created_by = $this->nama_lengkap;
            $consultationRequest->created_at = date('Y-m-d H:i:s');
        }

        $consultationRequest->updated_by = $this->nama_lengkap;
        $consultationRequest->updated_at = date('Y-m-d H:i:s');

        $consultationRequest->save();

        $service = new FirebaseService();

        $hrd = MasterKaryawan::where('jabatan', 'HR Counselling & Development Supervisor')->where('is_active', true)->first();

        $service->sendNotifications(
            [$hrd->id],
            NotificationCopy::consultationSubmitted($consultationRequest->created_by, NotificationCopy::pathForms('approval'))
        );

        return response()->json(['message' => 'Your consultation request has been submitted successfully'], 201);
    }

    public function approve(Request $request)
    {
        return $this->portalOnlyResponse('approve');

        $consultationRequest = ConsultationRequest::find($request->id);
        $consultationRequest->status = 'Approved';
        $consultationRequest->approved_by = $this->nama_lengkap;
        $consultationRequest->approved_at = date('Y-m-d H:i:s');
        $consultationRequest->save();

        $service = new FirebaseService();

        $service->sendNotifications(
            [$consultationRequest->employee_id],
            NotificationCopy::consultationApproved(NotificationCopy::pathForms('submission'))
        );

        return response()->json(['message' => "The consultation request has been approved successfully"], 200);
    }

    public function reject(Request $request)
    {
        return $this->portalOnlyResponse('reject');

        $consultationRequest = ConsultationRequest::find($request->id);
        $consultationRequest->status = 'Rejected';
        $consultationRequest->rejected_by = $this->nama_lengkap;
        $consultationRequest->rejected_at = date('Y-m-d H:i:s');
        $consultationRequest->reject_reason = $request->reject_reason;
        $consultationRequest->save();

        $service = new FirebaseService();

        $service->sendNotifications(
            [$consultationRequest->employee_id],
            NotificationCopy::consultationRejected($request->reject_reason, NotificationCopy::pathForms('submission'))
        );

        return response()->json(['message' => "The consultation request correction has been rejected successfully"], 200);
    }
}
