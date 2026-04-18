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
    public function updateDevice(Request $request)
    {
        try {
            $now = Carbon::now();
            $kode = $request->kode;
            $status = $request->status;
            $ip = $request->ip_address;
            $notification = "";

            if (strpos($kode, '_') === false) {

                // mesin_absen
                $affected = DB::table('mesin_absen')
                    ->where('kode_mesin', $kode)
                    ->update([
                        'status_device' => $status,
                        'last_update' => $now,
                        'ipaddress' => $ip
                    ]);

                if ($affected > 0) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Berhasil memperbarui device mesin absen',
                        'data' => compact('kode', 'status', 'ip') + ['last_update' => $now]
                    ], 200);
                }

                // devices
                $affected = DB::table('devices')
                    ->where('kode_device', $kode)
                    ->update([
                        'status_device' => $status,
                        'last_update' => $now,
                        'ip_address' => $ip
                    ]);

                if ($affected > 0) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Berhasil memperbarui device',
                        'data' => compact('kode', 'status', 'ip') + ['last_update' => $now]
                    ], 200);
                }

                // DeviceIntilab
                $device = DeviceIntilab::where('kode', $kode)->first();

                if ($device) {
                    $oldStatus = $device->status;
                    $oldIp = $device->ip;

                    $device->update([
                        'status' => $status,
                        'ip' => $ip,
                        'updated_at' => $now,
                        'updated_by' => 'SYSTEM'
                    ]);

                    if ($oldStatus != $status) {
                        try {
                            $notification = app(NotificationFdlService::class)
                                ->deviceIntilab($device->id, $oldStatus, $status);
                        } catch (\Exception $e) {
                            Log::warning("Gagal notifikasi", [
                                'device_id' => $device->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Berhasil memperbarui device intilab',
                        'data' => [
                            'kode' => $kode,
                            'old_status' => $oldStatus,
                            'new_status' => $status,
                            'old_ip' => $oldIp,
                            'new_ip' => $ip,
                            'last_update' => $now
                        ],
                        'notification' => $notification
                    ], 200);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Device tidak ditemukan',
                'data' => ['kode' => $kode]
            ], 404);

        } catch (\Exception $e) {

            Log::error("Error updateDevice", [
                'kode' => $request->kode ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }
}