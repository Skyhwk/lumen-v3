<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganGetaran;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\GetaranHeader;
use App\Models\WsValueUdara;

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

class FdlGetaranController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganGetaran::with('detail')->orderBy('id', 'desc');

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
            ->filterColumn('detail.kategori_3', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_3', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('sumber_getaran', function ($query, $keyword) {
                $query->where('sumber_getaran', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('jarak_sumber_getaran', function ($query, $keyword) {
                $query->where('jarak_sumber_getaran', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('kondisi', function ($query, $keyword) {
                $query->where('kondisi', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('intensitas', function ($query, $keyword) {
                $query->where('intensitas', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('frekuensi', function ($query, $keyword) {
                $query->where('frekuensi', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request){
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganGetaran::where('id', $request->id)->first();

                GetaranHeader::where('no_sampel', $request->no_sampel_lama)
                ->update(
                    [
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]
                );

                WsValueUdara::where('no_sampel', $request->no_sampel_lama)
                ->update(
                    [
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]
                );

                $data->no_sampel = $request->no_sampel_baru;
                $data->no_sampel_lama = $request->no_sampel_lama;
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now();
                $data->save();

                $order_detail_lama = OrderDetail::where('no_sampel', $request->no_sampel_lama)
                    ->first();

                if ($order_detail_lama) {
                    OrderDetail::where('no_sampel', $request->no_sampel_baru)
                        ->where('is_active', 1)
                        ->update([
                            'tanggal_terima' => $order_detail_lama->tanggal_terima
                        ]);
                }

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil ubah no sampel '.$request->no_sampel_lama.' menjadi '.$request->no_sampel_baru
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal ubah no sampel '.$request->no_sampel_lama.' menjadi '.$request->no_sampel_baru,
                    'error' => $e->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'No Sampel tidak boleh kosong'
            ], 401);
        }
    }

    public function approve(Request $request){
        DB::beginTransaction();
        try {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganGetaran::where('id', $request->id)->first();
                $no_sample = $data->no_sampel;
                $kategori_3 = $data->kategori_3;
                $po = OrderDetail::where('no_sampel', $data->no_sampel)->first();
                if ($po) {
                    // Decode parameter jika dalam format JSON
                    $decoded = json_decode($po->parameter, true);

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

                } else {
                    dd("OrderDetail tidak ditemukan");
                }
                
                $param = Parameter::where('nama_lab', $parameterValue)->first();

                $dataa = json_decode($data->nilai_pengukuran);
                $totData = count(array_keys(get_object_vars($dataa)));

                $min_per = 0;
                $max_per = 0;
                $min_kec = 0;
                $max_kec = 0;
                $perminT = 0;
                $permaxT = 0;
                $kecminT = 0;
                $kecmaxT = 0;
                $perminP = 0;
                $permaxP = 0;
                $kecminP = 0;
                $kecmaxP = 0;
                $perminB = 0;
                $permaxB = 0;
                $kecminB = 0;
                $kecmaxB = 0;

                foreach ($dataa as $idx => $val) {

                    
                    foreach ($val as $idf => $vale) {

                        if ($idf == "min_per") {
                            $min_per += $vale;
                        } else if ($idf == "max_per") {
                            $max_per += $vale;
                        } else if ($idf == "min_kec") {
                            $min_kec += $vale;
                        } else if ($idf == "max_kec") {
                            $max_kec += $vale;
                        } else if ($idf == "perminT") {
                            $perminT += $vale;
                        } else if ($idf == "permaxT") {
                            $permaxT += $vale;
                        } else if ($idf == "kecminT") {
                            $kecminT += $vale;
                        } else if ($idf == "kecmaxT") {
                            $kecmaxT += $vale;
                        } else if ($idf == "perminP") {
                            $perminP += $vale;
                        } else if ($idf == "permaxP") {
                            $permaxP += $vale;
                        } else if ($idf == "kecminP") {
                            $kecminP += $vale;
                        } else if ($idf == "kecmaxP") {
                            $kecmaxP += $vale;
                        } else if ($idf == "perminB") {
                            $perminB += $vale;
                        } else if ($idf == "permaxB") {
                            $permaxB += $vale;
                        } else if ($idf == "kecminB") {
                            $kecminB += $vale;
                        } else if ($idf == "kecmaxB") {
                            $kecmaxB += $vale;
                        }
                        
                    }

                }
                

                $min_per_1 = number_format($min_per / $totData, 4);
                $max_per_1 = number_format($max_per / $totData, 4);
                $min_kec_1 = number_format($min_kec / $totData, 4);
                $max_kec_1 = number_format($max_kec / $totData, 4);
                $perminT_1 = number_format($perminT / $totData, 4);
                $permaxT_1 = number_format($permaxT / $totData, 4);
                $kecminT_1 = number_format($kecminT / $totData, 4);
                $kecmaxT_1 = number_format($kecmaxT / $totData, 4);
                $perminP_1 = number_format($perminP / $totData, 4);
                $permaxP_1 = number_format($permaxP / $totData, 4);
                $kecminP_1 = number_format($kecminP / $totData, 4);
                $kecmaxP_1 = number_format($kecmaxP / $totData, 4);
                $perminB_1 = number_format($perminB / $totData, 4);
                $permaxB_1 = number_format($permaxB / $totData, 4);
                $kecminB_1 = number_format($kecminB / $totData, 4);
                $kecmaxB_1 = number_format($kecmaxB / $totData, 4);

                $percep = number_format(($min_per_1 + $max_per_1) / 2, 4);
                $percepT = number_format(($perminT_1 + $permaxT_1) / 2, 4);
                $percepP = number_format(($perminP_1 + $permaxP_1) / 2, 4);
                $percepB = number_format(($perminB_1 + $permaxB_1) / 2, 4);
                $kercep = number_format(($min_kec_1 + $max_kec_1) / 2, 4);
                $kercepT = number_format(($kecminT_1 + $kecmaxT_1) / 2, 4);
                $kercepP = number_format(($kecminP_1 + $kecmaxP_1) / 2, 4);
                $kercepB = number_format(($kecminB_1 + $kecmaxB_1) / 2, 4);

                $kercep_mms = $kercep;
                $kercepT_mms = $kercepT;
                $kercepP_mms = $kercepP;
                $kercepB_mms = $kercepB;
                $percep_mms2 = $percep;
                $percepT_mms2 = $percepT;
                $percepP_mms2 = $percepP;
                $percepB_mms2 = $percepB;

                if ($data->sat_kec == "m/s") {
                    $kercep_mms = round(($kercep * 1000), 4);
                    $kercepT_mms = round(($kercepT * 1000), 4);
                    $kercepP_mms = round(($kercepP * 1000), 4);
                    $kercepB_mms = round(($kercepB * 1000), 4);
                }

                if ($data->sat_per == "m/s2") {
                    $percep_mms2 = round(($percep * 1000), 4);
                    $percepT_mms2 = round(($percepT * 1000), 4);
                    $percepP_mms2 = round(($percepP * 1000), 4);
                    $percepB_mms2 = round(($percepB * 1000), 4);
                }

                // switch ($parameterValue) {
                //     case "Getaran (4 Hz)":
                //         $kercep_mms = (10/4) * $kercep_mms;
                //         break;
                //     case "Getaran (5 Hz)":
                //         $kercep_mms = (10/5) * $kercep_mms;
                //         break;
                //     case "Getaran (6.3 Hz)":
                //         $kercep_mms = (10/6.3) * $kercep_mms;
                //         break;
                //     case "Getaran (8 Hz)":
                //         $kercep_mms = (10/8) * $kercep_mms;
                //         break;
                //     case "Getaran (10 Hz)":
                //         $kercep_mms = (10/10) * $kercep_mms;
                //         break;
                //     case "Getaran (12.5 Hz)":
                //         $kercep_mms = (10/12.5) * $kercep_mms;
                //         break;
                //     case "Getaran (16 Hz)":
                //         $kercep_mms = (10/16) * $kercep_mms;
                //         break;
                //     case "Getaran (20 Hz)":
                //         $kercep_mms = (10/20) * $kercep_mms;
                //         break;
                //     case "Getaran (25 Hz)":
                //         $kercep_mms = (10/25) * $kercep_mms;
                //         break;
                //     case "Getaran (31.5 Hz)":
                //         $kercep_mms = (10/31.5) * $kercep_mms;
                //         break;
                //     case "Getaran (40 Hz)":
                //         $kercep_mms = (10/40) * $kercep_mms;
                //         break;
                //     case "Getaran (50 Hz)":
                //         $kercep_mms = (10/50) * $kercep_mms;
                //         break;
                //     case "Getaran (63 Hz)":
                //         $kercep_mms = (10/63) * $kercep_mms;
                //         break;
                //     default:
                //         // Tidak ada konversi untuk parameter lainnya
                //         break;
                // }

                // Ekstrak nilai Hz dari parameter menggunakan regex
                if (preg_match('/Getaran \(([0-9.]+) Hz\)/', $parameterValue, $matches)) {
                    $hz = floatval($matches[1]);
                    $kercep_mms = (10 / $hz) * $kercep_mms;
                }

                $kercep_mms = $kercep_mms < 0.1 ? '<0.1' : $kercep_mms;
                $kercepT_mms = $kercepT_mms < 0.1 ? '<0.1' : $kercepT_mms;
                $kercepP_mms = $kercepP_mms < 0.1 ? '<0.1' : $kercepP_mms;
                $kercepB_mms = $kercepB_mms < 0.1 ? '<0.1' : $kercepB_mms;

                $percep_mms2 = $percep_mms2 < 0.1 ? '<0.1' : $percep_mms2;
                $percepT_mms2 = $percepT_mms2 < 0.1 ? '<0.1' : $percepT_mms2;
                $percepP_mms2 = $percepP_mms2 < 0.1 ? '<0.1' : $percepP_mms2;
                $percepB_mms2 = $percepB_mms2 < 0.1 ? '<0.1' : $percepB_mms2;

                
                

                if($kategori_3 == 13 || $kategori_3 == 14 || $kategori_3 == 15 || $kategori_3 == 16 || $kategori_3 == 17 || $kategori_3 == 18 || $kategori_3 == 19) {
                    if ($kercep_mms == '') {
                        return response()->json([
                            'message' => 'Data belum di calculate.!!'
                        ], 401);
                    }
                    if($kategori_3 == 17) {
                        if ($percepB_mms2) {
                            $hasil = json_encode([
                                "Percepatan_Tangan" => $percepT_mms2,
                                "Percepatan_Pinggang" => $percepP_mms2,
                                "Percepatan_Betis" => $percepB_mms2,
                                "Kecepatan_Tangan" => $kercepT_mms,
                                "Kecepatan_Pinggang" => $kercepP_mms,
                                "Kecepatan_Betis" => $kercepB_mms
                            ]);
                        } else {
                            $hasil = json_encode([
                                "Percepatan_Tangan" => $percepT_mms2,
                                "Percepatan_Pinggang" => $percepP_mms2,
                                "Kecepatan_Tangan" => $kercepT_mms,
                                "Kecepatan_Pinggang" => $kercepP_mms
                            ]);
                        }
                        
                    }else {
                        $hasil = json_encode([
                            "Percepatan" => $percep_mms2,
                            "Kecepatan" => $kercep_mms
                        ]);
                    }
                }else if($kategori_3 == 20 ) {
                    if ($kercepT_mms == '') {
                        return response()->json([
                            'message' => 'Data belum di calculate.!!'
                        ], 401);
                    }

                    if ($percepB_mms2) {
                        $hasil = json_encode([
                            "Percepatan_Tangan" => $percepT_mms2,
                            "Percepatan_Pinggang" => $percepP_mms2,
                            "Percepatan_Betis" => $percepB_mms2,
                            "Kecepatan_Tangan" => $kercepT_mms,
                            "Kecepatan_Pinggang" => $kercepP_mms,
                            "Kecepatan_Betis" => $kercepB_mms
                        ]);
                    } else {
                        $hasil = json_encode([
                            "Percepatan_Tangan" => $percepT_mms2,
                            "Percepatan_Pinggang" => $percepP_mms2,
                            "Kecepatan_Tangan" => $kercepT_mms,
                            "Kecepatan_Pinggang" => $kercepP_mms
                        ]);
                    }
                }
                
                // dd($hasil);
                $headGet = GetaranHeader::where('no_sampel', $no_sample)->where('is_active', 1)->first();
                $ws = WsValueUdara::where('no_sampel', $no_sample)->where('is_active', 1)->first();

                if (empty($headGet)) {
                    $headGet = new GetaranHeader;
                    $ws = new WsValueUdara;
                }

                $headGet->no_sampel = $no_sample;
                $headGet->id_parameter = $param->id;
                $headGet->parameter = $param->nama_lab;
                $headGet->is_approve = 1;
                // $headGet->lhps = 1;
                $headGet->approved_by = $this->karyawan;
                $headGet->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $headGet->created_by = $this->karyawan;
                $headGet->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $headGet->save();

                
                $ws->id_getaran_header = $headGet->id;
                $ws->no_sampel = $no_sample;
                $ws->id_po = $po->id;
                $ws->hasil1 = $hasil;
                $ws->save();

                $data->is_approve = 1;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                // dd($data, $headGet, $ws);
                $data->save();

                app(NotificationFdlService::class)->sendApproveNotification('Getaran', $data->no_sampel, $this->karyawan, $data->created_by);

                DB::commit();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'cat' => 1
                ], 200);

            } else {
                return response()->json([
                    'message' => 'Gagal Approve'
                ], 401);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 401);
        }
    }

    public function reject(Request $request){
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganGetaran::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve = false;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();

            return response()->json([
                'message' => 'Data has ben Reject',
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganGetaran::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification('Getaran', $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function delete(Request $request){
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganGetaran::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lokasi = public_path() .'/dokumentasi/sampling/'. $data->foto_lokasi_sampel;
            $foto_kondisi = public_path() .'/dokumentasi/sampling/'. $data->foto_kondisi_sampel;
            $foto_lain = public_path() .'/dokumentasi/sampling/'.$data->foto_lain;
            if (is_file($foto_lokasi)) {
                unlink($foto_lokasi);
            }
            if (is_file($foto_kondisi)) {
                unlink($foto_kondisi);
            }
            if (is_file($foto_lain)) {
                unlink($foto_lain);
            }
            $data->delete();

            // if($this->pin!=null){
            //     $nama = $this->name;
            //     $txt = "FDL GETARAN dengan No sample $no_sample Telah di Hapus oleh $nama";

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

    public function block(Request $request){
        if (isset($request->id) && $request->id != null) {
            if ($request->is_blocked == true) {
                $data = DataLapanganGetaran::where('id', $request->id)->first();
                $data->is_blocked     = false;
                $data->blocked_by    = null;
                $data->blocked_at    = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Unblocked for user',
                    'master_kategori' => 4
                ], 200);
            } else {
                $data = DataLapanganGetaran::where('id', $request->id)->first();
                $data->is_blocked     = true;
                $data->blocked_by    = $this->karyawan;
                $data->blocked_at    = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Blocked for user',
                    'master_kategori' => 4
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Melakukan Blocked'
            ], 401);
        }
    }

    public function detail(Request $request){
        $data = DataLapanganGetaran::with('detail')
            ->where('id', $request->id)
            ->first();

        $this->resultx = 'get Detail FDL Cahaya Berhasil';

        return response()->json([
            'id'                  => $data->id,
            'id_sub_kategori'     => explode('-', $data->detail->kategori_3)[0],
            'sub_kategori'        => explode('-', $data->detail->kategori_3)[1],
            'no_sample'           => $data->no_sampel,
            'no_order'            => $data->detail->no_order,
            'sampler'             => $data->created_by,
            'nama_perusahaan'     => $data->detail->nama_perusahaan,
            'keterangan'          => $data->keterangan,
            'keterangan_2'        => $data->keterangan_2,
            'waktu'               => $data->waktu,
            'latitude'            => $data->latitude,
            'longitude'           => $data->longitude,
            'massage'             => $this->resultx,
            'sumber_getaran'      => $data->sumber_getaran,
            'jarak_sumber_getaran'=> $data->jarak_sumber_getaran,
            'kondisi'             => $data->kondisi,
            'intensitas'          => $data->intensitas,
            'frek'                => $data->frek,
            'satuan_kecepatan'    => $data->satuan_kecepatan,
            'satuan_percepatan'   => $data->satuan_percepatan,
            'pengukuran'          => $data->pengukuran,
            'nilai_pengukuran'    => $data->nilai_pengukuran,
            'tikoor'              => $data->titik_koordinat,
            'foto_lok'            => $data->foto_lokasi_sample,
            'foto_lain'           => $data->foto_lain,
            'coor'                => $data->titik_koordinat,
            'nama_pekerja'        => $data->nama_pekerja,
            'jenis_pekerja'       => $data->jenis_pekerja,
            'lokasi_unit'         => $data->lokasi_unit,
            'status'              => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganGetaran::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
} 