<?php

namespace App\Http\Controllers\mobile;


use App\Models\DataLapanganKebisinganBySoundMeter;
use App\Models\CatatanKebisinganSoundMeter;

use App\Models\DetailSoundMeter;

use App\Http\Controllers\Controller;
use App\Models\DeviceIntilab;
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

            $update = DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            $nama = $this->karyawan;
            $this->resultx = "Data Sampling KEBISINGAN Dengan No Sample $request->no_sample berhasil disimpan oleh $nama";

            DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e.getMessage(),
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
}