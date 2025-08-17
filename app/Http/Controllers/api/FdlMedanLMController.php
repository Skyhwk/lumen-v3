<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganMedanLM;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\MedanLmHeader;
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
use App\Models\AnalystFormula as Formula;
use App\Services\AnalystFormula;

class FdlMedanLMController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganMedanLM::with('detail')
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
            ->filterColumn('detail.kategori_3', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_3', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('parameter', function ($query, $keyword) {
                $query->where('parameter', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('lokasi', function ($query, $keyword) {
                $query->where('lokasi', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('waktu_pemaparan', function ($query, $keyword) {
                $query->where('waktu_pemaparan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('waktu_pengukuran', function ($query, $keyword) {
                $query->where('waktu_pengukuran', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('magnet_3', function ($query, $keyword) {
                $query->where('magnet_3', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('magnet_30', function ($query, $keyword) {
                $query->where('magnet_30', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('magnet_100', function ($query, $keyword) {
                $query->where('magnet_100', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('listrik_3', function ($query, $keyword) {
                $query->where('listrik_3', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('listrik_30', function ($query, $keyword) {
                $query->where('listrik_30', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('listrik_100', function ($query, $keyword) {
                $query->where('listrik_100', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('frekuensi_3', function ($query, $keyword) {
                $query->where('frekuensi_3', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('frekuensi_30', function ($query, $keyword) {
                $query->where('frekuensi_30', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('frekuensi_100', function ($query, $keyword) {
                $query->where('frekuensi_100', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganMedanLM::where('id', $request->id)->first();

                MedanLmHeader::where('no_sampel', $request->no_sampel_lama)
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
            $data = DataLapanganMedanLM::where('id', $request->id)->first();
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
                // Cek di Tabel Parameter
                $parameter = Parameter::where('nama_lab', $parameterValue)->first();

                // if ($data->parameter == "Power Density") {
                //     $magnet_3 = json_decode($data->magnet_3);
                //     $magnet_30 = json_decode($data->magnet_30);
                //     $magnet_100 = json_decode($data->magnet_100);
                //     $listrik_3 = json_decode($data->listrik_3);
                //     $listrik_30 = json_decode($data->listrik_30);
                //     $listrik_100 = json_decode($data->listrik_100);

                //     $totmagnet_3 = count(array_keys($magnet_3));
                //     $totmagnet_30 = count(array_keys($magnet_30));
                //     $totmagnet_100 = count(array_keys($magnet_100));
                //     $totlistrik_3 = count(array_keys($listrik_3));
                //     $totlistrik_30 = count(array_keys($listrik_30));
                //     $totlistrik_100 = count(array_keys($listrik_100));

                //     $nilmagnet_3 = 0;
                //     $nilmagnet_30 = 0;
                //     $nilmagnet_100 = 0;
                //     $nillistrik_3 = 0;
                //     $nillistrik_30 = 0;
                //     $nillistrik_100 = 0;

                //     foreach ($magnet_3 as $idx => $val) {
                //         foreach ($val as $k => $v) {
                //             $nilmagnet_3 += $v;
                //         }
                //     }
                //     foreach ($magnet_30 as $idx => $val) {
                //         foreach ($val as $k => $v) {
                //             $nilmagnet_30 += $v;
                //         }
                //     }
                //     foreach ($magnet_100 as $idx => $val) {
                //         foreach ($val as $k => $v) {
                //             $nilmagnet_100 += $v;
                //         }
                //     }
                //     foreach ($listrik_3 as $idx => $val) {
                //         foreach ($val as $k => $v) {
                //             $nillistrik_3 += $v;
                //         }
                //     }
                //     foreach ($listrik_30 as $idx => $val) {
                //         foreach ($val as $k => $v) {
                //             $nillistrik_30 += $v;
                //         }
                //     }
                //     foreach ($listrik_100 as $idx => $val) {
                //         foreach ($val as $k => $v) {
                //             $nillistrik_100 += $v;
                //         }
                //     }


                //     $nilmagnet_3_ = $nilmagnet_3 / $totmagnet_3;
                //     $nilmagnet_30_ = $nilmagnet_30 / $totmagnet_30;
                //     $nilmagnet_100_ = $nilmagnet_100 / $totmagnet_100;

                //     // Menghitung total nilai magnet
                //     $totalNilaiMagnet = $nilmagnet_3_ + $nilmagnet_30_ + $nilmagnet_100_;

                //     // Menghitung jumlah elemen
                //     $jumlahNilai = 3; // Karena ada 3 nilai

                //     // Menghitung rata-rata Magnet
                //     $rataRataMagnet = $totalNilaiMagnet / $jumlahNilai;

                //     $nillistrik_3_ = $nillistrik_3 / $totlistrik_3;
                //     $nillistrik_30_ = $nillistrik_30 / $totlistrik_30;
                //     $nillistrik_100_ = $nillistrik_100 / $totlistrik_100;

                //     // Menjumlahkan hasil
                //     $total_nillistrik = $nillistrik_3_ + $nillistrik_30_ + $nillistrik_100_;

                //     // Menghitung rata-rata
                //     $rata_rata_nillistrik = $total_nillistrik / 3;

                //     // HASIL KESELURUHAN
                //     $MagnitTesla = $rataRataMagnet / 1000000;


                //     $medanMagnitAm = $MagnitTesla * 797700;
                //     // Menampilkan hasil

                //     // Menghitung Hasil Watt
                //     $HasilWatt = $rata_rata_nillistrik * $medanMagnitAm;

                //     // Pastikan hasil akhir tidak dibulatkan
                //     $hasilmWat = $HasilWatt / 10;

                //     $formattedHasilmWat = number_format($hasilmWat, 4);
                //     $formattedHasilWat = number_format($HasilWatt, 4);
                //     $formattedHasilmagnet = number_format($medanMagnitAm, 4);

                //     // $hasil = json_encode(["Magnet_3" => $nilmagnet_3_, "Magnet_30" => $nilmagnet_30_, "Magnet_100" =>$nilmagnet_100_, "Listrik_3" => $nillistrik_3_, "Listrik_30" => $nillistrik_30_, "Listrik_100" =>$nillistrik_100_]);
                //     $hasil = json_encode([
                //         "medan_magnet_am" => $formattedHasilmagnet, 
                //         "hasil_watt" => $formattedHasilWat, 
                //         "hasil_mwatt" => $formattedHasilmWat
                //     ]);
                //     $function = Formula::where('id_parameter', $parameter->id)->where('is_active', true)->first()->function;
                //     $hasil = AnalystFormula::where('function', $function)
                //         ->where('data', $data)
                //         ->where('id_parameter', $parameter->id)
                //         ->process();
                // } else if ($data->parameter == "Medan Magnit Statis") {
                //     $magnet_3 = json_decode($data->magnet_3);
                //     $magnet_30 = json_decode($data->magnet_30);
                //     $magnet_100 = json_decode($data->magnet_100);

                //     $totmagnet_3 = count(array_keys($magnet_3));
                //     $totmagnet_30 = count(array_keys($magnet_30));
                //     $totmagnet_100 = count(array_keys($magnet_100));

                //     $nilmagnet_3 = 0;
                //     $nilmagnet_30 = 0;
                //     $nilmagnet_100 = 0;

                //     foreach ($magnet_3 as $idx => $val) {
                //         foreach ($val as $k => $v) {
                //             $nilmagnet_3 += $v;
                //         }
                //     }
                //     foreach ($magnet_30 as $idx => $val) {
                //         foreach ($val as $k => $v) {
                //             $nilmagnet_30 += $v;
                //         }
                //     }
                //     foreach ($magnet_100 as $idx => $val) {
                //         foreach ($val as $k => $v) {
                //             $nilmagnet_100 += $v;
                //         }
                //     }



                //     $nilmagnet_3_ = number_format($nilmagnet_3 / $totmagnet_3, 4);
                //     $nilmagnet_30_ = number_format($nilmagnet_30 / $totmagnet_30, 4);
                //     $nilmagnet_100_ = number_format($nilmagnet_100 / $totmagnet_100, 4);

                //     // Menghitung total nilai magnet
                //     $totalNilaiMagnet = $nilmagnet_3_ + $nilmagnet_30_ + $nilmagnet_100_;

                //     // Menghitung jumlah elemen
                //     $jumlahNilai = 3; // Karena ada 3 nilai

                //     // Menghitung rata-rata
                //     $rataRataMagnet = number_format($totalNilaiMagnet / $jumlahNilai, 4);

                //     // $hasil = json_encode(["Magnet_3" => $nilmagnet_3_, "Magnet_30" => $nilmagnet_30_, "Magnet_100" =>$nilmagnet_100_]);
                //     $hasil = json_encode([
                //         'medan_magnet' => $rataRataMagnet
                //     ]);
                //     $function = Formula::where('id_parameter', $parameter->id)->where('is_active', true)->first()->function;
                //     $hasil = AnalystFormula::where('function', $function)
                //         ->where('data', $data)
                //         ->where('id_parameter', $parameter->id)
                //         ->process();
                // } else if ($data->parameter == "Medan Listrik") {
                //     $listrik_3 = json_decode($data->listrik_3);
                //     $listrik_30 = json_decode($data->listrik_30);
                //     $listrik_100 = json_decode($data->listrik_100);
                //     $totlistrik_3 = count(array_keys($listrik_3));
                //     $totlistrik_30 = count(array_keys($listrik_30));
                //     $totlistrik_100 = count(array_keys($listrik_100));

                //     $nillistrik_3 = 0;
                //     $nillistrik_30 = 0;
                //     $nillistrik_100 = 0;

                //     foreach ($listrik_3 as $idx => $val) {
                //         foreach ($val as $k => $v) {
                //             $nillistrik_3 += $v;
                //         }
                //     }
                //     foreach ($listrik_30 as $idx => $val) {
                //         foreach ($val as $k => $v) {
                //             $nillistrik_30 += $v;
                //         }
                //     }
                //     foreach ($listrik_100 as $idx => $val) {
                //         foreach ($val as $k => $v) {
                //             $nillistrik_100 += $v;
                //         }
                //     }

                //     $nillistrik_3_ = number_format($nillistrik_3 / $totlistrik_3, 4);
                //     $nillistrik_30_ = number_format($nillistrik_30 / $totlistrik_30, 4);
                //     $nillistrik_100_ = number_format($nillistrik_100 / $totlistrik_100, 4);

                //     // Menjumlahkan hasil
                //     $total_nillistrik = $nillistrik_3_ + $nillistrik_30_ + $nillistrik_100_;
                //     // Menghitung rata-rata
                //     $rata_rata_nillistrik = $total_nillistrik / 3;
                //     // Format rata-rata
                //     $rata_rata_nillistrik = number_format($rata_rata_nillistrik, 4);

                //     // $hasil = json_encode(["Listrik_3" => $nillistrik_3_, "Listrik_30" => $nillistrik_30_, "Listrik_100" =>$nillistrik_100_]);
                //     $hasil = json_encode([
                //         'medan_listrik' => $rata_rata_nillistrik
                //     ]);
                // }

                $function = Formula::where('id_parameter', $parameter->id)->where('is_active', true)->first()->function;
                $data_parsing = $request->all();
                $data_parsing = (object) $data_parsing;
                $data_parsing->data_lapangan = collect($data)->toArray();
                $hasil = AnalystFormula::where('function', $function)
                    ->where('data', $data_parsing)
                    ->where('id_parameter', $parameter->id)
                    ->process();
                    
                $headuv = MedanLmHeader::where('no_sampel', $no_sample)->where('is_active', true)->first();
                $ws = WsValueUdara::where('no_sampel', $no_sample)->where('is_active', true)->first();
                $dataL = DataLapanganMedanLM::where('id', $request->id)->first();
                // dd($ws);
                if (empty($headuv)) {
                    $headuv = new MedanLmHeader;
                }

                if (empty($ws)) {
                    $ws = new WsValueUdara;
                }

                $headuv->no_sampel = $no_sample;
                $headuv->id_parameter = $parameter->id;
                $headuv->parameter = $parameter->nama_lab;
                $headuv->is_approve = true;
                $headuv->approved_by = $this->karyawan;
                $headuv->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $headuv->created_by = $this->karyawan;
                $headuv->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $headuv->save();

                $ws->id_medan_lm_header = $headuv->id;
                $ws->no_sampel = $no_sample;
                $ws->id_po = $po->id;
                $ws->hasil1 = json_encode($hasil);
                $ws->save();

                $dataL->is_approve = true;
                $dataL->approved_by = $this->karyawan;
                $dataL->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                // dd($dataL, $headuv, $ws);
                $dataL->save();

                app(NotificationFdlService::class)->sendApproveNotification('Medan Listrik & Magnet', $dataL->no_sampel, $this->karyawan, $data->created_by);

                DB::commit();
                return response()->json([
                    'message' => "FDL MEDAN Listrik & Magnet, No Sampel : $no_sample berhasil di Approve oleh : $this->karyawan",
                    'cat' => 1
                ], 200);

            } else {
                return response()->json([
                    'message' => "FDL MEDAN Listrik & Magnet, No Sampel : $no_sample gagal di Approve oleh : $this->karyawan",
                ], 401);
            }
            // DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            dd($e);
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }

    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganMedanLM::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve = false;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();
            // dd($data);

            // if($cek_sampler->pin_user!=null){
            //     $nama = $this->name;
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Reject oleh $nama";

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

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganMedanLM::where('id', $request->id)->first();
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
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Hapus oleh $nama";

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
                $data = DataLapanganMedanLM::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Unblocked for user',
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganMedanLM::where('id', $request->id)->first();
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
        $data = DataLapanganMedanLM::with('detail')->where('id', $request->id)->first();

        $this->resultx = 'get Detail FDL MEDAN LM PERSONAL success';

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
            'lokasi' => $data->lokasi,
            'parameter' => $data->parameter,
            'aktivitas_pekerja' => $data->aktivitas_pekerja,
            'sumber_radiasi' => $data->sumber_radiasi,
            'waktu_pemaparan' => $data->waktu_pemaparan,
            'waktu_pengukuran' => $data->waktu_pengukuran,
            'magnet_3' => $data->magnet_3,
            'magnet_30' => $data->magnet_30,
            'magnet_100' => $data->magnet_100,
            'listrik_3' => $data->listrik_3,
            'listrik_30' => $data->listrik_30,
            'listrik_100' => $data->listrik_100,
            'frekuensi_3' => $data->frekuensi_3,
            'frekuensi_30' => $data->frekuensi_30,
            'frekuensi_100' => $data->frekuensi_100,
            'koordinat' => $data->titik_koordinat,
            'foto_lokasi' => $data->foto_lokasi_sampel,
            'foto_lain' => $data->foto_lain,
            'status' => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganMedanLM::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganMedanLM::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Medan Listrik & Magnetik", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

}