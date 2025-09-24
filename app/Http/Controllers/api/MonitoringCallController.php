<?php

namespace App\Http\Controllers\api;

use App\Models\LogSIP;
use App\Models\AccountSIP;
use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;

class MonitoringCallController extends Controller
{
    // public function getSales(Request $request)
    // {
    //     $karyawan = $request->attributes->get('user')->karyawan;

    //     $accountSIP = AccountSIP::with(['logSIP' => fn($q) => $q->latest()->first()]);

    //     if (isset($karyawan->id_jabatan) && $karyawan->id_jabatan == 24) { // sales staff
    //         $accountSIP = $accountSIP->where('id_user', $this->user_id)->limit(1);
    //     } elseif (isset($karyawan->id_jabatan) && $karyawan->id_jabatan == 21) { // sales spv
    //         $bawahan = MasterKaryawan::where('is_active', 1)
    //             ->whereJsonContains('atasan_langsung', (string) $this->user_id)
    //             ->pluck('id')->toArray();
    //         array_push($bawahan, $this->user_id);
    //         $accountSIP = $accountSIP->whereIn('id_user', $bawahan);
    //     }

    //     $data = $accountSIP->get()->map(function ($item) {
    //         $item->karyawan = MasterKaryawan::where('is_active', 1)->find($item->id_user);
    //         return $item->karyawan ? $item : null;
    //     })->filter()->values();

    //     return response()->json(['data' => $data, 'success' => true], 200);
    // }

    public function getSales(Request $request)
    {
        $karyawan = $request->attributes->get('user')->karyawan;

        $accountSIP = AccountSIP::with(['latestLogSIP']);

        if (isset($karyawan->id_jabatan) && $karyawan->id_jabatan == 24) { // Sales Staff
            $accountSIP->where('id_user', $this->user_id)->limit(1);
        } elseif (isset($karyawan->id_jabatan) && $karyawan->id_jabatan == 21) { // Sales SPV
            $bawahan = MasterKaryawan::where('is_active', 1)
                ->whereJsonContains('atasan_langsung', (string) $this->user_id)
                ->pluck('id')->toArray();
            array_push($bawahan, $this->user_id);
            $accountSIP->whereIn('id_user', $bawahan);
        }

        $data = $accountSIP->get()->map(function ($item) {
            $item->karyawan = MasterKaryawan::where('is_active', 1)->find($item->id_user);

            $item->log_s_i_p = [0 => $item->latestLogSIP];
            unset($item->latestLogSIP);

            return $item->karyawan ? $item : null;
        })->filter()->values();

        $data = $data->map(function ($item) {
            if (is_null($item->karyawan->image)) $item->karyawan->image = 'no_image_2.jpg';
            return $item;
        })->values();

        return response()->json(['data' => $data, 'success' => true], 200);
    }

    public function getDetail(Request $request)
    {
        $data = LogSIP::where('from', $request->username)->latest()->get();

        return response()->json(['data' => $data, 'success' => true], 200);
    }
}
