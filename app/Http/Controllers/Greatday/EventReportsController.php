<?php

namespace App\Http\Controllers\Greatday;

use Illuminate\Http\Request;

use App\Models\Greatday\{EventReport};
use App\Models\{MasterKaryawan};

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
                $item->attachment = !$item->attachment ?: url('event-reports/' . $item->attachment);

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

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $destinationPath = public_path('event-reports');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            $fileName = str_replace('.', '', microtime(true)) . '.' . $file->getClientOriginalExtension();
            $file->move($destinationPath, $fileName);

            $eventReport->attachment = $fileName;
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
