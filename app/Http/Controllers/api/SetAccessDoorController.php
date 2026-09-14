<?php

namespace App\Http\Controllers\api;

use Datatables;
use Bluerhinos\phpMQTT as MqttClient;
use App\Models\Devices;
use App\Models\RfidCard;
use App\Models\AccessDoor;
use Illuminate\Http\Request;
use App\Models\MasterCabang;
use App\Models\MasterDivisi;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;

class SetAccessDoorController extends Controller
{
    public function index(Request $request)
    {
        $where = ['devices.is_active' => true];
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
            ->where('kode_mesin', $request->kode_device);

        return Datatables::of($accessDoors)->make(true);
    }

    public function getAllDivision()
    {
        $divisions = MasterDivisi::where('is_active', true)
            ->orderBy('nama_divisi')
            ->get();

        return response()->json($divisions, 200);
    }

    public function getAllKaryawan()
    {
        $karyawans = RfidCard::with('karyawan')
            ->whereHas('karyawan', fn($q) => $q->where('is_active', true)->orderBy('nama_lengkap'))
            ->where('status', 0)
            ->get();

        return response()->json($karyawans, 200);
    }

    public function indexByKaryawan(Request $request)
    {
        $query = MasterKaryawan::query()
            ->select([
                'master_karyawan.id',
                'master_karyawan.nik_karyawan',
                'master_karyawan.nama_lengkap',
                'master_karyawan.id_cabang',
                'master_divisi.nama_divisi',
            ])
            ->selectRaw('COUNT(DISTINCT rfid_card.id) as total_kartu')
            ->selectRaw('COUNT(DISTINCT CASE WHEN devices.kode_device IS NOT NULL AND devices.nama_device IS NOT NULL THEN access_door.id END) as total_akses')
            ->join('rfid_card', function ($join) {
                $join->on('master_karyawan.id', '=', 'rfid_card.userid')
                    ->where('rfid_card.status', 0);
            })
            ->leftJoin('access_door', 'rfid_card.kode_kartu', '=', 'access_door.kode_rfid')
            ->leftJoin('devices', function ($join) {
                $join->on('access_door.kode_mesin', '=', 'devices.kode_device')
                    ->whereNotNull('devices.kode_device')
                    ->whereNotNull('devices.nama_device');
            })
            ->leftJoin('master_divisi', 'master_karyawan.id_department', '=', 'master_divisi.id')
            ->where('master_karyawan.is_active', true)
            ->groupBy(
                'master_karyawan.id',
                'master_karyawan.nik_karyawan',
                'master_karyawan.nama_lengkap',
                'master_karyawan.id_cabang',
                'master_divisi.nama_divisi'
            );

        $request->id_cabang != "-1" && $query->where('master_karyawan.id_cabang', $request->id_cabang);

        return Datatables::of($query)->make(true);
    }

    public function getDetailByKaryawan(Request $request)
    {
        $kodeKartus = RfidCard::where('userid', $request->userid)
            ->where('status', 0)
            ->pluck('kode_kartu');

        $accessDoors = AccessDoor::with(['device', 'rfid.karyawan'])
            ->whereIn('kode_rfid', $kodeKartus)
            ->whereHas('device', function ($query) {
                $query->whereNotNull('kode_device')
                    ->whereNotNull('nama_device');
            });

        return Datatables::of($accessDoors)
            ->filterColumn('rfid.kode_kartu', function ($query, $keyword) {
                $keywords = array_values(array_filter(array_map('trim', explode('|', $keyword))));

                $query->where(function ($subQuery) use ($keywords) {
                    foreach ($keywords as $word) {
                        if ($word === '') {
                            continue;
                        }

                        $subQuery->orWhere('access_door.kode_rfid', 'like', "%{$word}%");

                        if (ctype_digit($word)) {
                            $hex = strtoupper(dechex((int) $word));
                            $subQuery->orWhere('access_door.kode_rfid', 'like', "%{$hex}%");
                        } elseif (ctype_xdigit($word)) {
                            $subQuery->orWhere('access_door.kode_rfid', 'like', "%" . strtoupper($word) . "%");
                        }
                    }
                });
            })
            ->make(true);
    }

    public function getCardsByKaryawan(Request $request)
    {
        $cards = RfidCard::where('userid', $request->userid)
            ->where('status', 0)
            ->orderBy('kode_kartu')
            ->get(['id', 'kode_kartu']);

        return response()->json($cards, 200);
    }

    public function getExistingAccessByKaryawan(Request $request)
    {
        $kodeKartus = RfidCard::where('userid', $request->userid)
            ->where('status', 0)
            ->pluck('kode_kartu');

        $accessDoors = AccessDoor::whereIn('kode_rfid', $kodeKartus)
            ->whereHas('device', function ($query) {
                $query->whereNotNull('kode_device')
                    ->whereNotNull('nama_device');
            })
            ->get(['kode_rfid', 'kode_mesin']);

        return response()->json(
            $accessDoors->map(fn ($item) => [
                'kode_kartu' => $item->kode_rfid,
                'kode_device' => $item->kode_mesin,
            ])->values(),
            200
        );
    }

    public function getAllDevices(Request $request)
    {
        $where = ['devices.is_active' => true];
        $request->id_cabang != "-1" && $where['devices.id_cabang'] = $request->id_cabang;

        $devices = Devices::where($where)
            ->orderBy('nama_device')
            ->get();

        return response()->json($devices, 200);
    }

    public function clearAccessByKaryawan(Request $request)
    {
        try {
            if ($invalidPassword = $this->validateAccessDoorPassword($request)) {
                return $invalidPassword;
            }

            $cards = RfidCard::with('karyawan')
                ->where('userid', $request->userid)
                ->where('status', 0)
                ->get();

            if ($cards->isEmpty()) {
                return response()->json(['message' => 'Karyawan tidak memiliki kartu RFID aktif'], 404);
            }

            $kodeKartus = $cards->pluck('kode_kartu');
            $hasOfflineDevice = AccessDoor::with('device')
                ->whereIn('kode_rfid', $kodeKartus)
                ->whereHas('device')
                ->get()
                ->contains(fn ($accessDoor) => ($accessDoor->device->status_device ?? '') !== 'online');

            if ($hasOfflineDevice) {
                return response()->json(['message' => 'Tidak dapat clear akses karena masih ada device offline.'], 422);
            }

            foreach ($cards as $card) {
                $namaLengkap = $card->karyawan->nama_lengkap ?? '';
                $karyawanValue = "{$card->kode_kartu}-{$namaLengkap}";
                $accessDoors = AccessDoor::where('kode_rfid', $card->kode_kartu)->get();

                foreach ($accessDoors as $accessDoor) {
                    AccessDoor::where([
                        'kode_rfid' => $card->kode_kartu,
                        'kode_mesin' => $accessDoor->kode_mesin,
                    ])->delete();

                    $this->send_mqtt(json_encode((object) [
                        'topic' => 'del_access',
                        'device' => $accessDoor->kode_mesin,
                        'data' => $karyawanValue,
                    ]));

                    $this->newMethod(json_encode((object) [
                        'topic' => 'delete_user',
                        'device' => $accessDoor->kode_mesin,
                        'data' => $karyawanValue,
                    ]));

                    usleep(150000);
                }
            }

            return response()->json(['message' => 'Semua akses karyawan berhasil dihapus'], 200);
        } catch (\Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }

    private function validateAccessDoorPassword(Request $request)
    {
        if ($request->confirm_password !== '78baLitni89') {
            return response()->json(['message' => 'Password tidak valid'], 403);
        }

        return null;
    }

    private function send_mqtt($data)
    {
        $mqtt = new MqttClient('apps.intilab.com', '1883', 'Admin');

        if ($mqtt->connect(true, null, '', '')) {
            $mqtt->publish('/intilab/resource/set-manage', $data, 0);
            $mqtt->close();

            return true;
        }

        return false;
    }

    private function newMethod($data)
    {
        $mqtt = new MqttClient('apps.intilab.com', '1111', 'Admin');

        if ($mqtt->connect(true, null, '', '')) {
            $mqtt->publish('/intilab/iot/multidevice', $data, 0);
            $mqtt->close();

            return true;
        }

        return false;
    }

    public function save(Request $request)
    {
        try {
            if ($request->kode_device) {
                if (is_array($request->karyawan)) {
                    foreach ($request->karyawan as $karyawan) {
                        $kodeRfid = explode("-", $karyawan)[0];

                        if (AccessDoor::where([
                            'kode_rfid' => $kodeRfid,
                            'kode_mesin' => $request->kode_device,
                        ])->exists()) {
                            continue;
                        }

                        $mqtt = $this->send_mqtt(json_encode((object) [
                            'topic' => 'set_access',
                            'device' => $request->kode_device,
                            'data' => $karyawan,
                        ]));

                        $accessDoor = new AccessDoor;
                        $accessDoor->kode_rfid = $kodeRfid;
                        $accessDoor->kode_mesin = $request->kode_device;
                        $accessDoor->save();

                        $new = $this->newMethod(json_encode((object) [
                            'topic' => 'add_user',
                            'device' => $request->kode_device,
                            'data' => $karyawan,
                        ]));
                        usleep(150000); // delay 150ms
                    }

                    return response()->json(['message' => 'Saved Successfully'], 200);
                } else {
                    $kodeRfid = explode("-", $request->karyawan)[0];

                    if (AccessDoor::where([
                        'kode_rfid' => $kodeRfid,
                        'kode_mesin' => $request->kode_device,
                    ])->exists()) {
                        return response()->json(['message' => 'Akses pintu sudah terdaftar'], 422);
                    }

                    $mqtt = $this->send_mqtt(json_encode((object) [
                        'topic' => 'set_access',
                        'device' => $request->kode_device,
                        'data' => $request->karyawan,
                    ]));

                    $accessDoor = new AccessDoor;
                    $accessDoor->kode_rfid = $kodeRfid;
                    $accessDoor->kode_mesin = $request->kode_device;
                    $accessDoor->save();

                    $new = $this->newMethod(json_encode((object) [
                        'topic' => 'add_user',
                        'device' => $request->kode_device,
                        'data' => $request->karyawan,
                    ]));
    
                    return response()->json(['message' => 'Saved Successfully'], 200);
                }
            }
        } catch (\Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            $deleted = AccessDoor::where([
                'kode_rfid' => explode("-", $request->karyawan)[0],
                'kode_mesin' => $request->kode_device,
            ])->delete();

            if (!$deleted) {
                return response()->json(['message' => 'Access not found'], 404);
            }

            $this->send_mqtt(json_encode((object) [
                'topic' => 'del_access',
                'device' => $request->kode_device,
                'data' => $request->karyawan,
            ]));

            $this->newMethod(json_encode((object) [
                'topic' => 'delete_user',
                'device' => $request->kode_device,
                'data' => $request->karyawan,
            ]));

            return response()->json(['message' => 'Deleted Successfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            if ($request->has('confirm_password')) {
                if ($invalidPassword = $this->validateAccessDoorPassword($request)) {
                    return $invalidPassword;
                }
            }

            foreach ($request->selectedEmployees as $item) {
                AccessDoor::where([
                    'kode_rfid' => explode("-", $item['karyawan'])[0],
                    'kode_mesin' => $item['kode_device'],
                ])->delete();

                $this->send_mqtt(json_encode((object) [
                    'topic' => 'del_access',
                    'device' => $item['kode_device'],
                    'data' => $item['karyawan'],
                ]));

                $this->newMethod(json_encode((object) [
                    'topic' => 'delete_user',
                    'device' => $item['kode_device'],
                    'data' => $item['karyawan'],
                ]));
                usleep(150000); // delay 150ms
            }

            return response()->json(['message' => 'Deleted Successfully'], 200);
        } catch (\Exception $ex) {
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }
}
