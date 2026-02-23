<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganSinarUV;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\SinarUvHeader;
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

class FdlSinarUVController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganSinarUV::with('detail')->orderBy('id', 'desc');

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
            ->filterColumn('waktu_pemaparan', function ($query, $keyword) {
                $query->where('waktu_pemaparan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('waktu_pengukuran', function ($query, $keyword) {
                $query->where('waktu_pengukuran', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('mata', function ($query, $keyword) {
                $query->where('mata', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('betis', function ($query, $keyword) {
                $query->where('betis', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('siku', function ($query, $keyword) {
                $query->where('siku', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function indexApps(Request $request)
    {
        $data = DataLapanganSinarUV::with('detail')
            ->where('is_blocked', false)
            ->where('created_by', $this->karyawan)
            ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
            ->orderBy('created_at', 'desc');
        return Datatables::of($data)->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganSinarUV::where('id', $request->id)->first();

                SinarUvHeader::where('no_sampel', $request->no_sampel_lama)
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
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
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
            $data = DataLapanganSinarUV::where('id', $request->id)->first();
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

                        // dd($parameterValue); // Output: "Kebisingan"
                    } else {
                        dd("Parameter tidak valid atau bukan JSON");
                    }
                } else {
                    dd("OrderDetail tidak ditemukan");
                }

                $parameter = Parameter::where('nama_lab', $parameterValue)->first();

                $totalMenit = $data->waktu_pemaparan ?? null;
                // hitung NAB
                $nab = null;
                if ($totalMenit){
                    $nab = isset($totalMenit) ? $this->getNab($totalMenit) : null;
                }


                $function = Formula::where('id_parameter', $parameter->id)->where('is_active', true)->first()->function;
                $calculate = AnalystFormula::where('function', $function)
                    ->where('data', $data)
                    ->where('id_parameter', $parameter->id)
                    ->process();

                $headuv = SinarUvHeader::where('no_sampel', $no_sample)->where('is_active', true)->first();
                $ws = WsValueUdara::where('no_sampel', $no_sample)->where('is_active', true)->first();
                if (empty($headuv)) {
                    $headuv = new SinarUvHeader;
                    $ws = new WsValueUdara;
                }
                $headuv->no_sampel = $no_sample;
                $headuv->id_parameter = $parameter->id;
                $headuv->parameter = $parameter->nama_lab;
                $headuv->is_approved = true;
                // $headuv->lhps = true;
                $headuv->approved_by = $this->karyawan;
                $headuv->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $headuv->created_by = $this->karyawan;
                $headuv->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $headuv->save();

                $ws->id_sinaruv_header = $headuv->id;
                $ws->no_sampel = $no_sample;
                $ws->id_po = $data->detail->id;
                $ws->hasil1 = $calculate['hasil1']; // Mata
                $ws->hasil2 = $calculate['hasil2']; // Siku
                $ws->hasil3 = $calculate['hasil3']; // Betis
                $ws->nab = $nab;
                $ws->save();

                $data->is_approve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                app(NotificationFdlService::class)->sendApproveNotification('Sinar Uv', $data->no_sampel, $this->karyawan, $data->created_by);

                DB::commit();
                return response()->json([
                    'message' => "Data FDL SINAR UV dengan No Sampel $no_sample berhasil diapprove oleh $this->karyawan",
                    'cat' => 1
                ], 200);

            } else {
                return response()->json([
                    'message' => "Data FDL SINAR UV dengan No Sampel $no_sample gagal diapprove oleh $this->karyawan",
                ], 401);
            }
            // DB::commit();
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
            $data = DataLapanganSinarUV::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve = false;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();

            return response()->json([
                'message' => "Data FDL SINAR UV dengan No Sampel $no_sample berhasil direject oleh $this->karyawan",
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Reject'
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganSinarUV::where('id', $request->id)->first();
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

            return response()->json([
                'message' => "Data FDL SINAR UV dengan No Sampel $no_sample berhasil di hapus oleh $this->karyawan",
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
                $data = DataLapanganSinarUV::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => "Data Dengan No Sampel $data->no_sampel Telah di Unblock oleh $this->karyawan",
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganSinarUV::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => "Data Dengan No Sampel $data->no_sampel Telah di block oleh $this->karyawan",
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
        $data = DataLapanganSinarUV::with('detail')
            ->where('id', $request->id)
            ->first();

        $this->resultx = 'get Detail FDL Partikulat Meter Berhasil';

        return response()->json([
            'id' => $data->id,
            'no_sample' => $data->no_sampel,
            'no_order' => $data->detail->no_order,
            'sub_kategori' => explode('-', $data->detail->kategori_3)[1],
            'id_sub_kategori' => explode('-', $data->detail->kategori_3)[0],
            'sampler' => $data->created_by,
            'nama_perusahaan' => $data->detail->nama_perusahaan,
            'keterangan' => $data->keterangan,
            'keterangan_2' => $data->keterangan_2,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'message' => $this->resultx,

            'lokasi' => $data->lokasi,
            'aktivitas_pekerja' => $data->aktivitas_pekerja,
            'sumber_radiasi' => $data->sumber_radiasi,
            'waktu_pemaparan' => $data->waktu_pemaparan,
            'waktu_pengukuran' => $data->waktu_pengukuran,
            'mata' => $data->mata,
            'siku' => $data->siku,
            'betis' => $data->betis,

            'koordinat' => $data->titik_koordinat,
            'foto_lokasi' => $data->foto_lokasi_sampel,
            'foto_lain' => $data->foto_lain,
            'aktifitas' => $data->aktifitas,
            'status' => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganSinarUV::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganSinarUV::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Sinar UV", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    private function getNab($waktu)
    {
        // >= 8 Jam
        if ($waktu >= 480) {
            return 0.0001;
        } 
        // >4 - <8 Jam
        elseif ($waktu > 240 && $waktu < 480) {
            return 0.0001;
        }
        // >2 - <=4 Jam
        elseif ($waktu > 120 && $waktu <= 240) {
            return 0.0002;
        } 
        // >1 - <=2 Jam
        elseif ($waktu > 60 && $waktu <= 120) {
            return 0.0004;
        } 
        // >30 - <60 menit
        elseif ($waktu > 30 && $waktu <= 60) {
            return 0.0008;
        } 
        // >15 - <30 menit
        elseif ($waktu > 15 && $waktu <= 30) {
            return 0.0017;
        } 
        // >10 - <=15 menit
        elseif ($waktu > 10 && $waktu <= 15) {
            return 0.0033;
        } 
        // >5 - <=10 menit
        elseif ($waktu > 5 && $waktu <= 10) {
            return 0.005;
        } 
        // >1 - <=5 menit
        elseif ($waktu > 1 && $waktu <= 5) {
            return 0.01;
        } 
        // >30 detik - <1 menit
        elseif ($waktu > 0.5 && $waktu <= 1) {
            return 0.05;
        } 
        // >10 - <30 detik
        elseif ($waktu > 0.1667 && $waktu <= 0.5) {
            return 0.1;
        }
        // >1 - <=10 detik
        elseif ($waktu > 0.0167 && $waktu <= 0.1667) {
            return 0.3;
        } 
        // >0.5 - <=1 detik
        elseif ($waktu > 0.0083 && $waktu <= 0.0167) {
            return 3;
        } 
        // >0.1 - <=0.5 detik
        elseif ($waktu > 0.0017 && $waktu <= 0.0083) {
            return 6;
        } 
        // 0.1 detik
        elseif ($waktu == 0.0017) {
            return 30;
        }

        return null;
    }
}