<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DeviceIntilab;
use App\Models\DetailSoundMeter;
use App\Models\KebisinganHeader;
use App\Models\WsValueUdara;
use App\Models\OrderDetail;
use App\Models\Parameter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SoundMeterController extends Controller
{
    public function index(Request $request)
    {  
        $data = DeviceIntilab::where('category', 'Sound Meter');
        
        return DataTables::of($data)->make();
    }

    public function sensorData(Request $request){
        DB::beginTransaction();
        try {
            $existingDevice = DeviceIntilab::where('kode', $request->kode)->first();
            if ($existingDevice) {
                return response()->json([
                    'message' => 'Device dengan kode tersebut sudah ada dengan type' . $existingDevice->category
                ], 409);
            }

            $device = new DeviceIntilab();
            $device->nama = $request->nama;
            $device->kode = $request->kode;
            $device->category = 'Sound Meter';
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
        $records = DetailSoundMeter::where('id_device', $kode)
            ->select('id', 'id_device', 'no_sampel', 'shift', 'LAeq', 'data_pendukung','db')
            ->orderBy('timestamp', 'desc')
            ->get();
            // ->map(function ($record) {
            //     $record->data_pendukung = json_decode($record->data_pendukung);
            //     return $record;
            // });
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
        $data = DetailSoundMeter::where('id_device', $request->kode)
            ->where('no_sampel', $request->no_sampel)
            ->orderBy('timestamp', 'desc');

        $minTimestamps = DetailSoundMeter::where('id_device', $request->kode)
            ->where('no_sampel', $request->no_sampel)
            ->select('shift', DB::raw('MIN(timestamp) as min_timestamp'))
            ->groupBy('shift')
            ->pluck('min_timestamp', 'shift');

        return DataTables::of($data)
            ->addColumn('shift_sistem', function ($row) use ($minTimestamps) {
                $minTimestamp = $minTimestamps[$row->shift] ?? null;
                if ($minTimestamp) {
                    $hour = Carbon::parse($minTimestamp)->format('H');
                    return config('shift_alat.' . $hour);
                }
                return null;
            })
            ->make(true);
    }

    public function deleteDevice(Request $request){
        DB::beginTransaction();
        try {
            $device = DeviceIntilab::where('kode',$request->kode)->first();

            DetailSoundMeter::where('id_device', $device->kode)->delete();

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
            DetailSoundMeter::where('id_device', $request->kode)->where('no_sampel', $request->no_sampel_lama)->update([
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
            DetailSoundMeter::where('id_device', $request->kode)->where('no_sampel', $request->no_sampel)->delete();
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

    public function getDataShift(Request $request){
        DB::beginTransaction();
        try {
            $records = DetailSoundMeter::where('id_device', $request->kode)
                ->where('no_sampel', $request->no_sampel)
                ->get();

            // Ambil timestamp terkecil per shift awal untuk mencari shift sistem
            $minTimestamps = $records->groupBy('shift')->map(function ($group) {
                return $group->min('timestamp');
            });

            $dataSistemShift = [];
            
            // Kelompokkan data LAeq berdasarkan shift sistem
            foreach ($records as $item) {
                $minTimestamp = $minTimestamps[$item->shift] ?? null;
                if ($minTimestamp) {
                    $hour = Carbon::parse($minTimestamp)->format('H');
                    $shiftSistem = config('shift_alat.' . $hour);
                    if ($shiftSistem) {
                        $dataSistemShift[$shiftSistem][] = (float) $item->LAeq;
                    }
                }
            }

            $data_per_shift = [];
            $allConvertValues = [];

            // Proses wajib 24 Shift (L1 - L24)
            for ($i = 1; $i <= 24; $i++) {
                $shiftName = 'L' . $i;
                $laeqValues = $dataSistemShift[$shiftName] ?? [];
                
                $count = count($laeqValues);
                $sum = array_sum($laeqValues);

                if ($count > 0) {
                    $combinedLaeq = 10 * log10((1 / $count) * $sum);
                    $convertLAeq = $combinedLaeq * 0.1;
                } else {
                    $combinedLaeq = 0;
                    $convertLAeq = 0;
                }

                $data_per_shift[] = [
                    'shift_sistem' => $shiftName,
                    'nilai_laeq' => $count > 0 ? (float) number_format($combinedLaeq, 1, '.', '') : 0,
                    'converted_laeq' => $count > 0 ? (float) number_format($convertLAeq, 2, '.', '') : 0,
                ];

                $allConvertValues[] = $count > 0 ? $convertLAeq : null;
            }

            // Hitung ls (L1-L16)
            $lsValues = array_slice($allConvertValues, 0, 16);
            $lsSum = 0;
            foreach ($lsValues as $value) {
                if ($value !== null) {
                    $lsSum += pow(10, $value);
                }
            }
            $ls = $lsSum > 0 ? 10 * log10((1/16) * $lsSum) : 0;
            
            // Hitung lm (L17-L24)
            $lmValues = array_slice($allConvertValues, 16, 8);
            $lmSum = 0;
            foreach ($lmValues as $value) {
                if ($value !== null) {
                    $lmSum += pow(10, $value);
                }
            }
            $lm = $lmSum > 0 ? 10 * log10((1/8) * $lmSum) : 0;
            
            // Hitung lsm
            if ($ls > 0 || $lm > 0) {
                $energyLs = $ls > 0 ? (16 * pow(10, 0.1 * $ls)) : 0;
                $energyLm = $lm > 0 ? (8 * pow(10, 0.1 * ($lm + 5))) : 0;
                $lsm = 10 * log10((1/24) * ($energyLs + $energyLm));
            } else {
                $lsm = 0;
            }

            DB::commit();
            return response()->json([
                'data_per_shift' => $data_per_shift,
                'ls' => $ls > 0 ? (float) number_format($ls, 2, '.', '') : 0,
                'lm' => $lm > 0 ? (float) number_format($lm, 2, '.', '') : 0,
                'lsm' => $lsm > 0 ? (float) number_format($lsm, 2, '.', '') : 0,
            ], 200);

        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ], 500);
        }
    }

    public function submitData(Request $request){
        DB::beginTransaction();
        try {
            $kebisingan_header = KebisinganHeader::where('no_sampel', $request->no_sampel)->first();
            $order_detail = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            if($order_detail){
                // Decode parameter jika dalam format JSON
                $decoded = json_decode($order_detail->parameter, true);

                $parameterValue = 'Data tidak valid';
                // Pastikan JSON ter-decode dengan benar dan berisi data
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Ambil elemen pertama dari array hasil decode
                    $parts = explode(';', $decoded[0] ?? '');

                    // Pastikan elemen kedua tersedia setelah explode
                    $parameterValue = $parts[1] ?? 'Data tidak valid';
                }

                $parameter = Parameter::where('nama_lab', $parameterValue)->where('id_kategori', 4)->where('is_active', true)->first();
                
                // HEADER
                if(!$kebisingan_header){
                    $kebisingan_header = new KebisinganHeader();
                }
                $kebisingan_header->no_sampel = $request->no_sampel;
                if ($parameter) {
                    $kebisingan_header->id_parameter = $parameter->id;
                    $kebisingan_header->parameter = $parameter->nama_lab;
                }
                $kebisingan_header->ls = $request->ls;
                $kebisingan_header->lm = $request->lm;
                $kebisingan_header->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $kebisingan_header->created_by = $this->karyawan;
                $kebisingan_header->save();
                
                // WS VALUE
                $ws_value = WsValueUdara::where('no_sampel', $request->no_sampel)->first();

                if(!$ws_value){
                    $ws_value = new WsValueUdara();
                }
                $ws_value->id_po = $order_detail->id;
                $ws_value->no_sampel = $request->no_sampel;
                $ws_value->id_kebisingan_header = $kebisingan_header->id;
                
                $ws_value->hasil1 = $request->lsm;
                
                $ws_value->save();
                DB::commit();
                return response()->json([
                    'message' => 'Data Berhasil disimpan'
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