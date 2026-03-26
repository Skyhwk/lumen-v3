<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MesinAbsen;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Bluerhinos\phpMQTT;

class MesinAbsenController extends Controller{
    public function index(){
        $data = MesinAbsen::with(['cabang'])->where('is_active', true)->get();

        // if($data!=null){
        //     foreach($data as $key => $val){
        //         $cek = \date_diff(date_create($val->last_update), date_create(DATE('Y-m-d H:i:s')));
        //         if($cek->s > 25){
        //             $val->status_device = 'offline';
        //             $val->save();
        //         }
        //     }
        // }

        return Datatables::of($data)->make(true);
    }

    public function showMesinAbsen(Request $request){
        $data = MesinAbsen::where('is_active', $request->is_active)->where('id', $request->id)->first();

       return Datatables::of($data)->make(true);
    }

    public function storeMesinAbsen(Request $request)
    {
        try {
            if (!empty($request->id)) {
                return Self::updateMesinAbsen($request);
            } else {
                DB::beginTransaction();
    
                $existingMesin = MesinAbsen::where('kode_mesin', $request->kode_mesin)->first();
                if ($existingMesin) {
                    return response()->json(['message' => 'Kode mesin sudah ada, gunakan kode unik', 'success' => false, 'status' => 400], 400);
                }
                $data = MesinAbsen::create([
                    'kode_mesin' => $request->kode_mesin,
                    'id_cabang' => $request->id_cabang ?? null,
                    'mode' => $request->mode ?? 'ADD',
                    'ipaddress' => $request->ipaddress ?? null,
                    'status_device' => $request->status_device ?? 'offline',
                    'is_active' => true,
                    'added_by' => $this->karyawan,
                    'added_at' => now()
                ]);
    
                DB::commit();
                return response()->json(['message' => 'Mesin absen berhasil disimpan', 'success' => true, 'status' => 200], 200);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
    
    private function send_mqtt_iot($data)
    {
        $mqtt = new phpMQTT('apps.intilab.com', '1111', 'AdminIoT');

        if ($mqtt->connect(true, null, '', '')) {
            $mqtt->publish('/intilab/iot/multidevice', $data, 0);
            $mqtt->close();

            return true;
        }

        return false;
    }

    public function updateMesinAbsen(Request $request){
        try {
            $data = MesinAbsen::with(['cabang'])->where('id', $request->id)->first();
            $data->kode_mesin = $request->kode_mesin;
            $data->id_cabang = $request->id_cabang ?? null;
            $data->updated_by = $this->karyawan;
            $data->updated_at = date('Y-m-d H:i:s');
            $data->save();
            
            if ($data) {
                return response()->json(['message' => 'Mesin absen berhasil diupdate!', 'success' => true, 'status' => 200], 200);
            } else {
                return response()->json(['message' => 'Mesin absen tidak ditemukan!', 'success' => false, 'status' => 404], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function destroyMesinAbsen(Request $request){
        try {
            $data = MesinAbsen::where('id', $request->id)->update([
                'is_active' => false,
                'deleted_by' => $this->karyawan,
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);
            
            if ($data) {
                return response()->json(['message' => 'Mesin absen berhasil dihapus!'], 200);
            } else {
                return response()->json(['message' => 'Mesin absen tidak ditemukan!'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function modeSwitcher(Request $request){
        $data = MesinAbsen::where('id', $request->id)->first();
        if ($data && $data->status_device == "online") {
            if($data->mode == "ADD"){
                $data->mode = "SCAN";
                $data->save();
                $mqttIot = $this->send_mqtt_iot(json_encode((object) [
                    'topic' => 'change_mode',
                    'device' => $data->kode_mesin,
                    'data' => "scan", // normal, open, close
                ]));
            } else {
                $data->mode = "ADD";
                $data->save();
                $mqttIot = $this->send_mqtt_iot(json_encode((object) [
                    'topic' => 'change_mode',
                    'device' => $data->kode_mesin,
                    'data' => "add", // normal, open, close
                ]));
            }
            return response()->json(['message' => 'Mode mesin absen berhasil dirubah!'], 200);
        } else {
            return response()->json(['message' => 'Mesin absen offline!'], 400);
        }
    }
}