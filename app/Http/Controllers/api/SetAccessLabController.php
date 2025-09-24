<?php

namespace App\Http\Controllers\api;

use Datatables;
use Bluerhinos\phpMQTT;
use App\Models\Devices;
use App\Models\RfidCard;
use App\Models\AccessDoor;
use Illuminate\Http\Request;
use App\Models\MasterCabang;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;

class SetAccessLabController extends Controller
{
    public function index(Request $request)
    {
        $where = ['devices.is_active' => true, 'devices.type' => 'lab'];
        $request->id_cabang != "-1" && $where['devices.id_cabang'] = $request->id_cabang;

        $devices = Devices::with('cabang')->where($where);

        return Datatables::of($devices)->make(true);
    }

    public function getAllCabang()
    {
        $cabangs = MasterCabang::where('is_active', true)->get();

        return response()->json($cabangs, 200);
    }

    public function getDetail(Request $request)
    {
        $accessDoors = AccessDoor::with(['rfid.karyawan'])
            ->whereHas('rfid.karyawan')
            ->where('kode_mesin', $request->kode_device)
            ->get();

        return Datatables::of($accessDoors)->make(true);
    }

    public function getAllKaryawan()
    {
        // $karyawans = MasterKaryawan::with('rfid')
        //     ->where('is_active', true)
        //     ->whereHas('rfid', fn($q) => $q->where('status', 0))
        //     ->orderBy('nama_lengkap')
        //     ->get();

        $karyawans = RfidCard::with('karyawan')
            ->whereHas('karyawan', fn($q) => $q->where('is_active', true)->orderBy('nama_lengkap'))
            ->where('status', 0)
            ->get();

        return response()->json($karyawans, 200);
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

    public function save(Request $request)
    {
        try {
            $mqtt = $this->send_mqtt(json_encode((object) [
                'topic' => 'set_access',
                'device' => $request->kode_device,
                'data' => $request->karyawan,
            ]));

            if ($mqtt) {
                $accessDoor = new AccessDoor;
                $accessDoor->kode_rfid = explode("-", $request->karyawan)[0];
                $accessDoor->kode_mesin = $request->kode_device;
                $accessDoor->save();

                return response()->json(['message' => 'Saved Successfully'], 200);
            }
        } catch (\Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            $mqtt = $this->send_mqtt(json_encode((object) [
                'topic' => 'del_access',
                'device' => $request->kode_device,
                'data' => $request->karyawan,
            ]));

            if ($mqtt) {
                AccessDoor::where([
                    'kode_rfid' => explode("-", $request->karyawan)[0],
                    'kode_mesin' => $request->kode_device
                ])->delete();

                return response()->json(['message' => 'Deleted Successfully'], 200);
            }
        } catch (\Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }
}
