<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Datatables;

use App\Models\Webphone;
use App\Models\LogWebphone;
use App\Models\MasterKaryawan;

class LogWebphoneController extends Controller
{
    public function index(Request $request)
    {
        $logs = [];
        $karyawan = $request->attributes->get('user')->karyawan;

        switch ($karyawan->id_jabatan) {
            case 24: // Sales Staff
                $logs = LogWebphone::with('karyawan')->where('karyawan_id', $this->user_id)->latest();

            case 21: // Sales Supervisor
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();
                array_push($bawahan, $this->user_id);

                $logs = LogWebphone::with('karyawan')->whereIn('karyawan_id', $bawahan)->latest();
                break;

            default:
                if ($karyawan->id == 1 || $karyawan->id == 127) {
                    $logs = LogWebphone::with('karyawan')->latest();
                } else if ($karyawan->id_department == 9) {
                    $logs = LogWebphone::with('karyawan')
                        ->whereHas('karyawan', fn($q) => $q->where('id_department', $karyawan->id_department))
                        ->latest();
                }
                break;
        }

        return Datatables::of($logs)
            ->addColumn('number_karyawan', function ($log) {
                if (strlen($log->number) == 4) {
                    $webphones = Webphone::with('karyawan')->where('sip_username', $log->number)->first();

                    return $webphones ? $webphones->karyawan->nama_lengkap : $log->number . ' (internal)';
                } else {
                    return $log->number;
                }
            })->make(true);
    }
}
