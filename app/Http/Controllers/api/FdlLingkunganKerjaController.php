<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganLingkunganKerja;
use App\Models\DetailLingkunganKerja;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\LingkunganHeader;
use App\Models\WsValueLingkungan;

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

class FdlLingkunganKerjaController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganLingkunganKerja::with('detail', 'detailLingkunganKerja')->orderBy('id', 'desc');

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
            ->filterColumn('detail.kategori_3', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_3', 'like', '%' . $keyword . '%');
                });
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if ($request->id != null && $request->id != '') {
            DB::beginTransaction();
            try {
                $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();
                if ($data != null) {
                    $data->no_sampel = $request->no_sampel_baru;
                    $data->no_sampel_lama = $request->no_sampel_lama;
                    $data->updated_by = $this->karyawan;
                    $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    $data_detail = DetailLingkunganKerja::where('no_sampel', $request->no_sampel_lama)->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama,
                    ]);

                    LingkunganHeader::where('no_sampel', $request->no_sampel_lama)
                        ->update(
                            [
                                'no_sampel' => $request->no_sampel_baru,
                                'no_sampel_lama' => $request->no_sampel_lama
                            ]
                        );

                    WsValueLingkungan::where('no_sampel', $request->no_sampel_lama)
                        ->update(
                            [
                                'no_sampel' => $request->no_sampel_baru,
                                'no_sampel_lama' => $request->no_sampel_lama
                            ]
                        );

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
                        'status' => 'success',
                        'message' => 'Data no sampel ' . $request->no_sampel_lama . ' berhasil diubah menjadi ' . $request->no_sampel_baru
                    ]);
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengubah data ' . $th->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ], 401);
        }
    }

    public function approveData(Request $request)
    {
        if ($request->id != null && $request->id != '') {
            DB::beginTransaction();
            try {
                $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();
                if ($data != null) {
                    $data->is_approve = true;
                    $data->approved_by = $this->karyawan;
                    $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    $data_detail = DetailLingkunganKerja::where('no_sampel', $data->no_sampel)->update([
                        'is_approve' => true,
                        'approved_by' => $this->karyawan,
                        'approved_at' => Carbon::now()->format('Y-m-d H:i:s')
                    ]);

                    app(NotificationFdlService::class)->sendApproveNotification('Lingkungan Kerja', $data->no_sampel, $this->karyawan, $data->created_by);

                    DB::commit();
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Data no sampel ' . $data->no_sampel . ' berhasil diapprove'
                    ]);
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal melakukan approve ' . $th->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ], 401);
        }
    }

    public function reject(Request $request)
    {
        if ($request->id != null && $request->id != '') {
            DB::beginTransaction();
            try {
                $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();
                if ($data != null) {
                    $data->is_approve = false;
                    $data->approved_by = null;
                    $data->approved_at = null;
                    $data->rejected_by = $this->karyawan;
                    $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    $data_detail = DetailLingkunganKerja::where('no_sampel', $data->no_sampel)->update([
                        'is_approve' => false,
                        'approved_by' => null,
                        'approved_at' => null,
                        'rejected_by' => $this->karyawan,
                        'rejected_at' => Carbon::now()->format('Y-m-d H:i:s')
                    ]);

                    DB::commit();
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Data no sampel ' . $data->no_sampel . ' berhasil direject'
                    ]);
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal melakukan reject ' . $th->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ], 401);
        }
    }

    public function blockData(Request $request)
    {
        if ($request->id != null && $request->id != '') {
            DB::beginTransaction();
            try {
                if ($request->is_blocked == true) {
                    $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();
                    $data->is_blocked = true;
                    $data->blocked_by = $this->karyawan;
                    $data->blocked_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();

                    $data_detail = DetailLingkunganKerja::where('no_sampel', $data->no_sampel)->update([
                        'is_blocked' => true,
                        'blocked_by' => $this->karyawan,
                        'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')
                    ]);

                    DB::commit();
                    return response()->json([
                        'message' => 'Data no sample ' . $data->no_sampel . ' telah di block untuk user'
                    ], 200);
                } else {
                    $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();
                    $data->is_blocked = false;
                    $data->blocked_by = null;
                    $data->blocked_at = null;
                    $data->save();

                    $data_detail = DetailLingkunganKerja::where('no_sampel', $data->no_sampel)->update([
                        'is_blocked' => false,
                        'blocked_by' => null,
                        'blocked_at' => null
                    ]);

                    DB::commit();
                    return response()->json([
                        'message' => 'Data no sample ' . $data->no_sampel . ' telah di unblock untuk user'
                    ], 200);
                }
            } catch (\Throwable $th) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal melakukan reject ' . $th->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ], 401);
        }
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganLingkunganKerja::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganLingkunganKerja::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Lingkungan Kerja", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
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