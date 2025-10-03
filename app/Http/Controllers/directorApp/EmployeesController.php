<?php

namespace App\Http\Controllers\directorApp;

use Laravel\Lumen\Routing\Controller;

use Illuminate\Http\Request;

use App\Models\{
    MasterKaryawan,
    MasterDivisi,
    MasterJabatan,
    LogDoor,
    Devices
};

class EmployeesController extends Controller
{
    public function getEmployees(Request $request)
    {
        $employees = MasterKaryawan::with('jabatan', 'salary', 'bpjsKesehatan', 'bpjsTk', 'pph21', 'loan', 'denda')
            ->where('is_active', true)
            ->where('id', '!=', 1);

        if ($request->status) $employees = $employees->where('status_karyawan', $request->status);
        if ($request->position) $employees = $employees->where('id_jabatan', $request->position);
        if ($request->department) $employees = $employees->where('id_department', $request->department);

        if ($request->searchTerm) {
            $employees = $employees->where('nama_lengkap', 'like', '%' . $request->searchTerm . '%')
                ->orWhere('department', 'like', '%' . $request->searchTerm . '%')
                ->orWhereHas('jabatan', function ($jabatan) use ($request) {
                    $jabatan->where('nama_jabatan', 'like', '%' . $request->searchTerm . '%');
                });
        }

        $employees = $employees->orderByDesc('id')->paginate(30);

        return response()->json([
            'message' => 'Employees data retrieved successfully',
            'data' => $employees
        ], 200);
    }

    public function getEmployeeFilterParams()
    {
        return response()->json([
            'message' => 'Employee filter params retrieved successfully',
            'data' => [
                'statuses' => MasterKaryawan::select('status_karyawan')
                    ->distinct()
                    ->where('status_karyawan', '!=', null)
                    ->pluck('status_karyawan')
                    ->toArray(),
                'departments' => MasterDivisi::select('id', 'nama_divisi')
                    ->orderBy('nama_divisi')
                    ->where('is_active', true)
                    ->get(),
                'positions' => MasterJabatan::select('id', 'nama_jabatan')
                    ->orderBy('nama_jabatan')
                    ->where('is_active', true)
                    ->get(),
            ],
        ], 200);
    }

    public function getAccessDoors(Request $request)
    {
        $logs = LogDoor::with(['karyawan', 'device'])
            ->whereNotNull('userid')
            ->whereRaw('MONTH(tanggal) = MONTH(NOW())')
            ->orderByDesc('id');

        if ($request->searchTerm) {
            $logs = $logs->where(function ($query) use ($request) {
                $query->whereHas('device', function ($device) use ($request) {
                    $device->where('nama_device', 'like', '%' . $request->searchTerm . '%');
                })->orWhereHas('karyawan', function ($karyawan) use ($request) {
                    $karyawan->where('nama_lengkap', 'like', '%' . $request->searchTerm . '%');
                });
            });
        }

        $logs = $logs->paginate(50);

        return response()->json([
            'message' => 'Access Doors data retrieved successfully',
            'data' => $logs
        ], 200);
    }

    public function getAccesslogFilterParams()
    {
        return response()->json([
            'message' => 'Access logs filter params retrieved successfully',
            'data' => [
                'devices' => Devices::select('id', 'nama_device')
                    ->orderBy('nama_device')
                    ->where('is_active', true)
                    ->get(),
                'employees' => MasterKaryawan::select('id', 'nama_lengkap')
                    ->where('is_active', true)
                    ->orderBy('nama_lengkap')
                    ->get(),
            ],
        ], 200);
    }
}
