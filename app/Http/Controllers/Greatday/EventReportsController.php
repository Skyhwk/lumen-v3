<?php

namespace App\Http\Controllers\Greatday;

use App\Models\Greatday\EventReport;
use App\Models\MasterKaryawan;
use App\Services\Hr\HrFormAttachmentStorage;
use App\Support\Greatday\GreatdayAssetPaths;
use Illuminate\Http\Request;

class EventReportsController extends Controller
{
    public function index()
    {
        $employee = $this->karyawan;

        $eventReports = EventReport::where('is_active', true)
            ->where('employee_id', $employee->id)
            ->latest()
            ->get()
            ->map(function ($item) {
                $employee = MasterKaryawan::find($item->employee_id);
                $item->employee_name = $employee->nama_lengkap;
                $item->employee_position = $employee->jabatan;
                $attachments = HrFormAttachmentStorage::resolvePublicUrls(
                    $item->attachment,
                    GreatdayAssetPaths::KEY_LAPORAN_KEGIATAN
                );
                $item->attachments = $attachments;
                $item->attachment = $attachments[0] ?? null;

                return $item;
            });

        return response()->json([
            'data' => $eventReports,
            'message' => 'Attendance corrections retrieved successfully',
        ], 200);
    }

    public function store(Request $request)
    {
        $eventReport = $request->id ? EventReport::find($request->id) : new EventReport();

        $eventReport->employee_id = $this->user_id;
        $eventReport->subject = $request->subject;
        $eventReport->date = $request->date;
        $eventReport->time = $request->time;
        $eventReport->description = $request->description;

        $storedAttachments = HrFormAttachmentStorage::storeImages(
            HrFormAttachmentStorage::collectUploadedImages($request),
            GreatdayAssetPaths::KEY_LAPORAN_KEGIATAN
        );
        if ($storedAttachments !== null) {
            $eventReport->attachment = $storedAttachments;
        }

        if (!$request->id) {
            $eventReport->created_by = $this->nama_lengkap;
            $eventReport->created_at = date('Y-m-d H:i:s');
        }

        $eventReport->updated_by = $this->nama_lengkap;
        $eventReport->updated_at = date('Y-m-d H:i:s');

        $eventReport->save();

        return response()->json(['message' => "Your attendance correction has been submitted successfully"], 201);
    }
}
