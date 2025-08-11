<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganGetaranPersonal;
use App\Models\OrderDetail;
use App\Models\WsValueUdara;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\GetaranHeader;

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


class FdlGetaranPersonalController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganGetaranPersonal::with('detail')->orderBy('id', 'desc');

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
            ->filterColumn('metode', function ($query, $keyword) {
                $query->where('metode', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('sumber_getaran', function ($query, $keyword) {
                $query->where('sumber_getaran', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('posisi_pengukuran', function ($query, $keyword) {
                $query->where('posisi_pengukuran', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('durasi_paparan', function ($query, $keyword) {
                $query->where('durasi_paparan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('kondisi', function ($query, $keyword) {
                $query->where('kondisi', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('intensitas', function ($query, $keyword) {
                $query->where('intensitas', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganGetaranPersonal::where('id', $request->id)->first();

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

    // 15/06/2025

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::BeginTransaction();
            try {
                $data = DataLapanganGetaranPersonal::where('id', $request->id)->first();
                $no_sample = $data->no_sampel;
                $po = OrderDetail::where('no_sampel', $data->no_sampel)->first();
                if ($po) {
                    $decoded = json_decode($po->parameter, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $parts = explode(';', $decoded[0] ?? '');

                        $parameterValue = $parts[1] ?? 'Data tidak valid';

                    } else {
                        dd("Parameter tidak valid atau bukan JSON");
                    }

                } else {
                    dd("OrderDetail tidak ditemukan");
                }

                $param = Parameter::where('nama_lab', $parameterValue)->first();
                $function = Formula::where('id_parameter', $param->id)->where('is_active', true)->first()->function;
                $data_parsing = $data;

                $data_kalkulasi = AnalystFormula::where('function', $function)
                    ->where('data', $data_parsing)
                    ->where('id_parameter', $param->id)
                    ->process();


                if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
                    return (object) [
                        'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
                        'status' => 404
                    ];
                }

                // $hasil = json_encode(["X" => $xx, "Y" => $yy, "Z" => $zz]);
                $headGet = GetaranHeader::where('no_sampel', $no_sample)->where('is_active', 1)->first();
                $ws = WsValueUdara::where('no_sampel', $no_sample)->where('is_active', 1)->first();

                if (empty($headGet)) {
                    $headGet = new GetaranHeader;
                    $ws = new WsValueUdara;
                }
                $headGet->no_sampel = $no_sample;
                // $headGet->id_po = $po->id;
                $headGet->id_parameter = $param->id;
                $headGet->parameter = $param->nama_lab;
                $headGet->is_approve = 1;
                $headGet->approved_by = $this->karyawan;
                $headGet->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $headGet->created_by = $this->karyawan;
                $headGet->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $headGet->save();

                $ws->id_getaran_header = $headGet->id;
                $ws->no_sampel = $no_sample;
                $ws->id_po = $po->id;
                $ws->hasil1 = $data_kalkulasi['hasil'];
                $ws->save();

                $data->is_approve = 1;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                // dd($data, $headGet, $ws);
                $data->save();

                app(NotificationFdlService::class)->sendApproveNotification('Getaran Personal', $data->no_sampel, $this->karyawan, $data->created_by);

                DB::commit();
                return response()->json([
                    'message' => 'Data has ben Approved',
                    'cat' => 1
                ], 200);
            } catch (\Throwable $th) {
                return response()->json([
                    'error' => $th->getMessage(),
                    'line' => $th->getLine(),
                    'file' => $th->getFile()
                ], 401);
            }

        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    // public function approve(Request $request)
    // {
    //     if (isset($request->id) && $request->id != null) {
    //         DB::BeginTransaction();
    //         try {
    //             $data = DataLapanganGetaranPersonal::where('id', $request->id)->first();
    //             $no_sample = $data->no_sampel;
    //             $po = OrderDetail::where('no_sampel', $data->no_sampel)->first();
    //             if ($po) {
    //                 // Decode parameter jika dalam format JSON
    //                 $decoded = json_decode($po->parameter, true);

    //                 // Pastikan JSON ter-decode dengan benar dan berisi data
    //                 if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
    //                     // Ambil elemen pertama dari array hasil decode
    //                     $parts = explode(';', $decoded[0] ?? '');

    //                     // Pastikan elemen kedua tersedia setelah explode
    //                     $parameterValue = $parts[1] ?? 'Data tidak valid';

    //                     // dd($parameterValue); // Output: "Pencahayaan"
    //                 } else {
    //                     dd("Parameter tidak valid atau bukan JSON");
    //                 }

    //             } else {
    //                 dd("OrderDetail tidak ditemukan");
    //             }

    //             $param = Parameter::where('nama_lab', $parameterValue)->first();
    //             $dataa = json_decode($data->pengukuran);
    //             $totData = count(array_keys(get_object_vars($dataa)));

    //             $x1 = 0;
    //             $x2 = 0;
    //             $y1 = 0;
    //             $y2 = 0;
    //             $z1 = 0;
    //             $z2 = 0;

    //             foreach ($dataa as $idx => $val) {

    //                 foreach ($val as $idf => $vale) {

    //                     if ($idf == "x1") {
    //                         $x1 += $vale;
    //                     } else if ($idf == "x2") {
    //                         $x2 += $vale;
    //                     } else if ($idf == "y1") {
    //                         $y1 += $vale;
    //                     } else if ($idf == "y2") {
    //                         $y2 += $vale;
    //                     } else if ($idf == "z1") {
    //                         $z1 += $vale;
    //                     } else if ($idf == "z2") {
    //                         $z2 += $vale;
    //                     }

    //                 }

    //             }

    //             $x_1 = number_format($x1 / $totData, 4);
    //             $x_2 = number_format($x2 / $totData, 4);
    //             $y_1 = number_format($y1 / $totData, 4);
    //             $y_2 = number_format($y2 / $totData, 4);
    //             $z_1 = number_format($z1 / $totData, 4);
    //             $z_2 = number_format($z2 / $totData, 4);

    //             $xx = round((($x_1 + $x_2) / 2), 4);
    //             $yy = round((($y_1 + $y_2) / 2), 4);
    //             $zz = round((($z_1 + $z_2) / 2), 4);

    //             if ($data->satKecX == "mm/s2") {
    //                 $xx = number_format($xx / 1000, 4);
    //             } else if ($data->satKecX == "m/s2") {
    //                 $xx = number_format($xx, 4);
    //             }

    //             if ($data->satKecY == "mm/s2") {
    //                 $yy = number_format($yy / 1000, 4);
    //             } else if ($data->satKecY == "m/s2") {
    //                 $yy = number_format($yy, 4);
    //             }

    //             if ($data->satKecZ == "mm/s2") {
    //                 $zz = number_format($zz / 1000, 4);
    //             } else if ($data->satKecZ == "m/s2") {
    //                 $zz = number_format($zz, 4);
    //             }

    //             $hasil = json_encode(["X" => $xx, "Y" => $yy, "Z" => $zz]);

    //             $headGet = GetaranHeader::where('no_sampel', $no_sample)->where('is_active', 1)->first();
    //             $ws = WsValueUdara::where('no_sampel', $no_sample)->where('is_active', 1)->first();

    //             if (empty($headGet)) {
    //                 $headGet = new GetaranHeader;
    //                 $ws = new WsValueUdara;
    //             }
    //             $headGet->no_sampel = $no_sample;
    //             // $headGet->id_po = $po->id;
    //             $headGet->id_parameter = $param->id;
    //             $headGet->parameter = $param->nama_lab;
    //             $headGet->is_approve = 1;
    //             $headGet->approved_by = $this->karyawan;
    //             $headGet->approved_at = Carbon::now()->format('Y-m-d H:i:s');
    //             $headGet->created_by = $this->karyawan;
    //             $headGet->created_at = Carbon::now()->format('Y-m-d H:i:s');
    //             $headGet->save();

    //             $ws->id_kebisingan_header = $headGet->id;
    //             $ws->no_sampel = $no_sample;
    //             $ws->id_po = $po->id;
    //             $ws->hasil1 = $hasil;
    //             $ws->save();

    //             $data->is_approve = 1;
    //             $data->approved_by = $this->karyawan;
    //             $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
    //             // dd($data, $headGet, $ws);
    //             $data->save();

    //             DB::commit();
    //             return response()->json([
    //                 'message' => 'Data has ben Approved',
    //                 'cat' => 1
    //             ], 200);
    //         } catch (\Throwable $th) {
    //             return response()->json([
    //                 'error' => $th->getMessage(),
    //                 'line' => $th->getLine()
    //             ], 401);
    //         }

    //     } else {
    //         return response()->json([
    //             'message' => 'Gagal Approve'
    //         ], 401);
    //     }
    // }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganGetaranPersonal::where('id', $request->id)->first();
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
            $data = DataLapanganGetaranPersonal::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification('Getaran Personal', $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
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
            $data = DataLapanganGetaranPersonal::where('id', $request->id)->first();
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
                $data = DataLapanganGetaranPersonal::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Unblocked for user',
                    'master_kategori' => 4
                ], 200);
            } else {
                $data = DataLapanganGetaranPersonal::where('id', $request->id)->first();
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
        $data = DataLapanganGetaranPersonal::with('detail')
            ->where('id', $request->id)
            ->first();

        $this->resultx = 'get Detail FDL Getaran Personal Berhasil';

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
            'waktu_pengukuran' => $data->waktu_pengukuran,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'message' => $this->resultx,
            'sumber_getaran' => $data->sumber_getaran,
            'metode' => $data->metode,
            'posisi_penguji' => $data->posisi_penguji,
            'durasi_paparan' => $data->durasi_paparan,
            'durasi_kerja' => $data->durasi_kerja,
            'kondisi' => $data->kondisi,
            'intensitas' => $data->intensitas,
            'pengukuran' => $data->pengukuran,
            'tangan' => $data->tangan,
            'pinggang' => $data->pinggang,
            'betis' => $data->betis,
            'satuan_kecepatan' => $data->satuan_kecepatan,
            'satuan_percepatan' => $data->satuan_percepatan,
            'satuan_kecepatan_x' => $data->satuan_kecepatan_x,
            'satuan_kecepatan_y' => $data->satuan_kecepatan_y,
            'satuan_kecepatan_z' => $data->satuan_kecepatan_z,
            'nama_pekerja' => $data->nama_pekerja,
            'jenis_pekerja' => $data->jenis_pekerja,
            'lokasi_unit' => $data->lokasi_unit,
            'alat_ukur' => $data->alat_ukur,
            'durasi_pengukuran' => $data->durasi_pengukuran,
            'adaptor' => $data->adaptor,
            'koordinat' => $data->titik_koordinat,
            'foto_lokasi' => $data->foto_lokasi_sampel,
            'foto_lain' => $data->foto_lain,
            'status' => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganGetaranPersonal::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}