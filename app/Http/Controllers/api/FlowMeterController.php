<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DeviceIntilab;
use App\Models\DetailFlowMeter;
use App\Models\DataLapanganLingkunganHidup;
use App\Models\DataLapanganLingkunganKerja;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class FlowMeterController extends Controller
{
    public function index(Request $request)
    {  
        $data = DeviceIntilab::where('category', 'Flow Meter');
        
        return DataTables::of($data)->make();
    }

    public function sensorData(Request $request){
        // dd($request->all());
        DB::beginTransaction();
        try {
            $existingDevice = DeviceIntilab::where('kode', $request->kode)->first();
            if ($existingDevice) {
                return response()->json([
                    'message' => 'Device dengan kode tersebut sudah ada dengan type' . $existingDevice->category
                ], 409);
            }
            
            $device = new DeviceIntilab();
            $device->kode = $request->kode;
            $device->nama = $request->nama;
            $device->category = 'Flow Meter';
            $device->created_at = Carbon::now();
            $device->created_by = $this->karyawan;
            $device->save();

            DB::commit();
            return response()->json([
                'message' => 'Berhasil menambahkan device'
            ],201);
        }catch (\Exception $th) {
            return response()->json([
                'message' => $th->getMessage()
            ],500);
        }
    }

    public function detailDevice(Request $request)
    {
        $kode = $request->kode; // Changed from $request->id to match your frontend

        // First, get all the records for this device
        $records = DetailFlowMeter::where('id_device', $kode)
            ->select('id', 'id_device', 'no_sampel', 'flow', 'temperature', 'humidity', 'delta_p','ambient','timestamp')
            ->orderBy('timestamp', 'desc')
            ->get();
        // Group the records by no_sampel
        $groupedData = $records->groupBy('no_sampel');
        
        // Transform the grouped data into the desired format
        $result = $groupedData->map(function ($group, $no_sampel) {
            // Take the first record for the main data
            $mainRecord = $group->first();
            
            // Create a new object with the main fields
            $item = [
                'id' => $mainRecord->id,
                'id_device' => $mainRecord->id_device,
                'no_sampel' => $mainRecord->no_sampel,
                'sampel_detail' => $group->toArray() // Add all records with this no_sampel as sampel_detail
            ];
            
            return $item;
        });
        // Convert to a collection and return as DataTables response
        return DataTables::of($result)->make(true);
    }

    public function getDetailData(Request $request){
        $data = DetailFlowMeter::where('id_device', $request->kode)
            ->where('no_sampel', $request->no_sampel)
            ->orderBy('timestamp', 'desc');

        return DataTables::of($data)->make(true);
    }

    public function deleteDevice(Request $request){
        DB::beginTransaction();
        try {
            $device = DeviceIntilab::where('kode',$request->kode)->first();

            DetailFlowMeter::where('id_device', $device->kode)->delete();

            $device->delete();
            DB::commit();
            return response()->json([
                'message' => 'Berhasil menghapus data device'
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

    public function updateNoSampel(Request $request){
        DB::beginTransaction();
        try {
            DetailFlowMeter::where('id_device', $request->kode)->where('no_sampel', $request->no_sampel_lama)->update([
                'no_sampel' => $request->no_sampel
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Berhasil mengupdate data no sampel'
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

    public function deleteNoSampel(Request $request){
        DB::beginTransaction();
        try {
            DetailFlowMeter::where('id_device', $request->kode)->where('no_sampel', $request->no_sampel)->delete();
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

    public function getCategories(Request $request){
        $file = file_get_contents(public_path('device_categories.json'));
        $data = json_decode($file, true);
        
        return response()->json([
            'data' => $data
        ],200);
    }

    public function getData(Request $request){
        DB::beginTransaction();
        try {
            $data = DetailFlowMeter::where('id_device', $request->kode)
                ->where('no_sampel', $request->no_sampel)
                ->get();

            $dataArray = $data->toArray(); // ubah jadi array native

            $totalData = count($dataArray);
            $dataPerBagian = floor($totalData / 24);

            $dataPerJam = [];

            for ($jam = 0; $jam < 24; $jam++) {
                $mulaiIndex = $jam * $dataPerBagian;
                
                if ($mulaiIndex < $totalData) {
                    if ($jam == 23) {
                        // ambil semua sisa data di jam terakhir
                        $dataPerJam[$jam] = array_slice($dataArray, $mulaiIndex);
                    } else {
                        $jumlahDiambil = min($dataPerBagian, $totalData - $mulaiIndex);
                        $dataPerJam[$jam] = array_slice($dataArray, $mulaiIndex, $jumlahDiambil);
                    }
                } else {
                    $dataPerJam[$jam] = [];
                }
            }


            $hasilPerJam = [];

            foreach ($dataPerJam as $jam => $items) {
                $jumlah = count($items);
                
                if ($jumlah > 0) {
                    $totalFlow = $totalTemp = $totalHumidity = $totalDeltaP = $totalTekanan = 0;

                    foreach ($items as $item) {
                        $totalFlow += floatval($item['flow']);
                        $totalTemp += floatval($item['temperature']);
                        $totalHumidity += floatval($item['humidity']);
                        $totalDeltaP += floatval($item['delta_p']);
                        $totalTekanan += floatval($item['ambient']); // tekanan udara
                    }

                    $avg_flow = round($totalFlow / $jumlah, 2);
                    $avg_temp = round($totalTemp / $jumlah, 2);
                    $avg_humidity = round($totalHumidity / $jumlah, 2);
                    $avg_delta_p = round($totalDeltaP / $jumlah, 2);
                    $avg_pressure = round($totalTekanan / $jumlah, 2);

                    // Perhitungan rumus dari nilai rata-rata
                    $tempK = $avg_temp + 273;
                    $hasil = $avg_flow * pow((298 * $avg_delta_p) / ($tempK * 760), 0.5);
                    $rumus = round($hasil, 2); // sesuaikan dengan kebutuhan presisi

                    $hasilPerJam[$jam] = [
                        'avg_flow' => $avg_flow,
                        'avg_temperature' => $avg_temp,
                        'avg_humidity' => $avg_humidity,
                        'avg_delta_p' => $avg_delta_p,
                        'avg_pressure' => $avg_pressure,
                        'qs' => $rumus,
                        'jumlah_data' => $jumlah
                    ];
                } else {
                    $hasilPerJam[$jam] = [
                        'avg_flow' => null,
                        'avg_temperature' => null,
                        'avg_humidity' => null,
                        'avg_delta_p' => null,
                        'avg_pressure' => null,
                        'hasil_rumus' => null,
                        'qs' => null,
                        'jumlah_data' => 0
                    ];
                }
            }
            $qsArray = array_column($hasilPerJam, 'qs');
            $totalQs = array_sum($qsArray);
            $jumlahDataQs = count(array_filter($qsArray, fn($val) => $val !== null)); // Hindari null

            $rerataQs = $jumlahDataQs > 0 ? $totalQs / $jumlahDataQs : 0;

            $vstd = $rerataQs * $totalData;

            DB::commit();
            return response()->json([
                'data' => $hasilPerJam,
                'menit' => $totalData,
                'rerata_qs' => $rerataQs,
                'vstd' => $vstd
            ],200);
        }catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ],500);
        }
    }

    public function submitData(Request $request){
        DB::beginTransaction();
        try {
            $dataPendukung = (object)[
                'rerata_qs' => $request->rerata_qs,
                'durasi_menit' => $request->menit,
                'vstd' => $request->vstd
            ];
            
            $lingkunganHidup = DataLapanganLingkunganHidup::where('no_sampel', $request->no_sampel)->first();
            $lingkunganKerja = DataLapanganLingkunganKerja::where('no_sampel', $request->no_sampel)->first();

            if($lingkunganHidup || $lingkunganKerja){
                
                if($lingkunganHidup){
                    $lingkunganHidup->update([
                        'data_pendukung' => json_encode($dataPendukung)
                    ]);
                }else{
                    $lingkunganKerja->update([
                        'data_pendukung' => json_encode($dataPendukung)
                    ]);
                }
               
                DB::commit();
                return response()->json([
                    'message' => 'Data Berhasil dikalkulasi'
                ], 200);
            }else{
                DB::rollBack();
                return response()->json([
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }

    }
}