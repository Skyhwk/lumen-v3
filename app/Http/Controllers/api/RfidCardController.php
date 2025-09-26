<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\RfidCard;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Models\AccessDoor;

use Bluerhinos\phpMQTT;

class RfidCardController extends Controller
{
    // Tested - Clear
    public function index()
    {
        $data = RfidCard::with(['karyawan'])->where('status', 0);

        return Datatables::of($data)->make(true);
    }

    // Tested - Clear
    public function showRfid(Request $request)
    {
        $data = RfidCard::with(['karyawan'])->where('id', $request->id)->first();

        return Datatables::of($data)->make(true);
    }

    // Tested - Clear
    public function destroyRfid(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = RfidCard::where('id', $request->id)->where('status', 0)->update([
                'status' => 1,
            ]);

            if ($data) {
                DB::commit();
                return response()->json(['message' => 'Rfid berhasil dihapus!'], 200);
            } else {
                DB::rollBack();
                return response()->json(['message' => 'Rfid tidak ditemukan!'], 404);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    // Tested - Clear
    public function linkRfid(Request $request)
    {
        DB::beginTransaction();
        try {
            $karyawan = MasterKaryawan::where('id', $request->userid)->first();
            if (!$karyawan) {
                DB::rollBack();
                return response()->json(['message' => 'Karyawan tidak ditemukan!'], 404);
            }

            RfidCard::where('id', $request->id)->where('status', 0)->update(['userid' => $request->userid]);
            DB::commit();
            return response()->json(['message' => 'Rfid berhasil link dengan ' . $karyawan->nama_lengkap . '!'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    // Tested - Clear
    public function unlinkRfid(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = RfidCard::with(['karyawan'])->where('id', $request->id)->where('status', 0)->first();

            if ($data && $data->karyawan) {
                $user = $data->karyawan->nama_lengkap;

                $data->userid = null;
                $data->save();

                $this->deleteAccessDevices($data->rfid . "-" . $user);
                DB::commit();
                return response()->json(['message' => 'Rfid berhasil unlink dari ' . $user . '!'], 200);
            } else {
                DB::rollBack();
                return response()->json(['message' => 'Rfid atau Karyawan tidak ditemukan!'], 404);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
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

    private function deleteAccessDevices($data) // kode_rfid-nama_lengkap
    {
        $accessDoors = AccessDoor::with('device')->where('kode_rfid', explode("-", $data)[0])->get();

        $batchIds = [];
        foreach ($accessDoors as $item) {
            if ($item->device->status_device == 'online') {
                $mqtt = $this->send_mqtt(json_encode((object) [
                    'topic' => 'del_access',
                    'device' => $item->device->kode_device,
                    'data' => $data,
                ]));

                if ($mqtt) $batchIds[] = $item->id;
            }
        }

        $deleted = AccessDoor::whereIn('id', $batchIds)->delete();
        if ($deleted) return true;

        return response()->json(['message' => 'Terjadi kesalahan!'], 500);
    }
}
