<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganIklimDingin;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\IklimHeader;
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

class FdlIklimDinginController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganIklimDingin::with('detail')->orderBy('id', 'desc');

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
            ->filterColumn('lokasi', function ($query, $keyword) {
                $query->where('lokasi', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('sumber_dingin', function ($query, $keyword) {
                $query->where('sumber_dingin', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('jarak_sumber_dingin', function ($query, $keyword) {
                $query->where('jarak_sumber_dingin', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('akumulasi_waktu_paparan', function ($query, $keyword) {
                $query->where('akumulasi_waktu_paparan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('waktu_kerja', function ($query, $keyword) {
                $query->where('waktu_kerja', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('shift_pengambilan', function ($query, $keyword) {
                $query->where('shift_pengambilan', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganIklimDingin::where('id', $request->id)->first();

                IklimHeader::where('no_sampel', $request->no_sampel_lama)
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

                // update OrderDetail
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
            $data = DataLapanganIklimDingin::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $po = OrderDetail::where('no_sampel', $no_sample)->first();
            if (isset($request->id) && $request->id != null) {

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

                $parameter = Parameter::where('nama_lab', $parameterValue)->first();
                // $check = IklimHeader::where('no_sampel', $no_sample)->where('is_active', true)->first();

                // if ($check) {
                //     return response()->json([
                //         'message' => 'Data sudah di Calculate'
                //     ], 401);
                // }

                $dataL = DataLapanganIklimDingin::select('no_sampel', 'pengukuran', 'shift_pengambilan', 'akumulasi_waktu_paparan')
                    ->where('no_sampel', $no_sample)->get();
                $totalL = $dataL->count();

                $totalShifts = 0;
                $hasil = 0;

                $getTotalApprove = DataLapanganIklimDingin::where('no_sampel', $no_sample)
                    // ->where('parameter', $parameter)
                    ->select(DB::raw('COUNT(no_sampel) AS total'))
                    ->where('is_approve', true)
                    ->first();

                $hasil_suhu_terpapar = [];
                $hasil_angin_terpapar = [];
                $total_waktu_paparan = 0; // Menampung jumlah total waktu paparan

                if ($getTotalApprove->total + 1 == $totalL) {

                    foreach ($dataL as $indx => $val) {
                        if ($val->pengukuran != null) {
                            // Decode JSON menjadi array asosiatif PHP
                            // dd($val);
                            $dataa = json_decode($val->pengukuran, true);
                            $totData = count($dataa);
                            $totHasilSuhu = 0;
                            $totHasilAngin = 0;
                            $waktuPaparan = $val->akumulasi_waktu_paparan; // Ambil waktu paparan dari setiap data
                            foreach ($dataa as $idx => $vl) {
                                // dd($dataa);
                                $totHasilSuhu += $vl['suhu_kering'];
                                $totHasilAngin += $vl['kecepatan_angin'];
                            }
                            // array_push($hasil_rata_suhu, $totHasilSuhu / $totData);
                            // array_push($hasil_rata_angin, $totHasilAngin / $totData);

                            // Menghitung rata-rata suhu dan angin untuk data ini
                            $rataSuhu = $totHasilSuhu / $totData;
                            $rataAngin = $totHasilAngin / $totData;

                            // Menampung hasil perhitungan rata-rata suhu dan angin dikali waktu paparan ke dalam array
                            $hasil_suhu_terpapar[] = $rataSuhu * $waktuPaparan;
                            $hasil_angin_terpapar[] = $rataAngin * $waktuPaparan;
                            $total_waktu_paparan += $waktuPaparan; // Menambahkan waktu paparan ke total waktu paparan  
                        }
                    }
                    // $rataSuhu = array_sum($hasil_rata_suhu) / $totalL;
                    // $rataAngin = array_sum($hasil_rata_angin) / $totalL;

                    // Setelah loop selesai, menghitung total suhu dan angin terpapar
                    $total_suhu_terpapar = array_sum($hasil_suhu_terpapar);
                    $total_angin_terpapar = array_sum($hasil_angin_terpapar);

                    // Menghitung rata-rata suhu dan angin terpapar
                    $rataSuhu = $total_suhu_terpapar / $total_waktu_paparan;
                    $rataAngin = $total_angin_terpapar / $total_waktu_paparan;
                    // dd($rataSuhu, $rataAngin, $total_waktu_paparan);


                    // RATA-RATA SUHU KERING
                    if ($rataSuhu > 4.4) {
                        $rataSuhu = 10;
                    } else if ($rataSuhu > -1.1 && $rataSuhu <= 4.4) {
                        $rataSuhu = 4.4;
                    } else if ($rataSuhu > -6.7 && $rataSuhu <= -1.1) {
                        $rataSuhu = -1.1;
                    } else if ($rataSuhu > -12.2 && $rataSuhu <= -6.7) {
                        $rataSuhu = -6.7;
                    } else if ($rataSuhu > -17.8 && $rataSuhu <= -12.2) {
                        $rataSuhu = -12.2;
                    } else if ($rataSuhu > -23.3 && $rataSuhu <= -17.8) {
                        $rataSuhu = -17.8;
                    } else if ($rataSuhu > -28.9 && $rataSuhu <= -23.3) {
                        $rataSuhu = -23.3;
                    } else if ($rataSuhu > -34.4 && $rataSuhu <= -28.9) {
                        $rataSuhu = -28.9;
                    } else if ($rataSuhu > -40.0 && $rataSuhu <= -34.4) {
                        $rataSuhu = -34.4;
                    } else if ($rataSuhu > -45.6 && $rataSuhu <= -40.0) {
                        $rataSuhu = -40.0;
                    } else if ($rataSuhu <= -45.6) {
                        $rataSuhu = -45.6;
                    }



                    // Bulatkan kecepatan angin ke atas menjadi kelipatan 5
                    $bulatKecepatanAngin = ceil($rataAngin / 5) * 5;
                    // Batasi maksimal rata-rata kecepatan angin menjadi 40
                    $rataRataKecepatanAngin = min(40, $bulatKecepatanAngin);

                    // dd($rataSuhu, $rataRataKecepatanAngin);
                    switch ($rataRataKecepatanAngin) {
                        case 5:
                            switch ($rataSuhu) {
                                case 10:
                                    $hasil = 8.9;
                                    break;
                                case 4.4:
                                    $hasil = 2.8;
                                    break;
                                case -1.1:
                                    $hasil = -2.8;
                                    break;
                                case -6.7:
                                    $hasil = -8.9;
                                    break;
                                case -12.2:
                                    $hasil = -14.4;
                                    break;
                                case -17.8:
                                    $hasil = -20.6;
                                    break;
                                case -23.3:
                                    $hasil = -26.1;
                                    break;
                                case -28.9:
                                    $hasil = -32.2;
                                    break;
                                case -34.4:
                                    $hasil = -37.8;
                                    break;
                                case -40.0:
                                    $hasil = -43.9;
                                    break;
                                case -45.6:
                                    $hasil = -49.4;
                                    break;
                                case -51.1:
                                    $hasil = -55.6;
                                    break;
                            }
                            break;
                        // KECEPATAN ANGIN 10
                        case 10:
                            switch ($rataSuhu) {
                                case 10:
                                    $hasil = 4.4;
                                    break;
                                case 4.4:
                                    $hasil = -2.2;
                                    break;
                                case -1.1:
                                    $hasil = -8.9;
                                    break;
                                case -6.7:
                                    $hasil = -15.6;
                                    break;
                                case -12.2:
                                    $hasil = -22.8;
                                    break;
                                case -17.8:
                                    $hasil = -31.1;
                                    break;
                                case -23.3:
                                    $hasil = -36.1;
                                    break;
                                case -28.9:
                                    $hasil = -43.3;
                                    break;
                                case -34.4:
                                    $hasil = -50.0;
                                    break;
                                case -40.0:
                                    $hasil = -56.7;
                                    break;
                                case -45.6:
                                    $hasil = -63.9;
                                    break;
                                case -51.1:
                                    $hasil = -70.6;
                                    break;
                            }
                            break;
                        // KECEPATAN ANGIN 15
                        case 15:
                            switch ($rataSuhu) {
                                case 10:
                                    $hasil = 2.2;
                                    break;
                                case 4.4:
                                    $hasil = -5.6;
                                    break;
                                case -1.1:
                                    $hasil = -12.8;
                                    break;
                                case -6.7:
                                    $hasil = -20.6;
                                    break;
                                case -12.2:
                                    $hasil = -27.8;
                                    break;
                                case -17.8:
                                    $hasil = -35.6;
                                    break;
                                case -23.3:
                                    $hasil = -42.8;
                                    break;
                                case -28.9:
                                    $hasil = -50.0;
                                    break;
                                case -34.4:
                                    $hasil = -57.8;
                                    break;
                                case -40.0:
                                    $hasil = -65.0;
                                    break;
                                case -45.6:
                                    $hasil = -72.8;
                                    break;
                                case -51.1:
                                    $hasil = -80.0;
                                    break;
                            }
                            break;
                        // KECEPATAN ANGIN 20
                        case 20:
                            switch ($rataSuhu) {
                                case 10:
                                    $hasil = 0.0;
                                    break;
                                case 4.4:
                                    $hasil = -7.8;
                                    break;
                                case -1.1:
                                    $hasil = -15.6;
                                    break;
                                case -6.7:
                                    $hasil = -23.3;
                                    break;
                                case -12.2:
                                    $hasil = -31.7;
                                    break;
                                case -17.8:
                                    $hasil = -39.4;
                                    break;
                                case -23.3:
                                    $hasil = -47.2;
                                    break;
                                case -28.9:
                                    $hasil = -55.0;
                                    break;
                                case -34.4:
                                    $hasil = -63.3;
                                    break;
                                case -40.0:
                                    $hasil = -71.1;
                                    break;
                                case -45.6:
                                    $hasil = -78.9;
                                    break;
                                case -51.1:
                                    $hasil = -80.0;
                                    break;
                            }
                            break;
                        case 25:
                            switch ($rataSuhu) {
                                case 10:
                                    $hasil = -1.1;
                                    break;
                                case 4.4:
                                    $hasil = -8.9;
                                    break;
                                case -1.1:
                                    $hasil = -17.8;
                                    break;
                                case -6.7:
                                    $hasil = -26.1;
                                    break;
                                case -12.2:
                                    $hasil = -33.9;
                                    break;
                                case -17.8:
                                    $hasil = -42.2;
                                    break;
                                case -23.3:
                                    $hasil = -50.6;
                                    break;
                                case -28.9:
                                    $hasil = -58.9;
                                    break;
                                case -34.4:
                                    $hasil = -66.7;
                                    break;
                                case -40.0:
                                    $hasil = -75.6;
                                    break;
                                case -45.6:
                                    $hasil = -83.3;
                                    break;
                                case -51.1:
                                    $hasil = -91.7;
                                    break;
                            }
                            break;
                        case 30:
                            switch ($rataSuhu) {
                                case 10:
                                    $hasil = -2.2;
                                    break;
                                case 4.4:
                                    $hasil = -10.6;
                                    break;
                                case -1.1:
                                    $hasil = -18.9;
                                    break;
                                case -6.7:
                                    $hasil = -27.8;
                                    break;
                                case -12.2:
                                    $hasil = -36.1;
                                    break;
                                case -17.8:
                                    $hasil = -44.4;
                                    break;
                                case -23.3:
                                    $hasil = -52.8;
                                    break;
                                case -28.9:
                                    $hasil = -61.7;
                                    break;
                                case -34.4:
                                    $hasil = -70.0;
                                    break;
                                case -40.0:
                                    $hasil = -78.3;
                                    break;
                                case -45.6:
                                    $hasil = -87.2;
                                    break;
                                case -51.1:
                                    $hasil = -95.6;
                                    break;
                            }
                            break;
                        case 35:
                            switch ($rataSuhu) {
                                case 10:
                                    $hasil = -2.8;
                                    break;
                                case 4.4:
                                    $hasil = -11.7;
                                    break;
                                case -1.1:
                                    $hasil = -20.0;
                                    break;
                                case -6.7:
                                    $hasil = -28.9;
                                    break;
                                case -12.2:
                                    $hasil = -37.2;
                                    break;
                                case -17.8:
                                    $hasil = -46.1;
                                    break;
                                case -23.3:
                                    $hasil = -55.0;
                                    break;
                                case -28.9:
                                    $hasil = -63.3;
                                    break;
                                case -34.4:
                                    $hasil = -72.2;
                                    break;
                                case -40.0:
                                    $hasil = -80.6;
                                    break;
                                case -45.6:
                                    $hasil = -89.4;
                                    break;
                                case -51.1:
                                    $hasil = -98.3;
                                    break;
                            }
                            break;
                        case 40:
                            switch ($rataSuhu) {
                                case 10:
                                    $hasil = -3.3;
                                    break;
                                case 4.4:
                                    $hasil = -12.2;
                                    break;
                                case -1.1:
                                    $hasil = -21.2;
                                    break;
                                case -6.7:
                                    $hasil = -21.1;
                                    break;
                                case -12.2:
                                    $hasil = -38.3;
                                    break;
                                case -17.8:
                                    $hasil = -47.2;
                                    break;
                                case -23.3:
                                    $hasil = -56.1;
                                    break;
                                case -28.9:
                                    $hasil = -65.0;
                                    break;
                                case -34.4:
                                    $hasil = -73.3;
                                    break;
                                case -40.0:
                                    $hasil = -82.2;
                                    break;
                                case -45.6:
                                    $hasil = -91.1;
                                    break;
                                case -51.1:
                                    $hasil = -100.0;
                                    break;
                            }
                            break;
                        default:
                            $hasil = 'Tidak ada data';

                    }


                    $headUdara = new IklimHeader;
                    $headUdara->no_sampel = $no_sample;
                    $headUdara->id_parameter = $parameter->id;
                    $headUdara->parameter = $parameter->nama_lab;
                    $headUdara->is_approve = true;
                    $headUdara->approved_by = $this->karyawan;
                    $headUdara->approved_at = Carbon::now();
                    $headUdara->created_by = $this->karyawan;
                    $headUdara->created_at = Carbon::now();
                    $headUdara->save();

                    $ws = new WsValueUdara;
                    $ws->id_iklim_header = $headUdara->id;
                    $ws->no_sampel = $no_sample;
                    $ws->id_po = $po->id;
                    $ws->hasil1 = $hasil;
                    $ws->save();

                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->save();
                } else {

                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->save();
                }
                app(NotificationFdlService::class)->sendApproveNotification("Iklim Dingin pada Shift $data->shift_pengambilan", $data->no_sampel, $this->karyawan, $data->created_by);
                DB::commit();
                return response()->json([
                    'message' => "FDL IKLIM DINGIN dengan No sample $no_sample Telah di Approve oleh $this->karyawan",
                    'cat' => 1
                ], 200);

                if ($cek_sampler->pin_user != null) {
                    $nama = $this->name;
                    $txt = "FDL IKLIM DINGIN dengan No sample $no_sample Telah di Approve oleh $nama";

                    $telegram = new Telegram();
                    $telegram->send($cek_sampler->pin_user, $txt);
                }
            } else {
                return response()->json([
                    'message' => "FDL IKLIM DINGIN dengan No sample $no_sample Gagal di Approve oleh $this->karyawan"
                ], 401);
            }
        } catch (\Exception $th) {
            DB::rollback();
            dd($th);
        }
    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganIklimDingin::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            // $check = IklimHeader::where('no_sample', $no_sample)->where('active', 0)->first();
            //$ws = Valuewsudara::where('no_sample', $no_sample)->first();

            $data->is_approve = false;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();
            // dd($data);

            // if($cek_sampler->pin_user!=null){
            //     $nama = $this->name;
            //     $txt = "FDL GETARAN dengan No sample $no_sample Telah di Reject oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($cek_sampler->pin_user, $txt);
            // }

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
            $data = DataLapanganIklimDingin::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Iklim Dingin pada Shift $data->shift_pengambilan", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
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
            $data = DataLapanganIklimDingin::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lokasi = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
            if (is_file($foto_lokasi)) {
                unlink($foto_lokasi);
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
                'master_kategori' => 4
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
                $data = DataLapanganIklimDingin::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Unblocked for user',
                    'master_kategori' => 4
                ], 200);
            } else {
                $data = DataLapanganIklimDingin::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = Carbon::now();
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

    public function detail(Request $request)
    {
        $data = DataLapanganIklimDingin::with('detail')
            ->where('id', $request->id)
            ->first();

        $this->resultx = 'get Detail FDL IKLIM DINGIN Personal Berhasil';

        return response()->json([
            'id' => $data->id,
            'no_sampel' => $data->no_sampel,
            'no_order' => $data->detail->no_order,
            'sub_kategori' => explode('-', $data->detail->kategori_3)[1],
            'id_sub_kategori' => explode('-', $data->detail->kategori_3)[0],
            'sampler' => $data->created_by,
            'nama_perusahaan' => $data->detail->nama_perusahaan,
            'keterangan' => $data->keterangan,
            'keterangan_2' => $data->keterangan_2,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'kategori_pengujian' => $data->kategori_pengujian,
            'apd_khusus' => $data->apd_khusus,
            'aktifitas' => $data->aktifitas,
            'lokasi' => $data->lokasi,
            'sumber_dingin' => $data->sumber_dingin,
            'jarak_sumber_dingin' => $data->jarak_sumber_dingin,
            'akumulasi_waktu_paparan' => $data->akumulasi_waktu_paparan,
            'waktu_kerja' => $data->waktu_kerja,
            'jam_awal_pengukuran' => $data->jam_awal_pengukuran,
            'shift_pengambilan' => $data->shift_pengambilan,
            'jam_akhir_pengujian' => $data->jam_akhir_pengujian,
            'pengukuran' => $data->pengukuran,
            'tipe_alat' => $data->tipe_alat,
            'koordinat' => $data->titik_koordinat,
            'foto_lokasi' => $data->foto_lokasi_sampel,
            'foto_lain' => $data->foto_lain,
            'status' => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganIklimDingin::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}