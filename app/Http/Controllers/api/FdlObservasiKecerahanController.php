<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganKecerahan;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

// header
use App\Models\Colorimetri;

// WS
use App\Models\WsValueAir;

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

class FdlObservasiKecerahanController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganKecerahan::with('detail')->orderBy('id', 'desc');

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
                $data = DataLapanganKecerahan::where('no_sampel', $request->no_sampel_lama)->whereNull('no_sampel_lama')->get();

                $ws = WsValueAir::where('no_sampel', $request->no_sampel_lama)->get();

                if ($ws->isNotEmpty()) {
                    Colorimetri::where('no_sampel', $request->no_sampel_lama)->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]);
                }

                $ws = WsValueAir::where('no_sampel', $request->no_sampel_lama)->update([
                    'no_sampel' => $request->no_sampel_baru,
                    'no_sampel_lama' => $request->no_sampel_lama
                ]);

                // Jika data ditemukan (Collection berisi elemen)
                $data->each(function ($item) use ($request) {
                    $item->no_sampel = $request->no_sampel_baru;
                    $item->no_sampel_lama = $request->no_sampel_lama;
                    $item->updated_by = $this->karyawan;  // Pastikan $this->karyawan valid
                    $item->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $item->save();  // Simpan perubahan untuk setiap item
                });

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
            if (!isset($request->id)) {
                return response()->json(['message' => 'ID tidak ditemukan'], 400);
            }

            $data = DataLapanganKecerahan::find($request->id);
            if (!$data) {
                return response()->json(['message' => 'Data Lapangan tidak ditemukan'], 404);
            }

            $order_detail = OrderDetail::where('no_sampel', $data->no_sampel)->first();
            if (!$order_detail) {
                return response()->json(['message' => 'Order detail tidak ditemukan'], 404);
            }
            
            if ($data) {
                $header = new Colorimetri();
                $header->no_sampel = $data->no_sampel;
                $header->parameter = $data->parameter;
                $header->created_by = $this->karyawan;
                $header->created_at = Carbon::now();
                $header->template_stp = 6;
                $header->is_approved = 1;
                $header->approved_by = $this->karyawan;
                $header->approved_at = Carbon::now();
                $header->save();

                $ws = new WsValueAir();
                $ws->no_sampel = $data->no_sampel;
                $ws->id_colorimetri = $header->id;
                $ws->hasil = $data->nilai_kecerahan;
                $ws->save();
        
                $data->is_approve = 1;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now();
                $data->save();
            }
            
            app(NotificationFdlService::class)->sendApproveNotification('Observasi Kecerahan', $data->no_sampel, $this->karyawan, $data->created_by);

            DB::commit();

            return response()->json([
                'message' => 'Data has been approved'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }



    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKecerahan::where('id', $request->id)->first();

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
            $data = DataLapanganKecerahan::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
            $foto_lokasi_selatan = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_selatan;
            $foto_lokasi_timur = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_timur;
            $foto_lokasi_barat = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_barat;
            $foto_lokasi_utara = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_utara;
            if (is_file($foto_lokasi_selatan)) {
                unlink($foto_lokasi_selatan);
            }
            if (is_file($foto_lokasi_timur)) {
                unlink($foto_lokasi_timur);
            }
            if (is_file($foto_lokasi_barat)) {
                unlink($foto_lokasi_barat);
            }
            if (is_file($foto_lokasi_utara)) {
                unlink($foto_lokasi_utara);
            }
            $data->delete();

            return response()->json([
                'message' => 'Data has ben Delete',
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
                    $data = DataLapanganKecerahan::where('id', $request->id)->first();
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
                    $data = DataLapanganKecerahan::where('id', $request->id)->first();
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

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganKecerahan::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Observasi Kecerahan", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganKecerahan::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}