<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganMicrobiologi;
use App\Models\DetailMicrobiologi;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\MicrobioHeader;
use App\Models\WsValueMicrobio;

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

class FdlMicrobiologiController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganMicrobiologi::with('detail')
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

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {

            $data = DataLapanganMicrobiologi::where('id', $request->id)->first();

            $no_sampel = $data->no_sampel;

            $data_detail = DetailMicrobiologi::where('no_sampel', $data->no_sampel)->update([
                'is_approve' => true,
                'approved_by' => $this->karyawan,
                'approved_at' => Carbon::now()->format('Y-m-d H:i:s')
            ]);

            $data->is_approve = true;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now();
            $data->rejected_at = null;
            $data->rejected_by = null;
            $data->save();

            app(NotificationFdlService::class)->sendApproveNotification('Microbiologi', $data->no_sampel, $this->karyawan, $data->created_by);

            return response()->json([
                'message' => "Data dengan No Sampel {$no_sampel} Telah di approve",
                'master_kategori' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => "Data dengan No Sampel {$no_sampel} gagal di approve"
            ], 401);
        }
    }

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganMicrobiologi::where('id', $request->id)->first();
            $no_sampel = $data->no_sampel;

            $data->is_approve = false;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();
            // dd($data);

            // if($detail_sampler->pin_user!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL AIR dengan No sample $no_sampel Telah di Reject oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($detail_sampler->pin_user, $txt);
            // }

            return response()->json([
                'message' => "Data dengan No Sampel {$no_sampel} Telah di Reject",
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => "Data dengan No Sampel {$no_sampel} gagal di Reject"
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) || $request->id != null || isset($request->shift) || $request->shift != null) {
            if ($request->no_sample == null) {
                return response()->json([
                    'message' => 'Aplikasi anda belum update...!'
                ], 401);
            }
            $data = DetailMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->get();
            dd($data);
            if ($request->tip == 1) {
                $cek = DetailMicrobiologi::where('id', $request->id)->first();
                if ($data->count() > 1) {
                    $cek->delete();
                    $this->resultx = "Fdl Microbio parameter $cek->parameter di no sample $cek->no_sample berhasil dihapus.!";
                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 1
                    ], 201);
                } else {
                    $cek2 = DataLapanganMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                    $cek->delete();
                    $cek2->delete();
                    $this->resultx = "Fdl Microbio parameter $cek->parameter di no sample $cek->no_sample berhasil dihapus.!";
                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 2
                    ], 201);
                }
            } else if ($request->tip == 2) {
                $cek = DetailMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->where('shift', $request->shift)->get();
                $shift = array();
                foreach ($data as $dat) {
                    $shift[$dat['shift_pengambilan']][] = $dat;
                }
                if (count($shift) > 1) {
                    $cek->each->delete();
                    $this->resultx = "Fdl Microbio shift $request->shift di no sample $request->no_sample berhasil dihapus.!";
                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 1
                    ], 201);
                } else {
                    $cek2 = DataLapanganMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                    $cek->each->delete();
                    $cek2->delete();
                    $this->resultx = "Fdl Microbio shift $request->shift di no sample $request->no_sample berhasil dihapus.!";
                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 2
                    ], 201);
                }
            } else if ($request->tip == 3) {
                $cek = DataLapanganMicrobiologi::where('id', $request->id)->first();
                $cek2 = DetailMictobiologi::where('no_sampel', strtoupper(trim($request->no_sample)))->delete();
                $cek->delete();
                $this->resultx = "Fdl Microbio no sampel $request->no_sample berhasil dihapus.!";
                return response()->json([
                    'message' => $this->resultx,
                ], 201);
            }
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
                $data = DataLapanganMicrobiologi::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => "Data dengan No Sampel {$data->no_sampel} berhasil di Unblock",
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganMicrobiologi::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => "Data dengan No Sampel {$data->no_sampel} berhasil di block",
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
        if ($request->tipe == 1) {
            $data = DataLapanganMicrobiologi::with('detail')->where('no_sampel', $request->no_sampel)->first();
            $this->resultx = 'get Detail sample lingkuhan hidup success';

            return response()->json([
                'no_sampel' => $data->no_sampel,
                'no_order' => $data->detail->no_order,
                'sub_kategori' => explode('-', $data->detail->kategori_3)[1],
                'id_sub_kategori' => explode('-', $data->detail->kategori_3)[0],
                'sampler' => $data->created_by,
                'nama_perusahaan' => $data->detail->nama_perusahaan,
            ], 200);

        } else if ($request->tipe == 2) {
            $data = DetailMicrobiologi::where('no_sampel', $request->no_sampel)->get();

            return response()->json([
                'data' => $data,
            ], 200);

        } else if ($request->tipe == 3) {
            $data = DetailMicrobiologi::where('id', $request->id)->with('detail')->first();
            $this->resultx = 'get Detail sample lapangan lingkungan hidup success';

            return response()->json([
                'data' => $data,
            ], 200);
        }
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganMicrobiologi::where('id', $request->id)->first();

                $data_detail = DetailMicrobiologi::where('no_sampel', $request->no_sampel_lama)->update([
                    'no_sampel' => $request->no_sampel_baru,
                    'no_sampel_lama' => $request->no_sampel_lama,
                ]);

                MicrobioHeader::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                WsValueMicrobio::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                $data->no_sampel = $request->no_sampel_baru;
                $data->no_sampel_lama = $request->no_sampel_lama;
                $data->updated_by = $this->karyawan;
                $data->updated_at = date('Y-m-d H:i:s');
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
                // dd($e);
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

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganMicrobiologi::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganMicrobiologi::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Microbiologi", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
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