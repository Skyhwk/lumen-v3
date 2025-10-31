<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganEmisiCerobong;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\EmisiCerobongHeader;
use App\Models\WsValueEmisiCerobong;

use App\Services\NotificationFdlService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

use App\Services\AnalystFormula;
use App\Models\AnalystFormula as Formula;

class FdlEmisiCerobongController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganEmisiCerobong::with('detail')
            ->where('tipe', $request->mode_cerobong)
            ->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.tanggal_sampling', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('tanggal_sampling', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.nama_perusahaan', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.no_order', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('no_order', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel_lama', function ($query, $keyword) {
                $query->where('no_sampel_lama', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('metode', function ($query, $keyword) {
                $query->where('metode', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('diameter_cerobong', function ($query, $keyword) {
                $query->where('diameter_cerobong', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('cuaca', function ($query, $keyword) {
                $query->where('cuaca', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('kelembapan', function ($query, $keyword) {
                $query->where('kelembapan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('tekanan_udara', function ($query, $keyword) {
                $query->where('tekanan_udara', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('waktu_pengukuran', function ($query, $keyword) {
                $query->where('waktu_pengukuran', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();

                EmisiCerobongHeader::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                WsValueEmisiCerobong::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                $data->no_sampel = $request->no_sampel_baru;
                $data->no_sampel_lama = $request->no_sampel_lama;
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                $order_detail_lama = OrderDetail::where('no_sampel', $request->no_sampel_lama)->first();

                if ($order_detail_lama) {
                    OrderDetail::where('no_sampel', $request->no_sampel_baru)
                        ->where('is_active', 1)
                        ->update([
                            'tanggal_terima' => $order_detail_lama->tanggal_terima
                        ]);
                }
                DB::commit();
                return response()->json([
                    'message' => 'Berhasil ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru,
                    'error' => $e->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'No Sampel tidak boleh kosong'
            ], 401);
        }
    }

    public function approve(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();
            $detail = OrderDetail::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
            
            if ($detail && isset($detail->parameter)) {
                $params = json_decode($detail->parameter, true);

                $parameterList = [];

                if (is_array($params)) {
                    foreach ($params as $param) {
                        [$id, $nama] = array_map('trim', explode(';', $param, 2));
                        $parameterList[] = [
                            'id' => $id,
                            'nama' => $nama
                        ];
                    }
                }
            }
            $paramList = ['CO2', 'O2', 'Opasitas', 'Suhu', 'Velocity', 'CO2 (ESTB)', 'O2 (ESTB)', 'Opasitas (ESTB)'];
            $parameters = ['CO2', 'Debu', 'NO2', 'Opasitas', 'SO2', 'Velocity'];

            // Filter agar hanya yang ada di $paramList
            $filteredParameters = array_values(array_intersect($parameters, $paramList));

            foreach ($filteredParameters as $key => $value) {
                $parameter = Parameter::where('nama_lab', $value)
                    ->where('nama_kategori', 'Emisi')
                    ->where('is_active', true)
                    ->first();

                $functionObj = Formula::where('id_parameter', $parameter->id)
                    ->where('is_active', true)
                    ->first();
                if(!$functionObj){
                    return response()->json(['message' => 'Formula is Coming Soon'], 404);
                } else{
                    $function = $functionObj->function;
                }

                $data_kalkulasi = AnalystFormula::where('function', $function)
                    ->where('data', $data)
                    ->where('id_parameter', $parameter->nama_lab)
                    ->process();

                $header = EmisiCerobongHeader::firstOrNew([
                    'no_sampel' => $data->no_sampel,
                    'id_parameter' => $parameter->id,
                ]);

                $header->fill([
                    'no_sampel' => $data->no_sampel,
                    'id_parameter' => $parameter->id,
                    'parameter' => $value,
                    'tanggal_terima' => $detail->tanggal_terima,
                    'template_stp' => 30,
                    'is_approved' => true,
                    'approved_by' => $this->karyawan,
                    'approved_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

                $header->save();

                $valueEmisi = WsValueEmisiCerobong::firstOrNew([
                    'id_emisi_cerobong_header' => $header->id,
                    'no_sampel' => $data->no_sampel
                ]);

                $valueEmisi->fill([
                    'id_emisi_cerobong_header' => $header->id,
                    'no_sampel' => $data->no_sampel,
                    'id_parameter' => $parameter->id,
                    'C' => $data_kalkulasi['C1'] ?? null,
                    'C1' => $data_kalkulasi['C2'] ?? null,
                    'C2' => $data_kalkulasi['C3'] ?? null,
                    'C3' => $data_kalkulasi['C4'] ?? null,
                    'C4' => $data_kalkulasi['C5'] ?? null,
                    'C5' => $data_kalkulasi['C6'] ?? null,
                    'C6' => $data_kalkulasi['C7'] ?? null,
                    'C7' => $data_kalkulasi['C8'] ?? null,
                    'C8' => $data_kalkulasi['C9'] ?? null,
                    'C9' => $data_kalkulasi['C10'] ?? null,
                    'C10' => $data_kalkulasi['C11'] ?? null,
                    'created_by' => $this->karyawan,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'suhu' => $data->suhu,
                    'Pa' => $data->tekanan_udara,
                    'suhu_cerobong' => $data->T_Flue,
                    'satuan' => $data_kalkulasi['satuan'] ?? null,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ]);
                $valueEmisi->save();

            }


            $data->is_approve = 1;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();
            return response()->json([
                'message' => 'Berhasil approve data'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal approve data',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    // 20/06/2025
    // public function approve(Request $request)
    // {
    //     if (isset($request->id) && $request->id != null) {
    //         DB::beginTransaction();
    //         try {
    //             $data = DataLapanganEmisiCerobong::with('detail')
    //                 ->where('id', $request->id)->first();
    //             $no_sample = $data->no_sampel;
    //             if ($data == null) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => 'Data Lapangan not found',
    //                 ], 404);
    //             }

    //             // Fungsi pembantu
    //             function ambilUtama($val) {
    //                 if (!is_string($val)) return $val;
                
    //                 // Coba decode dulu
    //                 $decoded = json_decode($val, true);
                
    //                 if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['utama'])) {
    //                     // Ganti koma dengan titik hanya di 'utama'-nya
    //                     return str_replace(',', '.', $decoded['utama']);
    //                 }
                
    //                 // Kalau bukan JSON valid, anggap angka biasa, baru ganti koma
    //                 return str_replace(',', '.', $val);
    //             }

    //             $data_param = [
    //                 'O2'  => ambilUtama($data->O2),
    //                 'CO2' => ambilUtama($data->CO2),
    //                 'C O' => ambilUtama($data->CO),
    //                 'NO'  => ambilUtama($data->NO),
    //                 'NO2' => ambilUtama($data->NO2),
    //                 'SO2' => ambilUtama($data->SO2),
    //                 'NOx' => ambilUtama($data->NOx),
    //             ];
                
    //             if (isset($data->NOx) && $data->NOx !== null) {
    //                 $data_param['NOx'] = str_replace(',','.', $data->NOx);
    //             }

    //             $Pa = floatval($data->tekanan_udara);
    //             $Ta = floatval($data->suhu);

    //             // Get Header If Exist
    //             $header = EmisiCerobongHeader::select(['id_parameter', 'parameter'])
    //                 ->where('no_sampel', $data->no_sampel)
    //                 ->where('is_active', true)
    //                 ->get()
    //                 ->map(function ($item) {
    //                     return [
    //                         'id_parameter' => $item->id_parameter,
    //                         'parameter' => $item->parameter
    //                     ];
    //                 })
    //                 ->toArray();

    //             // Get Parameter
    //             $param = json_decode($data->detail->parameter, true);

    //             $parameter = array_map(function ($item) {
    //                 return explode(';', $item)[1];
    //             }, $param);

    //             // Store Result After Calulating
    //             $hasil = [];
    //             foreach ($data_param as $key => $value) {
    //                 if ($data_param[$key] != null) {
    //                     $data_parameter = Parameter::where('nama_lab', $key)->where('nama_kategori', 'Emisi')->where('is_active', true)->first();
                        
    //                     $function = Formula::where('id_parameter', $data_parameter->id)->where('is_active', true)->first()->function;
    //                     $data_parsing = $request->all();
    //                     $data_parsing = (object) $data_parsing;
    //                     $data_parsing->C = floatval($data_param[$key]);
    //                     $data_parsing->Pa = $Pa;
    //                     $data_parsing->Ta = $Ta;

    //                     $data_kalkulasi = AnalystFormula::where('function', $function)
    //                         ->where('data', $data_parsing)
    //                         ->where('id_parameter', $data_parameter->id)
    //                         ->process();

    //                     if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
    //                         return (object)[
    //                             'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
    //                             'status' => 404
    //                         ];
    //                     }
    //                     $hasil[$key] = number_format($data_kalkulasi['hasil']);
    //                 }
    //             }

    //             $order = OrderDetail::where('no_sampel', $no_sample)->first();
    //             $parameterList = json_decode($order->parameter);  // asumsinya JSON array seperti list kamu di atas

    //             // buat mapping untuk cari nama parameter
    //             $paramMap = [];
    //             foreach ($parameterList as $param) {
    //                 [$code, $name] = explode(';', $param);
    //                 $paramMap[trim($name)] = trim($name);  // bisa juga simpan $code kalau perlu
    //             }

    //             // Khusus velocity rata-rata
    //             if($data->velocity != null){
    //                 $string = $data->velocity;
    //                 preg_match_all('/\d+(\.\d+)?/', $string, $matches);
    //                 $numbers = $matches[0];

    //                 $average = count($numbers) > 0 ? number_format(array_sum($numbers) / count($numbers), 4) : null;

    //                 if (isset($paramMap['Velocity'])) {
    //                     $hasil[$paramMap['Velocity']] = $average < 0.1 ? '<0.1' : $average;
    //                 }else if(isset($paramMap['Velocity/laju alir-NS1'])){
    //                     $hasil[$paramMap['Velocity/laju alir-NS1']] = $average < 0.1 ? '<0.1' : $average;
    //                 } else {
    //                     $hasil['Velocity'] = $average < 0.1 ? '<0.1' : $average;
    //                 }
    //             }

    //             if ($data->nilai_opasitas != null) {
    //                 $values = json_decode($data->nilai_opasitas, true); // decode ke array

    //                 $sum = array_sum($values);
    //                 $count = count($values);
    //                 $avg = $count > 0 ? $sum / $count : null;

    //                 // Tentukan key hasil
    //                 if (isset($paramMap['Opasitas'])) {
    //                     $key = $paramMap['Opasitas'];
    //                 } elseif (isset($paramMap['Opasitas-STD2'])) {
    //                     $key = 'Opasitas-STD2';
    //                 } elseif (isset($paramMap['Opasitas-STD1'])) {
    //                     $key = 'Opasitas-STD1';
    //                 } else {
    //                     $key = 'Opasitas';
    //                 }

    //                 if ($avg !== null) {
    //                     // Jika hasil < 0.83 maka tampilkan "<0.83", selain itu tampilkan hasil normal
    //                     $hasil[$key] = ($avg < 0.83)
    //                         ? '<0.83'
    //                         : number_format($avg, 4, '.', '');
    //                 } else {
    //                     $hasil[$key] = null;
    //                 }
    //             }


    //             // Store Header and Value
    //             foreach ($hasil as $key => $value) {
    //                 $getparam = Parameter::where('nama_lab', $key)
    //                     ->where('id_kategori', 5)
    //                     ->where('is_active', true)
    //                     ->first();

    //                 if ($getparam) {

    //                     // Cek jika header sudah ada berdasarkan no_sample, id_po, dan param
    //                     $addHeader = EmisiCerobongHeader::where('id_parameter', $getparam->id)
    //                         ->where('no_sampel', $data->no_sampel)
    //                         ->first();

    //                     if (!$addHeader) {
    //                         // Jika header tidak ditemukan, buat baru
    //                         $addHeader = EmisiCerobongHeader::create([
    //                             'id_parameter' => $getparam->id,
    //                             'tanggal_terima' => $data->detail->tanggal_terima,
    //                             'no_sampel' => $data->no_sampel,
    //                             'is_approved' => true,
    //                             'approved_by' => $this->karyawan,
    //                             'approved_at' => Carbon::now()->format('Y-m-d H:i:s'),
    //                             'created_by' => $this->karyawan,
    //                             'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
    //                             'parameter' => $key,
    //                             'template_stp' => 31,
    //                         ]);
    //                     } else {
    //                         // Jika header ditemukan, update data
    //                         $addHeader->update([
    //                             'id_parameter' => $getparam->id,
    //                             'tanggal_terima' => $data->detail->tanggal_terima,
    //                             'no_sampel' => $data->no_sampel,
    //                             'is_approved' => true,
    //                             'approved_by' => $this->karyawan,
    //                             'approved_at' => Carbon::now()->format('Y-m-d H:i:s'),
    //                             'created_by' => $this->karyawan,
    //                             'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
    //                             'parameter' => $key,
    //                             'template_stp' => 31,
    //                         ]);
    //                     }

    //                     // Cek jika valueEmisi sudah ada berdasarkan id_emisic_header dan id_parameter
    //                     $valueEmisi = WsValueEmisiCerobong::where('id_emisi_cerobong_header', $addHeader->id)
    //                         ->where('id_parameter', $getparam->id)
    //                         ->where('no_sampel', $data->no_sampel)
    //                         ->first();

    //                     if (!$valueEmisi) {
    //                         // Jika valueEmisi tidak ditemukan, buat baru
    //                         if ($key == 'O2 (ESTB)' || $key == 'CO2 (ESTB)' || $key == 'Opasitas' || $key == 'Opasitas-STD2' || $key == 'Opasitas-STD1') {
    //                             $valueEmisi = WsValueEmisiCerobong::create([
    //                                 'id_emisi_cerobong_header' => $addHeader->id,
    //                                 'C3_persen' => $value,
    //                                 'suhu_cerobong' => $data->T_Flue,
    //                                 'no_sampel' => $data->no_sampel,
    //                                 'tanggal_terima' => $data->detail->tanggal_terima,
    //                                 'id_parameter' => $getparam->id,
    //                                 'created_by' => $this->karyawan,
    //                                 'created_at' => carbon::now()->format('Y-m-d H:i:s'),
    //                                 'suhu' => $data->suhu,
    //                                 'Pa' => $data->tekanan_udara
    //                             ]);
    //                         }else if($key == 'Velocity/laju alir-NS1' || $key == 'Velocity'){
    //                             $valueEmisi = WsValueEmisiCerobong::create([
    //                                 'id_emisi_cerobong_header' => $addHeader->id,
    //                                 'C10' => $value,
    //                                 'suhu_cerobong' => $data->T_Flue,
    //                                 'no_sampel' => $data->no_sampel,
    //                                 'tanggal_terima' => $data->detail->tanggal_terima,
    //                                 'id_parameter' => $getparam->id,
    //                                 'created_by' => $this->karyawan,
    //                                 'created_at' => carbon::now()->format('Y-m-d H:i:s'),
    //                                 'suhu' => $data->suhu,
    //                                 'Pa' => $data->tekanan_udara
    //                             ]);
    //                         }else if($key == 'Suhu Cerobong-NS1'){
    //                             $valueEmisi = WsValueEmisiCerobong::create([
    //                                 'id_emisi_cerobong_header' => $addHeader->id,
    //                                 'suhu_cerobong' => $data->T_Flue,
    //                                 'no_sampel' => $data->no_sampel,
    //                                 'tanggal_terima' => $data->detail->tanggal_terima,
    //                                 'id_parameter' => $getparam->id,
    //                                 'created_by' => $this->karyawan,
    //                                 'created_at' => carbon::now()->format('Y-m-d H:i:s'),
    //                                 'suhu' => $data->suhu,
    //                                 'Pa' => $data->tekanan_udara
    //                             ]);
    //                         } else {
    //                             $valueEmisi = WsValueEmisiCerobong::create([
    //                                 'id_emisi_cerobong_header' => $addHeader->id,
    //                                 'C' => null,
    //                                 'C1' => $value,
    //                                 'C2' => $data_param[$key],  // << Tambahan disini
    //                                 'suhu_cerobong' => $data->T_Flue,
    //                                 'no_sampel' => $data->no_sampel,
    //                                 'tanggal_terima' => $data->detail->tanggal_terima,
    //                                 'id_parameter' => $getparam->id,
    //                                 'created_by' => $this->karyawan,
    //                                 'created_at' => carbon::now()->format('Y-m-d H:i:s'),
    //                                 'suhu' => $data->suhu,
    //                                 'Pa' => $data->tekanan_udara
    //                             ]);
    //                         }
    //                     } else {
    //                         // Jika valueEmisi ditemukan, update data
    //                         if ($key == 'O2 (ESTB)' || $key == 'CO2 (ESTB)' || $key == 'Opasitas' || $key == 'Opasitas-STD2' || $key == 'Opasitas-STD1' || $key == 'Velocity/laju alir-NS1' || $key == 'Velocity') {
    //                             $valueEmisi->update([
    //                                 'id_emisi_cerobong_header' => $addHeader->id,
    //                                 'C3_persen' => $value,
    //                                 'suhu_cerobong' => $data->T_Flue,
    //                                 'no_sampel' => $data->no_sampel,
    //                                 'tanggal_terima' => $data->detail->tanggal_terima,
    //                                 'id_parameter' => $getparam->id,
    //                                 'created_by' => $this->karyawan,
    //                                 'created_at' => carbon::now()->format('Y-m-d H:i:s'),
    //                                 'suhu' => $data->suhu,
    //                                 'Pa' => $data->tekanan_udara,
    //                                 'is_active' => true
    //                             ]);
    //                         }else if($key == 'Suhu Cerobong-NS1'){
    //                             $valueEmisi->update([
    //                                 'id_emisi_cerobong_header' => $addHeader->id,
    //                                 'suhu_cerobong' => $data->T_Flue,
    //                                 'no_sampel' => $data->no_sampel,
    //                                 'tanggal_terima' => $data->detail->tanggal_terima,
    //                                 'id_parameter' => $getparam->id,
    //                                 'created_by' => $this->karyawan,
    //                                 'created_at' => carbon::now()->format('Y-m-d H:i:s'),
    //                                 'suhu' => $data->suhu,
    //                                 'Pa' => $data->tekanan_udara,
    //                                 'is_active' => true
    //                             ]);
    //                         } else {
    //                             $valueEmisi->update([
    //                                 'id_emisi_cerobong_header' => $addHeader->id,
    //                                 'C' => null,
    //                                 'C1' => $value,
    //                                 'C2' => $data_param[$key],  // << Tambahan disini
    //                                 'suhu_cerobong' => $data->T_Flue,
    //                                 'no_sampel' => $data->no_sampel,
    //                                 'tanggal_terima' => $data->detail->tanggal_terima,
    //                                 'id_parameter' => $getparam->id,
    //                                 'created_by' => $this->karyawan,
    //                                 'created_at' => carbon::now()->format('Y-m-d H:i:s'),
    //                                 'suhu' => $data->suhu,
    //                                 'Pa' => $data->tekanan_udara,
    //                                 'is_active' => true
    //                             ]);
    //                         }
    //                     }
    //                 }
    //             }

    //             $data->is_approve = 1;
    //             $data->approved_by = $this->karyawan;
    //             $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
    //             $data->save();

    //             app(NotificationFdlService::class)->sendApproveNotification('Emisi Cerobong', $data->no_sampel, $this->karyawan, $data->created_by);

    //             DB::commit();
    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => "Data FDL EMISI CEROBONG dengan No Sampel $no_sample berhasil diapprove oleh $this->karyawan"
    //             ], 200);
    //         } catch (\Exception $th) {
    //             DB::rollBack();
    //             dd($th);
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => $th->getMessage(),
    //                 'line' => $th->getLine()
    //             ], 500);
    //         }
    //     } else {
    //         return response()->json([
    //             'message' => "Data FDL EMISI CEROBONG dengan No Sampel $no_sample gagal diapprove oleh $this->karyawan"
    //         ], 401);
    //     }
    // }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $ws_value = WsValueEmisiCerobong::Where('no_sampel', $no_sample)->get();
            foreach ($ws_value as $key => $value) {
                $value->is_active = false;
                $value->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $value->deleted_by = $this->karyawan;
                $value->save();
            }
            $data->is_approve = false;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->save();
            
            return response()->json([
                'message' => "Data FDL EMISI CEROBONG dengan No Sampel $no_sample berhasil direject oleh $this->karyawan",
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => "Data FDL EMISI CEROBONG dengan No Sampel $no_sample gagal direject oleh $this->karyawan"
            ], 401);
        }
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification('Emisi Cerobong', $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_kon)) {
                unlink($foto_kon);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->name;
            //     $txt = "FDL Emisi Cerobong dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    public function block(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            if ($request->is_blocked == true) {
                $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Unblocked for user',
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganEmisiCerobong::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Blocked for user',
                    'master_kategori' => 1
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Melakukan Blocked'
            ], 401);
        }
    }

    public function detail(Request $request)
    {
        $data = DataLapanganEmisiCerobong::with('detail')->where('id', $request->id)->first();

        $this->resultx = 'get Detail sample lapangan Emisi Cerobong success';

        return response()->json([
            'id' => $data->id,
            'no_sample' => $data->detail->no_sample,
            'no_order' => $data->detail->no_order,
            'sub_kategori' => explode('-', $data->detail->kategori_3)[1],
            'sampler' => $data->created_by,
            'nama_perusahaan' => $data->detail->nama_perusahaan,
            'keterangan' => $data->keterangan,
            'keterangan_2' => $data->keterangan_2,
            'koordinat' => $data->titik_koordinat,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'sumber_emisi' => $data->sumber_emisi,
            'merk' => $data->merk,
            'bahan_bakar' => $data->bahan_bakar,
            'cuaca' => $data->cuaca,
            'kecepatan_angin' => $data->kecepatan_angin,
            'diameter_cerobong' => $data->diameter_cerobong,
            'durasi_operasi' => $data->durasi_operasi,
            'proses_filtrasi' => $data->proses_filtrasi,
            'metode' => $data->metode,
            'T_Flue' => $data->T_Flue,
            'velocity' => $data->velocity,
            'waktu_pengukuran' => $data->waktu_pengukuran,
            'suhu' => $data->suhu,
            'kelembaban' => $data->kelembaban,
            'tekanan_udara' => $data->tekanan_udara,
            'opasitas' => $data->opasitas,
            'O2 (ESTB)' => $data->O2,
            'co' => $data->CO,
            'CO2 (ESTB)' => $data->CO2,
            'no' => $data->NO,
            'no2' => $data->NO2,
            'so2' => $data->SO2,
            'partikulat' => $data->partikulat,
            'hf' => $data->HF,
            'hci' => $data->HCI,
            'h2s' => $data->H2S,
            'nh3' => $data->NH3,
            'ci2' => $data->CI2,
            'foto_lokasi' => $data->foto_lokasi_sampel,
            'foto_kondisi' => $data->foto_kondisi_sampel,
            'foto_lain' => $data->foto_lain,

            'status' => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganEmisiCerobong::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}