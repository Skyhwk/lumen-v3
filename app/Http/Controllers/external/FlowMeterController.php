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
                $affected = DB::table('mesin_absen')->where('kode_mesin', $kode)->update([
                    'status_device' => $status,
                    'last_update' => $now,
                    'ipaddress' => $ip
                ]);
                
                if ($affected > 0) {
                    DB::commit();
                    Log::info("Device mesin_absen berhasil diperbarui", [
                        'kode' => $kode,
                        'status' => $status,
                        'ip' => $ip
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Berhasil memperbarui device mesin absen',
                        'data' => [
                            'kode' => $kode,
                            'status' => $status,
                            'ip_address' => $ip,
                            'last_update' => $now
                        ]
                    ], 200);
                }
            }

            // Update devices jika ada
            if (DB::table('devices')->where('kode_device', $kode)->exists()) {
                $affected = DB::table('devices')->where('kode_device', $kode)->update([
                    'status_device' => $status,
                    'last_update' => $now,
                    'ip_address' => $ip
                ]);
                
                if ($affected > 0) {
                    DB::commit();
                    Log::info("Device berhasil diperbarui", [
                        'kode' => $kode,
                        'status' => $status,
                        'ip' => $ip
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Berhasil memperbarui device',
                        'data' => [
                            'kode' => $kode,
                            'status' => $status,
                            'ip_address' => $ip,
                            'last_update' => $now
                        ]
                    ], 200);
                }
            }

            // Update DeviceIntilab jika ada
            $device = DeviceIntilab::where('kode', $kode)->first();
            if ($device) {
                $oldStatus = $device->status;
                $oldIp = $device->ip;
                
                // Update data device
                $device->status = $status;
                $device->ip = $ip;
                $device->updated_at = $now;
                $device->updated_by = "SYSTEM";
                
                if ($device->save()) {
                    // Kirim notifikasi jika status berubah
                    if ($oldStatus != $status) {
                        try {
                            $notification = app(NotificationFdlService::class)->deviceIntilab($device->id, $oldStatus, $status);
                        } catch (\Exception $e) {
                            Log::warning("Gagal mengirim notifikasi", [
                                'device_id' => $device->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    DB::commit();
                    Log::info("DeviceIntilab berhasil diperbarui", [
                        'kode' => $kode,
                        'old_status' => $oldStatus,
                        'new_status' => $status,
                        'old_ip' => $oldIp,
                        'new_ip' => $ip
                    ]);
                    
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

            // Jika tidak ditemukan di semua tabel
            DB::rollBack();
            Log::warning("Device tidak ditemukan", ['kode' => $kode]);
            
            return response()->json([
                'success' => false,
                'message' => 'Device dengan kode tersebut tidak ditemukan',
                'data' => [
                    'kode' => $kode
                ]
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error("Validasi gagal pada updateDevice", [
                'kode' => $request->kode ?? null,
                'errors' => $e->errors()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Data yang dikirim tidak valid',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $th) {
            DB::rollBack();
            Log::error("Error pada updateDevice", [
                'kode' => $request->kode ?? null,
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal server',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }
}