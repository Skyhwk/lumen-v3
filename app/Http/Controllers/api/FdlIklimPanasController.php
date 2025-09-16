<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganIklimPanas;
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

class FdlIklimPanasController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganIklimPanas::with('detail')->orderBy('id', 'desc');

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
            ->filterColumn('sumber_panas', function ($query, $keyword) {
                $query->where('sumber_panas', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('jarak_sumber_panas', function ($query, $keyword) {
                $query->where('jarak_sumber_panas', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('akumulasi_waktu_paparan', function ($query, $keyword) {
                $query->where('akumulasi_waktu_paparan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('waktu_kerja', function ($query, $keyword) {
                $query->where('waktu_kerja', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('shift_pengujian', function ($query, $keyword) {
                $query->where('shift_pengujian', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganIklimPanas::where('id', $request->id)->first();

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
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganIklimPanas::where('id', $request->id)->first();
                $no_sample = $data->no_sampel;
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


                $parameter = Parameter::where('nama_lab', $parameterValue)->where('id_kategori', 4)->where('is_active', true)->first();
                $data = DataLapanganIklimPanas::select('no_sampel', 'pengukuran', 'akumulasi_waktu_paparan', 'terpapar_panas_matahari', 'pakaian_yang_digunakan')
                    ->where('no_sampel', $no_sample)
                    ->get();

                $totalL = $data->count();
                $getTotalApprove = DataLapanganIklimPanas::where('no_sampel', $no_sample)
                    ->select(DB::raw('COUNT(no_sampel) AS total'))
                    ->where('is_approve', 1)
                    ->first();

                // dd($data);

                if ($getTotalApprove->total + 1 == $totalL) {

                    $function = Formula::where('id_parameter', $parameter->id)->where('is_active', true)->first()->function;


                    $data_kalkulasi = AnalystFormula::where('function', $function)
                        ->where('data', $data)
                        ->where('id_parameter', $parameter->id)
                        ->process();


                    if (!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
                        return (object) [
                            'message' => 'Formula is Coming Soon parameter : ' . $request->parameter . '',
                            'status' => 404
                        ];
                    }

                    $headUdara = IklimHeader::where('no_sampel', $no_sample)->where('is_active', true)->first();
                    $ws = WsValueUdara::where('no_sampel', $no_sample)->where('is_active', true)->first();
                    $data = DataLapanganIklimPanas::where('id', $request->id)->where('is_approve', 0)->first();

                    if (empty($headUdara)) {
                        $headUdara = new IklimHeader;
                        $ws = new WsValueUdara;
                    }

                    $headUdara->no_sampel = $no_sample;
                    // $headUdara->id_po = $dat->id_po;
                    $headUdara->id_parameter = $parameter->id;
                    $headUdara->parameter = $parameter->nama_lab;
                    $headUdara->is_approve = true;
                    // $headUdara->lhps = true;
                    $headUdara->approved_by = $this->karyawan;
                    $headUdara->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $headUdara->created_by = $this->karyawan;
                    $headUdara->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $headUdara->save();

                    $ws->id_iklim_header = $headUdara->id;
                    $ws->no_sampel = $no_sample;
                    $ws->id_po = $po->id;
                    $ws->hasil1 = json_encode($data_kalkulasi['hasil']);
                    $ws->hasil2 = json_encode($data_kalkulasi['hasil_2']);
                    $ws->save();

                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    // dd($headUdara, $ws, $data);
                    $data->save();

                } else {
                    $data = DataLapanganIklimPanas::where('id', $request->id)->where('is_approve', 0)->first();

                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now();
                    $data->save();

                }

                app(NotificationFdlService::class)->sendApproveNotification("Iklim Panas pada Shift $data->shift_pengujian", $data->no_sampel, $this->karyawan, $data->created_by);

                DB::commit();
                return response()->json([
                    'message' => "Data FDL IKLIM PANAS dengan $no_sample berhasil di Approve oleh $this->karyawan",
                    'cat' => 1
                ], 200);

            }
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }


    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganIklimPanas::where('id', $request->id)->first();
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
            $data = DataLapanganIklimPanas::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Iklim Panas pada Shift $data->shift_pengujian", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
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
            $data = DataLapanganIklimPanas::where('id', $request->id)->first();
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
                $data = DataLapanganIklimPanas::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Unblocked for user',
                    'master_kategori' => 4
                ], 200);
            } else {
                $data = DataLapanganIklimPanas::where('id', $request->id)->first();
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
        $data = DataLapanganIklimPanas::with('detail')
            ->where('id', $request->id)
            ->first();

        $this->resultx = 'get Detail FDL IKLIM PANAS Personal Berhasil';

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
            'lokasi' => $data->lokasi,
            'sumber_panas' => $data->sumber_panas,
            'jarak_sumber_panas' => $data->jarak_sumber_panas,
            'akumulasi_waktu_paparan' => $data->akumulasi_waktu_paparan,
            'waktu_kerja' => $data->waktu_kerja,
            'jam_awal_pengukuran' => $data->jam_awal_pengukuran,
            'shift' => $data->shift,
            'tac_in' => $data->tac_in,
            'tac_out' => $data->tac_out,
            'tgc_in' => $data->tgc_in,
            'tgc_out' => $data->tgc_out,
            'wbtgc_in' => $data->wbtgc_in,
            'wbtgc_out' => $data->wbtgc_out,
            'rh_in' => $data->rh_in,
            'rh_out' => $data->rh_out,
            // 'ventilasi'            => $data->ventilasi,
            'pakaian_yang_digunakan' => $data->pakaian_yang_digunakan,
            'terpapar_panas_matahari' => $data->terpapar_panas_matahari,
            'jam_akhir_pengukuran' => $data->jam_akhir_pengukuran,
            'pengukuran' => $data->pengukuran,
            'tipe_alat' => $data->tipe_alat,
            'aktifitas' => $data->aktifitas,
            'koordinat' => $data->titik_koordinat,
            'foto_lok' => $data->foto_lokasi_sampel,
            'foto_lain' => $data->foto_lain,
            'status' => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganIklimPanas::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}