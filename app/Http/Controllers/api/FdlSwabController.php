<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganSwab;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

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

class FdlSwabController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganSwab::with('detail')->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
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
            ->filterColumn('suhu', function ($query, $keyword) {
                $query->where('suhu', 'like', '%' . $keyword . '%');
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

    public function indexApps(Request $request)
    {
        $data = DataLapanganSwab::with('detail')
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
                $data = DataLapanganSwab::where('id', $request->id)->first();
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
        if (isset($request->id) && $request->id != null) {

            $data = DataLapanganSwab::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now();
            $data->rejected_at = null;
            $data->rejected_by = null;
            $data->save();

            app(NotificationFdlService::class)->sendApproveNotification('Swab Test', $data->no_sampel, $this->karyawan, $data->created_by);

            return response()->json([
                'message' => "Data dengan No Sampel $no_sample Telah di Approve oleh $this->karyawan",
                'master_kategori' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => "Data dengan No Sampel $no_sample gagal di Approve"
            ], 401);
        }
    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganSwab::where('id', $request->id)->first();
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
                'message' => "Data dengan No Sampel $no_sample Telah di Reject oleh $this->karyawan",
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => "Data dengan No Sampel $no_sample Gagal di Reject"
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganSwab::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lokasi = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
            $foto_kondisi = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
            $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
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
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => "Data dengan No Sampel $no_sample Telah di Hapus oleh $this->karyawan",
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => "Data Dengan No Sampel $no_sample gagal di Hapus"
            ], 401);
        }
    }

    public function block(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            if ($request->is_blocked == true) {
                $data = DataLapanganSwab::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => "Data Dengan No Sampel $data->no_sampel Telah di Unblock oleh $this->karyawan",
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganSwab::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => "Data Dengan No Sampel $data->no_sampel Telah di Blocked oleh $this->karyawan",
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
        $data = DataLapanganSwab::with('detail')
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

            'kondisi_tempat_sampling' => $data->kondisi_tempat_sampling,
            'kondisi_sampel' => $data->kondisi_sampel,
            'luas_area_swab' => $data->luas_area_swab,
            'suhu' => $data->suhu,
            'kelembapan' => $data->kelembapan,
            'catatan' => $data->catatan,
            'tekanan_udara' => $data->tekanan_udara,
            'waktu_pengukuran' => $data->waktu_pengukuran,

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
        $data = DataLapanganSwab::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganSwab::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Swab Test", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
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