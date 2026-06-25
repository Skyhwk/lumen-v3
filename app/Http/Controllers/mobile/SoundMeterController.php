<?php

namespace App\Http\Controllers\mobile;


use App\Models\DataLapanganKebisinganBySoundMeter;
use App\Models\CatatanKebisinganSoundMeter;

use App\Models\DetailSoundMeter;

use App\Http\Controllers\Controller;
use App\Models\DeviceIntilab;
use App\Models\DeviceIntilabRunning;
use App\Models\OrderDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SoundMeterController extends Controller
{
    public function addKebisinganByFlowMeter(Request $request)
    {
        DB::beginTransaction();
        try {    

            $data = new DataLapanganKebisinganBySoundMeter();

            $data->no_sampel = strtoupper(trim($request->no_sample));

            if ($request->keterangan_4) {
                $data->keterangan = $request->keterangan_4;
            }

            if ($request->information) {
                $data->informasi_tambahan = $request->information;
            }

            if ($request->posisi) {
                $data->titik_koordinat = $request->posisi;
            }

            if ($request->lat) {
                $data->latitude = $request->lat;
            }

            if ($request->longi) {
                $data->longitude = $request->longi;
            }

            if ($request->jen_frek) {
                $data->jenis_frekuensi_kebisingan = $request->jen_frek;
            }

            if ($request->waktu) {
                $data->waktu = $request->waktu;
            }

            if ($request->sumber_keb) {
                $data->sumber_kebisingan = $request->sumber_keb;
            }

            if ($request->jenis_kat) {
                $data->jenis_kategori_kebisingan = $request->jenis_kat;
            }

            if ($request->suhu_udara) {
                $data->suhu_udara = $request->suhu_udara;
            }

            if ($request->kelembapan_udara) {
                $data->kelembapan_udara = $request->kelembapan_udara;
            }

            // Penambahan jam pemaparan
            if ($request->jam_pemaparan !== null || $request->menit_pemaparan !== null) {
                $jam = (int) $request->jam_pemaparan;
                $menit = (int) $request->menit_pemaparan;

                // Bikin Carbon dari 00:00 terus set jam & menit
                $data->jam_pemaparan = Carbon::createFromTime(0, 0)->setHours($jam)->setMinutes($menit)->format('H:i');
            }

            if ($request->permis) {
                $data->permission = $request->permis;
            }

            if ($request->foto_lok) {
                $data->foto_lokasi_sampel = self::convertImg($request->foto_lok, 1, $this->user_id);
            }

            if ($request->foto_lain) {
                $data->foto_lain = self::convertImg($request->foto_lain, 3, $this->user_id);
            }

            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            if($request->catatan){
                CatatanKebisinganSoundMeter::create([
                    'id_kebisingan' => $data->id,
                    'catatan' => $request->catatan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s')
                ]);
            }

            $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('is_active', 1)->first();

            if($orderDetail->tanggal_terima == null){
                $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d');
                $orderDetail->save();
            }

            $nama = $this->karyawan;
            $this->resultx = "Data Sampling KEBISINGAN Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine()
            ]);
        }
    }

    public function getKondisiLapangan(Request $request)
    {
        if ($request->menu === 'soundMeter') {
            $sevenDaysAgo = Carbon::now()->subDays(7);

            $data = DetailSoundMeter::select('no_sampel', DB::raw('MAX(timestamp) as max_timestamp'))
                ->where('timestamp', '>=', $sevenDaysAgo)
                ->groupBy('no_sampel');

            return Datatables::of($data)->make(true);
        }

        return response()->json([
            'message' => 'Data Tidak Ditemukan'
        ], 404);
    }

    public function viewKondisiLapangan(Request $request)
    {
        if ($request->menu === 'soundMeter') {

            $data = DataLapanganKebisinganBySoundMeter::where('no_sampel', $request->no_sampel)->get();

            return response()->json([
            'message' => 'Success Get Data',
            'data' => $data
        ], 200);
        }

        return response()->json([
            'message' => 'Data Tidak Ditemukan'
        ], 404);
    }

    public function checkDevice(Request $request) {
        if (isset($request->kode) && $request->kode != null) {
            $data = DeviceIntilab::where('kode', $request->kode)->first();
            if($data){
                return response()->json([
                    'message' => 'Device Exist',
                    'success' => true
                ], 200);
            } else {
                return response()->json([
                    'message' => "Upss.. 🙊 Kode alat $request->kode yang anda masukkan belum terdaftar di sistem",
                    'success' => false
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'theres no device code u are inputing',
                'success' => false
            ], 401);
        }
    }

    public function checkFdlFlowMeter(Request $request)
    {
        if(isset($request->no_sampel) && $request->no_sampel != null){
            $data = DataLapanganKebisinganBySoundMeter::where('no_sampel', $request->no_sampel)->first();

            if($data){
                return response()->json([
                    'message' => 'Data Exist',
                    'exist' => true
                ], 200);
            } else {
                return response()->json([
                    'message' => "Data not Exist",
                    'exist' => false
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Anda Belum memasukkan nomor sample',
                'success' => false
            ], 401);
        }
    }

    public function AddCatatanSoundMeter(Request $request)
    {
        if(isset($request->no_sampel) && $request->no_sampel != null){
            $data = DataLapanganKebisinganBySoundMeter::where('no_sampel', $request->no_sampel)->first();

            if($data){
                CatatanKebisinganSoundMeter::create([
                    'id_kebisingan' => $data->id,
                    'catatan' => $request->catatan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s')
                ]);

                return response()->json([
                    'message' => "Success Add Catatan",
                ], 200);
            } else {
                return response()->json([
                    'message' => "Data dengan nomor sample tidak ada",
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Anda Belum memasukkan nomor sample',
                'success' => false
            ], 401);
        }
    }

    private function getKondisiLapanganItems($data)
    {
        if (!$data || !$data->kondisi_lapangan_json) {
            return [];
        }

        $items = json_decode($data->kondisi_lapangan_json, true);

        return is_array($items) ? $items : [];
    }

    private function findOrCreateDataLapanganSoundMeter($noSampel)
    {
        $noSampel = strtoupper(trim($noSampel));

        $data = DataLapanganKebisinganBySoundMeter::where('no_sampel', $noSampel)->first();

        if (!$data) {
            $data = new DataLapanganKebisinganBySoundMeter();
            $data->no_sampel = $noSampel;
            $data->kondisi_lapangan_json = json_encode([]);
            $data->mqtt_status = 'waiting';
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();
        }

        return $data;
    }

    private function saveMonitoringKondisiPhotos(Request $request, $noSampel)
    {
        $files = $request->file('photos', []);

        if (!is_array($files)) {
            $files = [$files];
        }

        $destinationPath = public_path() . '/dokumentasi/sampling/';
        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0775, true);
        }

        $savedFiles = [];
        $safeSample = preg_replace('/[^A-Za-z0-9_-]/', '_', strtoupper(trim($noSampel)));

        foreach ($files as $index => $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                $extension = 'jpg';
            }

            $fileName = date('YmdHis') . '_' . $safeSample . '_monitoring_' . ($index + 1) . '_' . uniqid() . '.' . $extension;
            $file->move($destinationPath, $fileName);

            $savedFiles[] = [
                'id' => uniqid('foto_', true),
                'name' => $file->getClientOriginalName(),
                'file' => $fileName,
                'path' => 'dokumentasi/sampling/' . $fileName,
            ];
        }

        return $savedFiles;
    }

    public function getMonitoringKondisiLapangan(Request $request)
    {
        if (!$request->no_sampel) {
            return response()->json([
                'message' => 'Nomor sampel wajib diisi'
            ], 422);
        }

        try {
            $data = $this->findOrCreateDataLapanganSoundMeter($request->no_sampel);

            return response()->json([
                'message' => 'Success Get Data',
                'data' => [
                    'id' => $data->id,
                    'no_sampel' => $data->no_sampel,
                    'kondisi_lapangan_json' => $this->getKondisiLapanganItems($data),
                    'mqtt_status' => $data->mqtt_status ?? 'waiting',
                    'last_mqtt_message_at' => $data->last_mqtt_message_at,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function appendMonitoringKondisiLapangan(Request $request)
    {
        if (!$request->no_sampel) {
            return response()->json([
                'message' => 'Nomor sampel wajib diisi'
            ], 422);
        }

        if (!$request->event) {
            return response()->json([
                'message' => 'Event kondisi lapangan wajib diisi'
            ], 422);
        }

        $eventPayload = is_array($request->event) ? $request->event : json_decode($request->event, true);

        if (!is_array($eventPayload)) {
            return response()->json([
                'message' => 'Format event kondisi lapangan tidak valid'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $this->findOrCreateDataLapanganSoundMeter($request->no_sampel);
            $items = $this->getKondisiLapanganItems($data);
            $event = $eventPayload;

            if (!isset($event['created_at']) || $event['created_at'] == null) {
                $event['created_at'] = Carbon::now()->format('Y-m-d H:i:s');
            }

            if (!isset($event['created_by']) || $event['created_by'] == null) {
                $event['created_by'] = $this->karyawan;
            }

            $uploadedPhotos = $this->saveMonitoringKondisiPhotos($request, $data->no_sampel);
            if (count($uploadedPhotos) > 0) {
                $event['photos'] = $uploadedPhotos;
            }

            $items[] = $event;

            $data->kondisi_lapangan_json = json_encode($items);
            $data->mqtt_status = $request->mqtt_status ?? $data->mqtt_status ?? 'waiting';
            $data->last_mqtt_message_at = $request->last_mqtt_message_at ?? $data->last_mqtt_message_at;
            $data->updated_by = $this->karyawan;
            $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Kondisi lapangan berhasil disimpan',
                'data' => [
                    'id' => $data->id,
                    'no_sampel' => $data->no_sampel,
                    'kondisi_lapangan_json' => $items,
                    'mqtt_status' => $data->mqtt_status,
                    'last_mqtt_message_at' => $data->last_mqtt_message_at,
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function updateMonitoringMqttStatus(Request $request)
    {
        if (!$request->no_sampel) {
            return response()->json([
                'message' => 'Nomor sampel wajib diisi'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $this->findOrCreateDataLapanganSoundMeter($request->no_sampel);
            $data->mqtt_status = $request->mqtt_status ?? 'waiting';
            $data->last_mqtt_message_at = $request->last_mqtt_message_at ?? $data->last_mqtt_message_at;
            $data->updated_by = $this->karyawan;
            $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Status MQTT berhasil diupdate',
                'data' => [
                    'no_sampel' => $data->no_sampel,
                    'mqtt_status' => $data->mqtt_status,
                    'last_mqtt_message_at' => $data->last_mqtt_message_at,
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
    public function getDeviceRunning(Request $request){
        $devices = DeviceIntilabRunning::where('is_active', true)->where('type', 'Sound Meter')->where('start_by', $this->karyawan)->get();
        
        return response()->json(['data' => $devices], 200);
    }
}

