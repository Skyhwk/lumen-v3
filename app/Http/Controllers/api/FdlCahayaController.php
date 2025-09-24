<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganCahaya;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\PencahayaanHeader;
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

class FdlCahayaController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganCahaya::with('detail')->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.tanggal_sampling', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('tanggal_sampling', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel_lama', function ($query, $keyword) {
                $query->where('no_sampel_lama', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.nama_perusahaan', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.kategori_2', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_2', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.kategori_3', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_3', 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganCahaya::where('id', $request->id)->first();

                PencahayaanHeader::where('no_sampel', $request->no_sampel_lama)
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
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganCahaya::where('id', $request->id)->first();

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

                $parameter = Parameter::where('nama_lab', $parameterValue)->first();


                $nilai = json_decode($data->pengukuran);
                $function = Formula::where('id_parameter', $parameter->id)->where('is_active', true)->first()->function;
                $data_parsing = $request->all();
                $data_parsing = (object)$data_parsing;
                $data_parsing->nilai = (array)$nilai;

                $data_kalkulasi = AnalystFormula::where('function', $function)
                    ->where('data', $data_parsing)
                    ->where('id_parameter', $parameter->id)
                    ->process();
                

                if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
                    return (object)[
                        'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
                        'status' => 404
                    ];
                }

                $headCaha = PencahayaanHeader::where('no_sampel', $no_sample)->first();
                $ws = WsValueUdara::where('no_sampel', $no_sample)->first();
                if (empty($headCaha)) {
                    $headCaha = new PencahayaanHeader;
                    $ws = new WsValueUdara;
                }

                $headCaha->no_sampel = $no_sample;
                $headCaha->id_parameter = $parameter->id;
                $headCaha->parameter = $parameter->nama_lab;
                $headCaha->is_approved = 1;
                $headCaha->lhps = 1;
                $headCaha->approved_by = $this->karyawan;
                $headCaha->approved_at = Carbon::now();
                $headCaha->created_by = $this->karyawan;
                $headCaha->created_at = Carbon::now();
                $headCaha->save();

                $ws->id_pencahayaan_header = $headCaha->id;
                $ws->no_sampel = $no_sample;
                $ws->id_po = $po->id;
                $ws->hasil1 = $data_kalkulasi['hasil'];
                $ws->satuan = $data_kalkulasi['satuan'];
                $ws->save();


                $data->is_approve = 1;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now();
                $data->save();

                app(NotificationFdlService::class)->sendApproveNotification('Cahaya', $data->no_sampel, $this->karyawan, $data->created_by);

                if ($parameter->id == 309) {
                    $check = DB::table("data_lapangan_cahaya")->where("no_sampel", $no_sample)->where('is_approve', 1)->first();
                    if (is_null($check)) {
                        return response()->json([
                            'message' => 'Data lapangan Sudah dilakukan approve.!'
                        ], 401);
                    }

                    $header = PencahayaanHeader::where('no_sampel', $no_sample)->first();
                    $header->is_approved = 1;
                    $header->approved_by = $this->karyawan;
                    $header->approved_at = Carbon::now();
                    $header->lhps = 1;
                    $header->save();


                    DB::commit();
                    return response()->json([
                        'message' => "Data Dengan No Sampel $no_sample Berhasil di Approve oleh $this->karyawan",
                        'cat' => 1
                    ], 200);

                } else {
                    return response()->json([
                        'message' => "Data Dengan No Sampel $no_sample Gagal di Approve oleh $this->karyawan"
                    ], 401);
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            dd($e);
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganCahaya::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification('Cahaya', $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganCahaya::where('id', $request->id)->first();
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

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganCahaya::where('id', $request->id)->first();
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
        DB::beginTransaction();
        try {
            if (isset($request->id) && $request->id != null) {
                if ($request->is_blocked == 1) {
                    $data = DataLapanganCahaya::where('id', $request->id)->first();
                    $data->is_blocked = false;
                    $data->blocked_by = null;
                    $data->blocked_at = null;
                    $data->save();
                    DB::commit();
                    return response()->json([
                        'message' => 'Data has ben Unblocked for user',
                        'master_kategori' => 1
                    ], 200);
                } else {
                    $data = DataLapanganCahaya::where('id', $request->id)->first();
                    $data->is_blocked = true;
                    $data->blocked_by = $this->karyawan;
                    $data->blocked_at = Carbon::now();
                    $data->save();
                    DB::commit();
                    return response()->json([
                        'message' => 'Data has ben Blocked for user',
                        'master_kategori' => 1
                    ], 200);
                }
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal Melakukan Blocked'
                ], 401);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json([
                'message' => 'Gagal Melakukan Blocked',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    public function detail(Request $request)
    {
        $data = DataLapanganCahaya::with('detail')
            ->where('id', $request->id)
            ->first();

        $this->resultx = 'get Detail FDL Cahaya Berhasil';

        return response()->json([
            'id' => $data->id,
            'no_sample' => $data->no_sampel,
            'no_order' => $data->detail->no_order,
            'kategori' => $data->kategori,
            'sampler' => $data->created_by,
            'nama_perusahaan' => $data->detail->nama_perusahaan,
            'keterangan' => $data->keterangan,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'message' => $this->resultx,
            'info_tambahan' => $data->informasi_tambahan,
            'jenis_tempat' => $data->jenis_tempat_alat_sensor,
            'waktu' => $data->waktu_pengambilan,
            'panjang' => $data->panjang,
            'lebar' => $data->lebar,
            'luas' => $data->luas,
            'jml_titik_p' => $data->jumlah_titik_pengujian,
            'titik_p_sampler' => $data->titik_pengujian_sampler,
            'jenis_cahaya' => $data->jenis_cahaya,
            'jenis_lampu' => $data->jenis_lampu,
            'jml_kerja' => $data->jumlah_tenaga_kerja,
            'mulai' => $data->jam_mulai_pengukuran,
            'pengukuran' => $data->pengukuran,
            'nilai_peng' => $data->nilai_pengukuran,
            'selesai' => $data->jam_selesai_pengukuran,
            'tikoor' => $data->titik_koordinat,
            'foto_lok' => $data->foto_lokasi_sampel,
            'foto_lain' => $data->foto_lain,
            'aktifitas' => $data->aktifitas,
            'status' => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganCahaya::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}