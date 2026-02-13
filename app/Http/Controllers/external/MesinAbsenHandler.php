<?php

namespace App\Http\Controllers\external;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use App\Models\MesinAbsen;
use App\Models\LogDoor;
use App\Models\RfidCard;
use App\Models\Absensi;
use Bluerhinos\phpMQTT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendMqttAccess;

class MesinAbsenHandler extends BaseController
{
    public function index(Request $request)
    {
        if($request->token == 'intilab_jaya'){
            if($request->mode == 'check'){
                $cekDevice = MesinAbsen::where('kode_mesin', $request->device)->first();
                $absen = '';
                $status = 'offline';
                if($request->absen!=''){

                    $waktu = \explode("T", $request->absen);
                    $absen = DATE('Y-m-d H:i:s', \strtotime($waktu[0]." ".$waktu[1]));
                   
                    $status = 'online';
                }
    
                if($cekDevice != null){
                    if($request->ip!='')$cekDevice->ipaddress = $request->ip;
                    if($request->absen!='')$cekDevice->status_device = $status;
                    if($request->absen!='')$cekDevice->last_update = $absen;
                    $cekDevice->save();
    
                    return response()->json([
                        'message'=>'success',
                    ], 200);
                } else {
                    return response()->json([
                        'message'=>'success',
                    ], 200);
                }
                
            } else if ($request->mode == "sync"){
                $cekDevice = MesinAbsen::leftJoin('master_cabang', 'mesin_absen.id_cabang', '=', 'master_cabang.id')
                ->where('kode_mesin', $request->device)
                ->select('master_cabang.nama_cabang')
                ->first();
                
                $akses = DB::table('master_karyawan as A')
                ->join('rfid_card as B', 'A.id', '=', 'B.userid')
                ->where('A.is_active', 1)
                ->selectRaw('CONCAT(B.kode_kartu, "-", A.nama_lengkap) as akses')
                ->pluck('akses');

                return response()->json([
                    'message' => 'data user',
                    'data' => $akses,
                    'cabang' => $cekDevice->nama_cabang
                ], 200);
            } else {
                
                $ipAddress = $request->header('X-Forwarded-For') ?? $request->ip();
                $cekDevice = MesinAbsen::leftJoin('master_cabang', 'mesin_absen.id_cabang', '=', 'master_cabang.id')->where('kode_mesin', $request->device)->first();
                $cekDevice->last_update = date('Y-m-d H:i:s');
                $cekDevice->status_device = 'online';
                $cekDevice->ipaddress = $ipAddress;
                $cekDevice->save();

                if($cekDevice != null && $cekDevice->mode == 'SCAN'){
                    if($request->rfid != NULL){
                        // $cekKarywan = RfidCard::leftJoin('master_karyawan', 'rfid_card.userid', '=', 'master_karyawan.id')
                        // ->select('rfid_card.userid', 'master_karyawan.nama_lengkap',)
                        // ->where('kode_kartu', $request->rfid)->where('rfid_card.status', 0)->first();
                        $cekKarywan = RfidCard::with('karyawan')
                        ->where('kode_kartu', $request->rfid)->where('rfid_card.status', 0)->first();
                        
                        if($cekKarywan != null && $cekKarywan->karyawan != null){
                            if($request->absen != null){
                                
                                $waktu = \explode("T", $request->absen);
                                $shift = "Masuk";
                                $absen = DATE('Y-m-d H:i:s', \strtotime($waktu[0]." ".$waktu[1]));
                                $jam = DATE('H:i:s', \strtotime($waktu[1]));
                                

                                if($jam > '14:00:00')$shift = "Keluar";

                                $cekShift = DB::table('shift_karyawan')->where('tanggal', $waktu[0])->where('karyawan_id', $cekKarywan->karyawan->id)->first();
                                if($cekShift != null){
                                    if($cekShift->shift == 'SHSECURITY2'){
                                        $shift = 'Keluar';
                                        if($jam >= '18:00:00' )$shift = "Masuk";
                                    }
                                    if($cekShift->shift == 'off'){
                                        $minus =  DATE('Y-m-d', strtotime($waktu[0]. '-1day'));
                                        $cekShift_ = DB::table('shift_karyawan')->where('tanggal', $minus)->where('userid', $cekKarywan->karyawan->id)->first();
                                        
                                        if($cekShift_->shift == 'SHSECURITY2'){
                                            if($jam <= '10:00:00' )$shift = "Keluar";
                                        } else {
                                            $shift = 'Masuk';
                                            if($jam >= '14:00:00' )$shift = "Masuk";
                                        }
                                    }
                                }
                                
                                
                                $insert = new Absensi;
                                $insert->karyawan_id = $cekKarywan->karyawan->id;
                                $insert->kode_mesin = $request->device;
                                $insert->kode_kartu = $request->rfid;
                                // $insert->absen = $absen;
                                
                                $insert->hari = self::hari($waktu[0]);
                                $insert->tanggal = $waktu[0];
                                $insert->jam = $jam;
                                
                                $insert->status = $shift;

                                $insert->save();
                                
                                $ket = $cekKarywan->karyawan->nama_lengkap;
                                $status = $shift;
                                
                            } else {
                                $ket = "Absen";
                                $status = "Kosong";
                            }

                            // Notif::send($cekKarywan->userid, null, null, $status, 'Monitor', 'reload_absen');

                            return response()->json([
                                'mode'=> $cekDevice->mode,
                                'ket'=> $ket,
                                'status' => $status,
                                'cabang' => $cekDevice->nama_cabang
                            ], 200);

                        } else {
                            return response()->json([
                                'mode'=> $cekDevice->mode,
                                'ket'=> 'Kartu',
                                'status' => 'Tidak Terdaftar',
                                'cabang' => $cekDevice->nama_cabang
                            ], 200);
                        }

                    } else {
                        return response()->json([
                            'mode'=>$cekDevice->mode,
                            'ket'=> 'Absensi',
                            'status' => 'success',
                            'cabang' => $cekDevice->nama_cabang
                        ], 200);
                    }
                } else if($cekDevice != null && $cekDevice->mode == 'ADD'){

                    $cekKartu = RfidCard::where('kode_kartu', $request->rfid)->where('status', 0)->first();
                    if($cekKartu!=null){
                        return response()->json([
                            'mode'=>$cekDevice->mode,
                            'ket'=> $request->rfid,
                            'status' => 'Already Added',
                            'cabang' => $cekDevice->nama_cabang
                        ], 200);
                    } else {
                        $addKartu = new RfidCard;
                        $addKartu->kode_kartu = $request->rfid;
                        $addKartu->add_at = DATE('Y-m-d H:i:s');
                        $addKartu->save();

                        return response()->json([
                            'mode'=>$cekDevice->mode,
                            'ket'=> $request->rfid,
                            'status' => 'Success Added',
                            'cabang' => $cekDevice->nama_cabang
                        ], 200);
                    }
                    

                } else {
                    return response()->json([
                        'ket'=> 'Mesin Absen',
                        'status' => 'Tidak Terdaftar',
                    ], 200);
                }
            }
        } else {
            return response()->json([
                'mode'=>'SCAN',
                'ket'=> 'Accesss',
                'status' => 'ditolak',
            ], 200);
        }
    }

    public function hari($tanggal){
        $hari = date ("D", strtotime($tanggal));
     
        switch($hari){
            case 'Sun':
                $hari_ini = "Minggu";
            break;
     
            case 'Mon':			
                $hari_ini = "Senin";
            break;
     
            case 'Tue':
                $hari_ini = "Selasa";
            break;
     
            case 'Wed':
                $hari_ini = "Rabu";
            break;
     
            case 'Thu':
                $hari_ini = "Kamis";
            break;
     
            case 'Fri':
                $hari_ini = "Jumat";
            break;
     
            case 'Sat':
                $hari_ini = "Sabtu";
            break;
            
            default:
                $hari_ini = "Tidak di ketahui";		
            break;
        }
     
        return $hari_ini;
     
    }

    public function Sync(Request $request)
    {
        $data = [];
        try {
            //code...
            if($request->token == 'intilab_jaya'){
                if($request->mode == 'sync'){
                    $mesinAbsen = MesinAbsen::where('kode_mesin', $request->device)->first();
                    if($mesinAbsen == null){
                        $data = DB::table('access_door')
                        ->join('rfid_card', 'access_door.kode_rfid', '=', 'rfid_card.kode_kartu')
                        ->join('master_karyawan', 'rfid_card.userid', '=', 'master_karyawan.id')
                        ->where('kode_mesin', $request->device)
                        ->select(DB::raw('CONCAT(rfid_card.kode_kartu, "-", master_karyawan.nama_lengkap) as akses'))
                        ->get()
                        ->pluck('akses')
                        ->implode('|');
                        
                        $devices = DB::table('devices')
                        ->where('kode_device', $request->device)
                        ->first();

                        $nameDevice = $devices->nama_device;
                        $mode = $devices->mode;

                        $job = new SendMqttAccess($data, $request->device);
                        dispatch($job);
                    } else {
                        if ($mesinAbsen->id_cabang == 1) {
                            $nameDevice = 'HEAD OFFICE';
                        } elseif ($mesinAbsen->id_cabang == 4) {
                            $nameDevice = 'RO-KARAWANG';
                        } else {
                            $nameDevice = 'RO-PEMALANG';
                        }

                        $mode = $mesinAbsen->mode;
                        $data = DB::table('master_karyawan')
                        ->join('rfid_card', 'master_karyawan.id', '=', 'rfid_card.userid')
                        ->where('master_karyawan.is_active', 1)
                        ->where('master_karyawan.id_cabang', $mesinAbsen->id_cabang)
                        ->select(DB::raw('CONCAT(rfid_card.kode_kartu, "-", master_karyawan.nama_lengkap) as akses'))
                        ->get()
                        ->pluck('akses')
                        ->implode('|');
                        
                        $job = new SendMqttAccess($data, $request->device);
                        dispatch($job);
                    }
        
                    return response()->json([
                        'nameDevice' => $nameDevice,
                        'mode' => $mode,
                    ], 200);
                }
            }
            
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json('Error : ' . $th->getMessage(), 200);
        }
    }

    public function IotSync(Request $request)
    {
        try {
            if($request->token == 'intilab_jaya'){
                if($request->mode == 'sync'){
                    $deviceCode = $request->device;
                    
                    $mesinAbsen = MesinAbsen::where('kode_mesin', $deviceCode)->first();
                    
                    if($mesinAbsen == null){
                        // Mode Access Door
                        $data = DB::table('access_door')
                            ->join('rfid_card', 'access_door.kode_rfid', '=', 'rfid_card.kode_kartu')
                            ->join('master_karyawan', 'rfid_card.userid', '=', 'master_karyawan.id')
                            ->where('kode_mesin', $deviceCode)
                            ->select(
                                'master_karyawan.id as employee_id',
                                'rfid_card.kode_kartu as rfid',
                                'master_karyawan.nama_lengkap as full_name'
                            )
                            ->get();
                        
                        $devices = DB::table('devices')
                            ->where('kode_device', $deviceCode)
                            ->first();

                        $nameDevice = $devices->nama_device ?? 'Unknown Device';
                        $mode = $devices->mode ?? 'normal';
                    } else {
                        // Mode Attendance
                        if ($mesinAbsen->id_cabang == 1) {
                            $nameDevice = 'HEAD OFFICE';
                        } elseif ($mesinAbsen->id_cabang == 4) {
                            $nameDevice = 'RO-KARAWANG';
                        } else {
                            $nameDevice = 'RO-PEMALANG';
                        }

                        $mode = $mesinAbsen->mode ?? 'scan';
                        
                        $data = DB::table('master_karyawan')
                            ->join('rfid_card', 'master_karyawan.id', '=', 'rfid_card.userid')
                            ->where('master_karyawan.is_active', 1)
                            ->where('master_karyawan.id_cabang', $mesinAbsen->id_cabang)
                            ->select(
                                'master_karyawan.id as employee_id',
                                'rfid_card.kode_kartu as rfid',
                                'master_karyawan.nama_lengkap as full_name'
                            )
                            ->get();
                    }

                    // DEBUG: Log jumlah data
                    \Log::info("Device: {$deviceCode}, Data count: " . count($data));
                    
                    // Cek jika data kosong
                    if (count($data) == 0) {
                        \Log::warning("No data found for device: {$deviceCode}");
                    }

                    // Path folder per device
                    $deviceFolder = public_path('iot/' . $deviceCode);
                    
                    // Pastikan folder device ada
                    if (!file_exists($deviceFolder)) {
                        mkdir($deviceFolder, 0755, true);
                        \Log::info("Created folder: {$deviceFolder}");
                    }
                    
                    // File selalu bernama "access.bin"
                    $filepath = $deviceFolder . '/access.bin';
                    
                    // Generate binary file
                    $result = $this->generateAccessBin($data, $filepath);
                    
        
                    return response()->json([
                        'nameDevice' => $nameDevice,
                        'mode' => $mode,
                        'path' => "http://apps.intilab.com/v3/public/iot/" . $deviceCode . "/access.bin",
                        'records' => count($data),
                        'filesize' => file_exists($filepath) ? filesize($filepath) : 0
                    ], 200);
                }
            }
            
            return response()->json('Invalid request', 400);
            
        } catch (\Throwable $th) {
            \Log::error("IotSync Error: " . $th->getMessage());
            \Log::error($th->getTraceAsString());
            return response()->json('Error : ' . $th->getMessage(), 500);
        }
    }

    /**
     * Generate binary file access.bin sesuai format ESP32
     */
    private function generateAccessBin($data, $filepath)
    {
        try {
            // Hapus file lama jika ada
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            $handle = fopen($filepath, 'wb');
            
            if (!$handle) {
                \Log::error("Cannot create file: " . $filepath);
                throw new \Exception("Cannot create file: " . $filepath);
            }
            
            $recordCount = 0;
            
            foreach ($data as $record) {
                // Pastikan data tidak null
                $empId = isset($record->employee_id) ? (string)$record->employee_id : '';
                $rfidCode = isset($record->rfid) ? (string)$record->rfid : '';
                $name = isset($record->full_name) ? (string)$record->full_name : '';
                
                // DEBUG: Log setiap record
                \Log::debug("Record: EmpID={$empId}, RFID={$rfidCode}, Name={$name}");
                
                // employee_id - 16 bytes
                $employeeId = str_pad(substr($empId, 0, 15), 16, "\0");
                fwrite($handle, $employeeId);
                
                // rfid - 16 bytes
                $rfid = str_pad(substr($rfidCode, 0, 15), 16, "\0");
                fwrite($handle, $rfid);
                
                // full_name - 32 bytes
                $fullName = str_pad(substr($name, 0, 31), 32, "\0");
                fwrite($handle, $fullName);
                
                $recordCount++;
            }
            
            fclose($handle);
            
            // Verifikasi file
            $filesize = file_exists($filepath) ? filesize($filepath) : 0;
            $expectedSize = $recordCount * 64;
            
            \Log::info("Generated access.bin: {$filepath}");
            \Log::info("Records: {$recordCount}, Size: {$filesize} bytes, Expected: {$expectedSize} bytes");
            
            if ($filesize != $expectedSize) {
                \Log::warning("File size mismatch! Expected: {$expectedSize}, Got: {$filesize}");
            }
            
            return true;
            
        } catch (\Exception $e) {
            \Log::error("generateAccessBin Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function handleMultiDevice(Request $request)
    {
        switch ($request->handle) {
            case 'handle_mesin_absensi':
                $deviceId = $request->deviceId;
                if($request->mode == 'SCAN'){
                    //
                    $shift = "Masuk";
                    $tanggal = \explode("T", $request->data['datetime'])[0];
                    $jam = \explode("T", $request->data['datetime'])[1];
                    
                    if($jam > '14:00:00')$shift = "Keluar";
                    $karyawan = DB::table('rfid_card')->join('master_karyawan', 'rfid_card.userid', '=', 'master_karyawan.id')->select('master_karyawan.nama_lengkap', 'master_karyawan.id')->where('kode_kartu', $request->data['rfid'])->first();
                    $cekShift = DB::table('shift_karyawan')->where('tanggal', $tanggal)->where('karyawan_id', $karyawan->id)->first();

                    if($cekShift != null){
                        if($cekShift->shift == 'SHSECURITY2'){
                            $shift = 'Keluar';
                            if($jam >= '18:00:00' )$shift = "Masuk";
                        }
                        if($cekShift->shift == 'off'){
                            $minus =  DATE('Y-m-d', strtotime($tanggal. '-1day'));
                            $cekShift_ = DB::table('shift_karyawan')->where('tanggal', $minus)->where('userid', $karyawan->id)->first();
                            
                            if($cekShift_->shift == 'SHSECURITY2'){
                                if($jam <= '10:00:00' )$shift = "Keluar";
                            } else {
                                $shift = 'Masuk';
                                if($jam >= '14:00:00' )$shift = "Masuk";
                            }
                        }
                    }

                    DB::beginTransaction();
                    try {
                        $insert = new Absensi;
                        $insert->karyawan_id = $karyawan->id;
                        $insert->kode_mesin = $request->deviceId;
                        $insert->kode_kartu = $request->data['rfid'];
                        $insert->hari = self::hari($tanggal);
                        $insert->tanggal = $tanggal;
                        $insert->jam = $jam;
                        $insert->status = $shift;
                        $insert->save();
                        
                        DB::commit();

                        $return = [
                            'topic' => 'response',
                            'device' => $request->deviceId,
                            'data' => $karyawan->nama_lengkap . '-' . $shift,
                        ];

                        $this->send_mqtt(json_encode($return));
                    } catch (\Throwable $th) {
                        //throw $th;
                        DB::rollBack();
                        Log::error(['Error : ' . $th->getMessage(), $th->getLine(), $th->getFile()]);
                    }

                } else if($request->mode == 'ADD'){
                    $cekKartu = RfidCard::where('kode_kartu', $request->data['rfid'])->where('status', 0)->first();
                    
                    if($cekKartu!=null){
                        $return = [
                            'topic' => 'response',
                            'device' => $request->deviceId,
                            'data' => $request->data['rfid'] . '-' . 'Already Added',
                        ];
                        
                        $this->send_mqtt(json_encode($return));
                    } else {
                        $addKartu = new RfidCard;
                        $addKartu->kode_kartu = $request->rfid;
                        $addKartu->add_at = DATE('Y-m-d H:i:s');
                        $addKartu->save();

                        $return = [
                            'topic' => 'response',
                            'device' => $request->deviceId,
                            'data' => $request->data['rfid'] . '-' . 'Success Added',
                        ];

                        $this->send_mqtt(json_encode($return));
                    }
                }
                break;
            case 'handle_mesin_akses_pintu':
                // insert data akses
                $userid = DB::table('rfid_card')->where('kode_kartu', $request->data['rfid'])->first()->userid;
                DB::beginTransaction();
                try {
                    $insert = new LogDoor;
                    $insert->kode_mesin = $request->deviceId;
                    $insert->kode_rfid = $request->data['rfid'];
                    $insert->tanggal = \explode("T", $request->data['datetime'])[0];
                    $insert->jam = \explode("T", $request->data['datetime'])[1];
                    $insert->userid = $userid;
                    $insert->status = $request->data['status'];

                    $insert->save();
                    DB::commit();
                    
                    return response()->json([
                        'message' => 'success',
                    ], 200);
                } catch (\Throwable $th) {
                    DB::rollBack();
                    Log::error('Error : ' . $th->getMessage());
                }

                break;
        }
    }

    private function send_mqtt($data)
    {
        $mqtt = new phpMQTT('apps.intilab.com', '1111', 'AdminIoT');
        if ($mqtt->connect(true, null, '', '')) {
            $mqtt->publish('/intilab/iot/multidevice', $data, 0);
            $mqtt->close();

            return true;
        }

        return false;
    }
}
