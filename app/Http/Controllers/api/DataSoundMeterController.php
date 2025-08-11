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

class DataSoundMeterController extends Controller
{
    public function index()
    {
        $records = DetailSoundMeter::with('device')
            ->orderBy('timestamp', 'desc')
            ->get();

        // Group the records by no_sampel
        $groupedData = $records->groupBy('no_sampel');

        // Transform the grouped data into the desired format
        $result = $groupedData->map(function ($group, $no_sampel) {
            $mainRecord = $group->first();

            $item = [
                'id' => $mainRecord->id,
                'nama'=> optional($mainRecord->device)->nama,
                'kode'=> optional($mainRecord->device)->kode,
                'id_device' => $mainRecord->id_device,
                'no_sampel' => $mainRecord->no_sampel,
                // 'sampel_detail' => $group->toArray()
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

        return DataTables::of($data)->make(true);
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
            $shift = DetailSoundMeter::where('id_device', $request->kode)->where('no_sampel', $request->no_sampel)
                ->get();
            
            $dataShift = [];
            $shift->each(function($item) use (&$dataShift) {
                $dataShift[$item->shift][] = (object)[
                    'db' => $item->db,
                    'laeq' => $item->LAeq
                ];
            });

            // Sort the keys naturally to ensure L1, L2, L3... L24 ordering
            uksort($dataShift, function($a, $b) {
                // Extract the numeric part from the shift value (L1, L2, etc.)
                $numA = (int) substr($a, 1);
                $numB = (int) substr($b, 1);
                
                return $numA - $numB;
            });

            $formattedHasil = 0;
            $shifSummary = [];
            $L8 = [];
            if(count($dataShift) > 23) { // shift 24 jam
                foreach ($dataShift as $shiftName => $items) {
                    // Ambil semua nilai laeq
                    $laeqValues = array_map(function($item) {
                        return (float) $item->laeq;
                    }, $items);
    
                    $count = count($laeqValues);
                    $sum = array_sum($laeqValues);
    
                    // Hitung combined LAeq jika data ada, jika tidak hasilkan null
                    $combinedLaeq = $count > 0 ? number_format((10 * log10((1 / $count) * $sum)), 1) : null;
                    $convertLAeq = $combinedLaeq ? number_format($combinedLaeq * 0.1, 2) : null; // combinedLaeq * 0.1
                    $hasilConvert = $convertLAeq !== null
                        ? number_format(1 * pow(10, $convertLAeq), 2)
                        : null; // 1*(10^convertLAeq)
                    // Tambahkan hasil ke dalam array
                    $shifSummary[$shiftName] = [
                        'total_count' => $count,
                        'hasil_laeq' => $combinedLaeq,
                        'convert_laeq' => $convertLAeq,
                        'hasil_convert' => $hasilConvert
                    ];
                }
            // Persiapkan array nilai convert_laeq untuk semua shift (L1-L24)
                $allConvertValues = [];
                foreach ($shifSummary as $shiftName => $data) {
                    $allConvertValues[] = $data['convert_laeq'];
                }
                
                // Pastikan array terurut dari L1-L24
                ksort($shifSummary, SORT_NATURAL);
                
                // Hitung ls (L1-L16)
                $lsValues = array_slice($allConvertValues, 0, 16);
                $lsSum = 0;
                foreach ($lsValues as $value) {
                    if ($value !== null) {
                        $lsSum += pow(10, $value);
                    }
                }
                $ls = $lsSum > 0 ? 10 * log10((1/16) * $lsSum) : null;
                
                // Hitung lm (L17-L24)
                $lmValues = array_slice($allConvertValues, 16, 8);
                $lmSum = 0;
                foreach ($lmValues as $value) {
                    if ($value !== null) {
                        $lmSum += pow(10, $value);
                    }
                }
                $lm = $lmSum > 0 ? 10 * log10((1/8) * $lmSum) : null;
                
                // Hitung lsm
                // lsm = 10*LOG((1/24)*((16*(10^(0,1*ls)))+(8*(10^(0,1*(lm+5))))))
                if ($ls !== null && $lm !== null) {
                    $lsm = 10 * log10((1/24) * ((16 * pow(10, 0.1 * $ls)) + (8 * pow(10, 0.1 * ($lm + 5)))));
                } else {
                    $lsm = null;
                }
                
                // Format hasil
                $ls = $ls !== null ? number_format($ls, 2) : null;
                $lm = $lm !== null ? number_format($lm, 2) : null;
                $lsm = $lsm !== null ? number_format($lsm, 2) : null;

                $hasil_l24 = [
                    'ls' => $ls,
                    'lm' => $lm,
                    'lsm' => $lsm
                ];
            }else if(count($dataShift) > 7) { // shift 8 jam
                foreach ($dataShift as $shiftName => $items) {
                    // Ambil semua nilai laeq
                    $laeqValues = array_map(function($item) {
                        return (float) $item->laeq;
                    }, $items);
    
                    $count = count($laeqValues);
                    $sum = array_sum($laeqValues);
    
                    // Hitung combined LAeq jika data ada, jika tidak hasilkan null
                    $combinedLaeq = $count > 0 ? number_format((10 * log10((1 / $count) * $sum)), 1) : null;
                    $convertLAeq = $combinedLaeq ? number_format($combinedLaeq * 0.1, 2) : null; // combinedLaeq * 0.1
                    $hasilConvert = $convertLAeq !== null
                        ? number_format(1 * pow(10, $convertLAeq), 2)
                        : null; // 1*(10^convertLAeq)
                    
                        // Tambahkan hasil ke dalam array
                    $shifSummary[$shiftName] = [
                        'total_count' => $count,
                        'hasil_laeq' => $combinedLaeq,
                        'convert_laeq' => $convertLAeq,
                        'hasil_convert' => $hasilConvert
                    ];
                }
                // Hitung total hasil convert

                $totalHasilConvert = 0;
                $totalShift = count($shifSummary);

                foreach ($shifSummary as $data) {
                    // Hilangkan koma dan konversi ke float
                    $cleanValue = floatval(str_replace(',', '', $data['hasil_convert']));
                    $totalHasilConvert += $cleanValue;
                }

                // Hitung hasil akhir seperti rumus Excel: 10 * LOG((1 / totalShift) * totalHasilConvert)
                $hasil = 10 * log10((1 / $totalShift) * $totalHasilConvert);


                // Format hasil jika ingin tampil dengan 2 desimal
                $formattedJumlahLeq = number_format($totalHasilConvert, 1, '.', ',');
                $formattedHasil = number_format($hasil, 1, '.', ',');
                $L8 = [
                    'hasil' => $formattedHasil,
                    'jumlah_leq' => $formattedJumlahLeq
                ];
            }else{ // sesaat
                foreach ($dataShift as $shiftName => $items) {
                    // Ambil semua nilai laeq
                    $laeqValues = array_map(function($item) {
                        return (float) $item->laeq;
                    }, $items);
    
                    $count = count($laeqValues);
                    $sum = array_sum($laeqValues);
    
                    // Hitung combined LAeq jika data ada, jika tidak hasilkan null
                    $combinedLaeq = $count > 0 ? number_format((10 * log10((1 / $count) * $sum)), 1) : null;
                    $convertLAeq = $combinedLaeq ? number_format($combinedLaeq * 0.1, 2) : null; // combinedLaeq * 0.1
                    $hasilConvert = $convertLAeq !== null
                        ? number_format(1 * pow(10, $convertLAeq), 2)
                        : null; // 1*(10^convertLAeq)
                    // Tambahkan hasil ke dalam array
                    $shifSummary[$shiftName] = [
                        'total_count' => $count,
                        'hasil_laeq' => $combinedLaeq,
                        'convert_laeq' => $convertLAeq,
                        'hasil_convert' => $hasilConvert
                    ];
                }
            }
            
            DB::commit();
            return response()->json([
                'data' => $dataShift,
                'shifSummary' => $shifSummary,
                'hasil' => $L8, // shift 8 jam
                'hasil_dua_empat' => $hasil_l24 // shift 24 jam
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
            $hasil = (array)$request->hasil; // sesaat
            $hasilL8 = (array)$request->hasil_l8; // 8 jam
            $hasilDuaEmpat = (array)$request->hasil_dua_empat; // 24 jam

            $kebisingan_header = KebisinganHeader::where('no_sampel', $request->no_sampel)->first();
            $order_detail = OrderDetail::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            
            if($order_detail){
                // Decode parameter jika dalam format JSON
                $decoded = json_decode($order_detail->parameter, true);

                // Pastikan JSON ter-decode dengan benar dan berisi data
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Ambil elemen pertama dari array hasil decode
                    $parts = explode(';', $decoded[0] ?? '');

                    // Pastikan elemen kedua tersedia setelah explode
                    $parameterValue = $parts[1] ?? 'Data tidak valid';

                    // dd($parameterValue); // Output: "Pencahayaan"
                } else {
                    dd("Parameter tidak valid atau bukan JSON");
                }

                $parameter = Parameter::where('nama_lab', $parameterValue)->where('id_kategori', 4)->where('is_active', true)->first();
                // HEADER
                if(!$kebisingan_header){
                    $kebisingan_header = new KebisinganHeader();
                }
                $kebisingan_header->no_sampel = $request->no_sampel;
                $kebisingan_header->id_parameter = $parameter->id;
                $kebisingan_header->parameter = $parameter->nama_lab;
                $kebisingan_header->leq = $hasilL8['jumlah_leq'] ?? null;
                $kebisingan_header->ls = $hasilDuaEmpat['ls'] ?? null;
                $kebisingan_header->lm = $hasilDuaEmpat['lm'] ?? null;
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
                
                if(count($hasil) > 23){
                    $ws_value->hasil1 = $hasilDuaEmpat['lsm'] ?? null;
                }else if(count($hasil) > 7){
                    $ws_value->hasil1 = $hasilL8['hasil'] ?? null;
                }else{
                    $ws_value->hasil1 = $hasil['L1']['hasil_laeq'] ?? null;
                }
                $ws_value->save();
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