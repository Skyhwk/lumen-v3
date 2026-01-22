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

// SERVICE
use App\Services\AnalystFormula;
use App\Models\AnalystFormula as Formula;

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

                    } else {
                        dd("Parameter tidak valid atau bukan JSON");
                    }

                } else {
                    dd("OrderDetail tidak ditemukan");
                }

                $parameter = Parameter::where('nama_lab', $parameterValue)->first();
                

                $dataL = DataLapanganIklimDingin::select('no_sampel', 'pengukuran', 'shift_pengambilan', 'akumulasi_waktu_paparan')
                    ->where('no_sampel', $no_sample)->get();
                
                $totalL = $dataL->count();


                $getTotalApprove = DataLapanganIklimDingin::where('no_sampel', $no_sample)
                    ->select(DB::raw('COUNT(no_sampel) AS total'))
                    ->where('is_approve', true)
                    ->first();


                if ($getTotalApprove->total + 1 == $totalL) {
                    // 1. Ambil Formula
                    $formula = Formula::where('id_parameter', $parameter->id)
                        ->where('is_active', true)
                        ->first();

                    if (!$formula) {
                        return (object)[
                            'message' => 'Formula tidak ditemukan untuk parameter: ' . $parameter->id,
                            'status' => 404
                        ];
                    }

                    // 2. Kalkulasi Data
                    $data_kalkulasi = AnalystFormula::where('function', $formula->function)
                        ->where('data', $dataL)
                        ->where('id_parameter', $parameter->id)
                        ->process();

                    if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
                        return (object)[
                            'message' => 'Formula is Coming Soon parameter : ' . $request->parameter,
                            'status' => 404
                        ];
                    }

                    // 3. Update atau Create Header (IklimHeader)
                    // Kita cari berdasarkan no_sampel dan id_parameter agar tidak double row
                    $headUdara = IklimHeader::updateOrCreate(
                        [
                            'no_sampel'    => $no_sample,
                            'id_parameter' => $parameter->id,
                        ],
                        [
                            'parameter'              => $parameter->nama_lab,
                            'is_approve'             => true,
                            'rata_suhu'              => $data_kalkulasi['rataSuhu'],
                            'rata_kecepatan_angin'   => $data_kalkulasi['rataAngin'],
                            'approved_by'            => $this->karyawan,
                            'approved_at'            => Carbon::now(),
                            'created_by'             => $this->karyawan, // Tetap aman dengan updateOrCreate
                            'created_at'             => Carbon::now(),
                        ]
                    );

                    // 4. Update atau Create Value (WsValueUdara)
                    WsValueUdara::updateOrCreate(
                        [
                            'id_iklim_header' => $headUdara->id,
                            'no_sampel'       => $no_sample,
                        ],
                        [
                            'id_po'  => $po->id,
                            'hasil1' => $data_kalkulasi['hasil'],
                        ]
                    );

                }

                // Bagian ini dijalankan baik di 'if' maupun 'else' (DRY Principle)
                $data->is_approve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now();
                $data->save();
                
                DB::commit();
                return response()->json([
                    'message' => "FDL IKLIM DINGIN dengan No sample $no_sample Telah di Approve oleh $this->karyawan",
                    'cat' => 1
                ], 200);

                app(NotificationFdlService::class)->sendApproveNotification('Lingkungan Kerja', $data->no_sampel, $this->karyawan, $data->created_by);
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