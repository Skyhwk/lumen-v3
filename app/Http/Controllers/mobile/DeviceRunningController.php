<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use App\Models\DeviceIntilab;
use App\Models\DeviceIntilabRunning;
use App\Services\InsertActivityFdl;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class DeviceRunningController extends Controller
{
    public function checkDeviceStatus(Request $request) {
        $deviceRunning = DeviceIntilabRunning::where('is_active', true)->where('device_id', $request->device_id)->first();

        if($deviceRunning) {
            return response()->json([
                'message' => 'Device sedang digunakan oleh ' . $deviceRunning->start_by . ' untuk menjalankan nomor sampel ' . $deviceRunning->no_sampel . '. Apakah anda ingin melanjutkan? (ini akan memberhentikan running device sebelumnya)',
                'data' => $deviceRunning,
                'success' => false,
            ], 200);
        } else {
            $device = DeviceIntilab::where('id', $request->device_id)->first();
            return response()->json(['message' => 'Device tidak sedang digunakan', 'data' => $device, 'success' => true], 200);
        }
    }

    public function start(Request $request) {
        $device = DeviceIntilab::where('kode', $request->kode)->first();
        Db::beginTransaction();
        try {
            if ($device && isset($request->no_sampel)) {
                DeviceIntilabRunning::where('device_id', $device->id)
                    ->update(['is_active' => false]);

                $deviceRunning = new DeviceIntilabRunning();
                $deviceRunning->device_id = $device->id;
                $deviceRunning->type = $device->category;
                $deviceRunning->no_sampel = $request->no_sampel;
                $deviceRunning->start_at = Carbon::now();
                $deviceRunning->start_by = $this->karyawan;
                $deviceRunning->start_by_id = $this->user_id;
                $deviceRunning->is_active = true;
                $deviceRunning->save();

                InsertActivityFdl::by($this->user_id)->action('start')->target("$device->type dengan nomor sampel $request->no_sampel")->save();

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil mengubah data device'
                ],201);
            } else {
                return response()->json([
                    'message' => 'Nomor sampel atau device tidak ditemukan'
                ], 404);
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ],500);
        }
    }

    public function stop(Request $request) {
        $device = DeviceIntilab::where('kode', $request->kode)->first();
        DB::beginTransaction();
        try {
            $deviceRunning = DeviceIntilabRunning::where('device_id', $device->id)->where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            if(!$deviceRunning) {
                return response()->json([
                    'message' => $request->no_sampel . ' tidak sedang digunakan'
                ], 404);
            }
            $deviceRunning->stop_at = Carbon::now();
            $deviceRunning->stop_by = $this->karyawan;
            $deviceRunning->stop_by_id = $this->user_id;
            $deviceRunning->is_active = false;
            $deviceRunning->save();

            InsertActivityFdl::by($this->user_id)->action('stop')->target("$device->type dengan nomor sampel $request->no_sampel")->save();
            DB::commit();
            return response()->json([
                'message' => 'Berhasil mengubah data device'
            ],201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ],500);
        }
    }

    public function deviceStatus(Request $request){
        $device = DeviceIntilab::where('kode', $request->kode)->first();
        $deviceRunning = DeviceIntilabRunning::where('is_active', true)
        ->where('device_id', $device->id)
        ->where('no_sampel', $request->no_sampel)
        ->where('start_by_id', $this->user_id)
        ->where('stop_at', null)
        ->first();

        if($deviceRunning) {
            return response()->json(['data' => $deviceRunning], 200);
        } else {
            return response()->json(['message' => 'Device tidak sedang digunakan'], 400);
        }
    }

    public function getDataDevice(Request $request)
    {
        $device = DeviceIntilab::where('kode', $request->kode)->first();

        if (!$device) {
            return response()->json([
                'message' => 'Device tidak ditemukan'
            ], 404);
        }

        $device->makeHidden(['created_at', 'updated_at', 'created_by', 'updated_by']);

        return response()->json([
            'data' => $device,
            'message' => 'Berhasil mengambil data device'
        ], 200);
    }
}