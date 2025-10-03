<?php

namespace App\Http\Controllers\external;

use Laravel\Lumen\Routing\Controller as BaseController;
use App\Models\DeviceIntilab;
use App\Models\DetailSoundMeter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SoundMeterController extends BaseController 
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
                    'message' => 'Invalid type'
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
                $detail = new DetailSoundMeter();
                $detail->id_device = $request->kode;
                $detail->timestamp = $request->timestamp;
                $detail->no_sampel = $request->no_sampel;
                $detail->shift = $request->shift;
                $detail->LAeq = $request->LAeq;
                $detail->db = $request->db;
                $detail->data_pendukung = !empty($request->data_pendukung) ? $request->data_pendukung : null;
                $detail->save();
                
                DB::commit();
                return response()->json([
                    'message' => 'Berhasil menambahkan detail data device'
                ],200);
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
            $device = DeviceIntilab::where('kode', $request->kode)->first();
            if($device){   
                $device->status = $request->status;
                $device->ip = $request->ip_address;
                $device->updated_at = Carbon::now();
                $device->updated_by = "SYSTEM";
                $device->save();
                
                DB::commit();
                return response()->json([
                    'message' => 'Berhasil memperbarui device'
                ],201);
            }
        }catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage()
            ],500);
        }
    }

    public function deleteNoSampel(Request $request){
        DB::beginTransaction();
        try {
            DetailSoundMeter::where('id_device', $request->kode)->where('no_sampel', $request->no_sampel)->delete();
            DB::commit();
            return response()->json([
                'message' => 'Berhasil menghapus data no sampel'
            ],201);
        }catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ],500);
        }
    }
}