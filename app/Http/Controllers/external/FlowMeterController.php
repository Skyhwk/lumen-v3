<?php

namespace App\Http\Controllers\external;

use Laravel\Lumen\Routing\Controller as BaseController;
use App\Models\DeviceIntilab;
use App\Models\DetailFlowMeter;
use App\Services\NotificationFdlService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Log;

class FlowMeterController extends BaseController 
{
    public function sensorData(Request $request){
        switch ($request->type) {
            case 'save':
                return $this->saveSensorData($request);
                break;
            case 'update':
                return $this->updateDevice($request);
                break;
            default:
                return response()->json([
                    'message' => "Invalid type $request->type"
                ],400);
                break;
        }
    }
    
    // ** save sensor device data
    // *
    // * @param Request $request
    public function saveSensorData(Request $request){
        DB::beginTransaction();
        try {
            $cek = DeviceIntilab::where('kode', $request->kode)->first();
            if($cek){
                $detail = new DetailFlowMeter();
                $detail->id_device = $request->kode;
                $detail->timestamp = $request->timestamp;
                $detail->no_sampel = $request->no_sampel;
                $detail->flow = $request->flow;
                $detail->temperature = $request->temperature;
                $detail->humidity = $request->humidity;
                $detail->delta_p = $request->delta_p;
                $detail->ambient = $request->ambient;
                $detail->save();

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil menambahkan detail data device'
                ],200);
            }else{
                return response()->json([
                    'message' => 'Kode device tidak ditemukan'
                ],404);
            }

        }catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage()
            ],500);
        }
    }


    
    /**
     * Update device by kode
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateDevice(Request $request){

        DB::beginTransaction();
        try {
            $now = Carbon::now();
            $kode = $request->kode;
            $status = $request->status;
            $ip = $request->ip_address;
            $notification = "";

            // Update mesin_absen jika ada
            if (DB::table('mesin_absen')->where('kode_mesin', $kode)->exists()) {
                DB::table('mesin_absen')->where('kode_mesin', $kode)->update([
                    'status' => $status,
                    'last_update' => $now,
                    'ipaddress' => $ip
                ]);
                DB::commit();
                return response()->json([
                    'message' => 'Berhasil memperbarui device',
                    'status' => $status
                ], 201);
            }

            // Update devices jika ada
            if (DB::table('devices')->where('kode_device', $kode)->exists()) {
                DB::table('devices')->where('kode_device', $kode)->update([
                    'status_device' => $status,
                    'last_update' => $now,
                    'ip_address' => $ip
                ]);
                DB::commit();
                return response()->json([
                    'message' => 'Berhasil memperbarui device',
                    'status' => $status
                ], 201);
            }

            // Update DeviceIntilab jika ada
            $device = DeviceIntilab::where('kode', $kode)->first();
            if ($device) {
                $oldStatus = $device->status;
                $device->status = $status;
                $device->ip = $ip;
                $device->updated_at = $now;
                $device->updated_by = "SYSTEM";
                $device->save();

                if ($oldStatus != $status) {
                    $notification = app(NotificationFdlService::class)->deviceIntilab($device->id, $oldStatus, $status);
                }

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil memperbarui device',
                    'messageNotifikasi' => $notification,
                    'oldStatus' => $oldStatus,
                    'newStatus' => $status
                ], 201);
            }

            // Jika tidak ditemukan
            DB::rollBack();
            return response()->json([
                'message' => 'Device tidak ditemukan'
            ], 404);

        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }
}