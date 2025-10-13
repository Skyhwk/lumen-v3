<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganDebuPersonal;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\DebuPersonalHeader;
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

class FdlDebuPersonalController extends Controller
{
    public function index(Request $request){
        $this->autoBlock();
        $data = DataLapanganDebuPersonal::with('detail')->orderBy('id', 'desc');
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

    public function updateNoSampel(Request $request){
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganDebuPersonal::where('no_sampel', $request->no_sampel_lama)->get();

                foreach ($data as $item) {
                    $item->no_sampel = $request->no_sampel_baru;
                    $item->no_sampel_lama = $request->no_sampel_lama;
                    $item->updated_by = $this->karyawan;
                    $item->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $item->save(); // Save for each item
                }

                DebuPersonalHeader::where('no_sampel', $request->no_sampel_lama)
                    ->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]);

                WsValueUdara::where('no_sampel', $request->no_sampel_lama)
                    ->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]);


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

    public function approve(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = DataLapanganDebuPersonal::where('id', $request->id)->first();

            $data->is_approve  = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            DB::commit();

            app(NotificationFdlService::class)->sendApproveNotification(
                "Debu Personal pada Shift($data->shift)",
                $data->no_sampel,
                $this->karyawan,
                $data->created_by
            );

            return response()->json([
                'message' => 'Data berhasil di Approve',
                'master_kategori' => 1
            ], 200);

        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal approve ' . $th->getMessage(),
                'line'    => $th->getLine()
            ], 401);
        }
    }


    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDebuPersonal::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Debu Personal pada Shift($data->shift)", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function reject(Request $request){
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDebuPersonal::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve = false;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->save();
            // dd($data);

            // if($cek_sampler->pin_user!=null){
            //     $nama = $this->name;
            //     $txt = "FDL DEBU PERSONAL dengan No sample $no_sample Telah di Reject oleh $nama";
                
            //     $telegram = new Telegram();
            //     $telegram->send($cek_sampler->pin_user, $txt);
            // }

            return response()->json([
                'message' => 'Data berhasil di reject',
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Reject'
            ], 401);
        }
    }

    public function delete(Request $request){
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDebuPersonal::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lok = public_path() .'/dokumentasi/sampling/'. $data->foto_lokasi_sampel;
            $foto_alat = public_path() .'/dokumentasi/sampling/'. $data->foto_alat;
            $foto_lain = public_path() .'/dokumentasi/sampling/'.$data->foto_lain;
            if (is_file($foto_lok)) {
                unlink($foto_lok);
            }
            if (is_file($foto_alat)) {
                unlink($foto_alat);
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
                'message' => 'Data berhasil di hapus',
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
                $data = DataLapanganDebuPersonal::where('id', $request->id)->first();
                $data->is_blocked     = false;
                $data->blocked_by    = null;
                $data->blocked_at    = null;
                $data->save();
                return response()->json([
                    'message' => 'Data berhasil Unblocked for user',
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganDebuPersonal::where('id', $request->id)->first();
                $data->is_blocked     = true;
                $data->blocked_by    = $this->karyawan;
                $data->blocked_at    = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data berhasil di Blocked for user',
                    'master_kategori' => 1
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Melakukan Blocked'
            ], 401);
        }
    }

    public function detail(Request $request){
        $data = DataLapanganDebuPersonal::with('detail')
            ->where('id', $request->id)
            ->first();

        $this->resultx = 'get Detail FDL Cahaya Berhasil';

        return response()->json([
            'id'                => $data->id,
            'no_sample'         => $data->no_sampel,
            'no_order'          => $data->detail->no_order,
            'sub_kategori'      => explode('-', $data->detail->kategori_3)[1],
            'id_sub_kategori'   => explode('-', $data->detail->kategori_3)[0],
            'sampler'           => $data->created_by,
            'nama_perusahaan'   => $data->detail->nama_perusahaan,
            'keterangan'        => $data->keterangan,
            'keterangan_2'      => $data->keterangan_2,
            'latitude'          => $data->latitude,
            'longitude'         => $data->longitude,
            'nama_pekerja'      => $data->nama_pekerja,
            'divisi'            => $data->divisi,
            'suhu'              => $data->suhu,
            'kelembaban'        => $data->kelembaban,
            'tekanan_udara'     => $data->tekanan_udara,
            'shift'             => $data->shift,
            'aktivitas'         => $data->aktivitas,
            'apd'               => $data->apd,
            'jam_mulai'         => $data->jam_mulai,
            'jam_pengambilan'   => $data->jam_pengambilan,
            'flow'              => $data->flow,
            'jam_selesai'       => $data->jam_selesai,
            'total_waktu'       => $data->total_waktu,
            'koordinat'         => $data->titik_koordinat,
            'foto_lokasi'       => $data->foto_lokasi_sample,
            'foto_lain'         => $data->foto_lain,
            'foto_alat'         => $data->foto_alat,
            'status'            => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganDebuPersonal::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
} 