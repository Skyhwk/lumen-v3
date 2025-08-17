<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\MasterKaryawan;
use App\Models\Webphone;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\Datatables\Datatables;
use App\Services\Crypto;
use Carbon\Carbon;
use Bluerhinos\phpMQTT;


class WebphoneController extends Controller
{
    public function indexWebphone(Request $request)
    {
        $data = Webphone::with('karyawan')->where('karyawan_id', $request->karyawan_id)
            ->get();

        return Datatables::of($data)->make(true);
    }

    public function storeWebphone(Request $request)
    {
        try {
            $encryptor = new Crypto;
            $store = Webphone::updateOrCreate(
                ['karyawan_id' => $request->karyawan_id],
                [
                    'sip_username' => !empty($request->sip_username) ? $request->sip_username : null,
                    'sip_password' => !empty($request->sip_password) ? $encryptor->encrypt($request->sip_password) : null
                ]
            );

            return response()->json([
                'message' => 'Data berhasil disimpan.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function setLogWebphone(Request $request)
    {
        if ($request->karyawan_id) {
            try {
                $exists = DB::table('log_webphone')
                    ->where('call_id', $request->call_id)
                    ->exists();

                $data = [
                    'karyawan_id' => $request->karyawan_id,
                    'number' => $request->number,
                    'tunnel' => ($request->tunnel == "outgoing" || $request->tunnel == "incoming") ? $request->tunnel : null,
                    'status_call' => $request->status_call ?? null,
                    'time' => $request->time ?? null,
                ];

                if (!$exists) {
                    $data['created_at'] = Carbon::now()->format('Y-m-d H:i:s');
                }

                DB::table('log_webphone')->updateOrInsert(
                    ['call_id' => $request->call_id],
                    $data
                );

                if (!$this->sendSocket($data)) {
                    Log::error('Gagal mengirim data ke socket: ' . json_encode($data));
                }

                return response()->json(['message' => 'Log berhasil disimpan.'], 200);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
            }
        } else {
            try {
                $exists = DB::table('log_webphone')
                    ->where('call_id', $request->call_id)
                    ->exists();

                $status_call = $request->status_call ?? null;
                $ended_by = $request->ended_by ?? null;

                // Daftar status generik yang butuh detail tambahan
                $generic_statuses = ['Normal Call clearing', 'ended', 'berakhir'];

                if (in_array($status_call, $generic_statuses) && $ended_by) {
                    if ($ended_by === 'us' || $ended_by === 'local') {
                        $status_call .= ' (ended by user)';
                    } elseif ($ended_by === 'them' || $ended_by === 'remote') {
                        $status_call .= ' (ended by client)';
                    }
                }

                $data = [
                    'karyawan_id' => Webphone::where('sip_username', $request->sip_username)->first()->karyawan_id,
                    'number' => $request->number,
                    'tunnel' => $request->tunnel == 'outbond' ? 'outgoing' : 'incoming',
                    'status_call' => $status_call,
                    'time' => $request->time ?? '',
                ];

                if (!$exists) {
                    $data['created_at'] = Carbon::now()->format('Y-m-d H:i:s');
                }

                DB::table('log_webphone')->updateOrInsert(
                    ['call_id' => $request->call_id],
                    $data
                );

                if (!$this->sendSocket($data)) {
                    Log::error('Gagal mengirim data ke socket: ' . json_encode($data));
                }

                return response()->json(['message' => 'Log berhasil disimpan.'], 200);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
            }
        }
    }
    // public function setLogWebphone(Request $request){
    //     try {
    //         $exists = DB::table('log_webphone')
    //             ->where('call_id', $request->call_id)
    //             ->exists();

    //         $data = [
    //             'karyawan_id' => $request->karyawan_id,
    //             'number' => $request->number,
    //             'tunnel' => ($request->tunnel == "outgoing" || $request->tunnel == "incoming") ? $request->tunnel : null,
    //             'status_call' => $request->status_call ?? null,
    //             'time' => $request->time ?? null,
    //         ];

    //         if (!$exists) {
    //             $data['created_at'] = Carbon::now()->format('Y-m-d H:i:s');
    //         }

    //         DB::table('log_webphone')->updateOrInsert(
    //             ['call_id' => $request->call_id],
    //             $data
    //         );

    //         if(!$this->sendSocket($data)){
    //             Log::error('Gagal mengirim data ke socket: ' . json_encode($data));
    //         }

    //         return response()->json(['message' => 'Log berhasil disimpan.'], 200);
    //     } catch (\Exception $e) {
    //         return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
    //     }
    // }

    private function sendSocket($dataArray = [])
    {
        $host = env('MQTT_HOST');
        $port = env('MQTT_PORT');
        $clientID = env('MQTT_USERNAME');
        $username = env('MQTT_USERNAME');
        $password = env('MQTT_PASSWORD');
        $mqtt = new phpMQTT($host, $port, $clientID);

        $dataString = json_encode($dataArray);
        try {
            if ($mqtt->connect(true, null, $username, $password)) {
                $mqtt->publish('/journal', $dataString, 0);
                $mqtt->close();
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            dump($e);
            return false;
        }
    }
}
