<?php

namespace App\Http\Controllers\api;

use Datatables;
use Bluerhinos\phpMQTT;
use App\Models\Devices;
use App\Models\AccessDoor;
use Illuminate\Http\Request;
use App\Models\MasterCabang;
use App\Http\Controllers\Controller;

class DevicesController extends Controller
{
    public function index(Request $request)
    {
        $where = ['devices.is_active' => true];
        $request->cabang && $where['devices.id_cabang'] = $request->id_cabang;

        $devices = Devices::with('cabang')->where($where);

        return Datatables::of($devices)->make(true);
    }

    public function getAllCabang()
    {
        $cabangs = MasterCabang::where('is_active', true)->get();

        return response()->json($cabangs, 200);
    }

    private function send_mqtt($data)
    {
        $mqtt = new phpMQTT('apps.intilab.com', '1883', 'Admin');

        if ($mqtt->connect(true, null, '', '')) {
            $mqtt->publish('/intilab/resource/set-manage', $data, 0);
            $mqtt->close();

            return true;
        }

        return false;
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

    public function save(Request $request)
    {
        $oldDevice = null;
        if ($request->id) {
            $oldDevice = Devices::where('id', $request->id)->first();
        } else {
            $data['created_at'] = date("Y-m-d H:i:s");
            $data['last_update'] = date("Y-m-d H:i:s");
        };

        if ($request->id_cabang) $data['id_cabang'] = $request->id_cabang;
        if ($request->kode_device) $data['kode_device'] = $request->kode_device;
        if ($request->nama_device) $data['nama_device'] = $request->nama_device;
        if ($request->status_device) $data['status_device'] = $request->status_device;
        if ($request->ip_address) $data['ip_address'] = $request->ip_address;
        if ($request->type) $data['type'] = $request->type;
        if ($request->mode) $data['mode'] = $request->mode;

        $mqtt = $this->send_mqtt(json_encode((object) [
            'topic' => 'change_mode',
            'device' => $oldDevice ? $oldDevice->kode_device : $request->kode_device,
            'data' => $request->mode, // normal, open, close
        ]));

        $mqttIot = $this->send_mqtt_iot(json_encode((object) [
            'topic' => 'change_mode',
            'device' => $oldDevice ? $oldDevice->kode_device : $request->kode_device,
            'data' => $request->mode, // normal, open, close
        ]));

        if($mqtt){
            Devices::updateOrCreate(['id' => $request->id], $data);
    
            // update access door
            foreach (AccessDoor::where('kode_mesin', $oldDevice->kode_device)->get() as $accessDoor) {
                $accessDoor->kode_mesin = $request->kode_device;
                $accessDoor->save();
            };
        }

        return response()->json(['message' => 'Saved Successfully'], 200);
    }

    public function destroy(Request $request)
    {
        $device = Devices::where('id', $request->id)->first();

        $device->last_update = date("Y-m-d H:i:s");
        $device->is_active = false;
        $device->save();

        return response()->json(['message' => 'Deleted Successfully'], 200);
    }
}
