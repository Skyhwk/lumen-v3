<?php

namespace App\Http\Controllers\api;

use Datatables;
use App\Models\LogDoor;
use App\Http\Controllers\Controller;

class MonitoringDoorController extends Controller
{
    public function index()
    {
        $logs = LogDoor::with(['karyawan', 'device'])->whereNotNull('userid')->whereRaw('MONTH(tanggal) = MONTH(NOW())')->orderBy('id', 'DESC')->limit(50);

        return Datatables::of($logs)
        ->filterColumn('karyawan.nik_karyawan', function ($query, $keyword) {
            $query->whereHas('karyawan', function ($q) use ($keyword) {
                $q->where('nik_karyawan', 'like', "%{$keyword}%");
            });
        })
        ->filterColumn('karyawan.nama_lengkap', function ($query, $keyword) {
            $query->whereHas('karyawan', function ($q) use ($keyword) {
                $q->where('nama_lengkap', 'like', "%{$keyword}%");
            });
        })
        ->filterColumn('karyawan.department', function ($query, $keyword) {
            $query->whereHas('karyawan', function ($q) use ($keyword) {
                $q->where('department', 'like', "%{$keyword}%");
            });
        })
        ->filterColumn('device.nama_device', function ($query, $keyword) {
            $query->whereHas('device', function ($q) use ($keyword) {
                $q->where('nama_device', 'like', "%{$keyword}%");
            });
        })
        ->make(true);
    }
}
