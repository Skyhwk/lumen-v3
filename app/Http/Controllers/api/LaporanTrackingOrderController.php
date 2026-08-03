<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\OrderDetail;   
use Datatables;
use Exception;

class LaporanTrackingOrderController extends Controller
{
    public function indexOrderBerjalan(Request $request)
    {
        try {
            $query = DB::table("order_berjalan as ob") 
                ->leftJoin("order_header as oh", "oh.no_order", "=", "ob.no_order")
                ->leftJoin("master_karyawan as mk", "mk.id", "=", "ob.sales_id")
                ->select([
                    'ob.id',
                    'ob.no_order',
                    DB::raw("
                        CONCAT(
                            ROUND(
                                CAST(
                                    JSON_UNQUOTE(
                                        JSON_EXTRACT(ob.dataOrderDetail, '$[0].persentase_lhp_selesai')
                                    ) AS DECIMAL(10,6)
                                )
                            ),
                            ' %'
                        ) as persentase
                    "),
                    'ob.tgl_order as periode',
                    'ob.no_penawaran as no_quotation',
                    'ob.nama_perusahaan as nama_pelanggan',
                    'oh.konsultan as nama_konsultan',
                    'mk.nama_lengkap as sales_penanggung_jawab',
                ])
                ->where(function ($q) {
                    $q->where('ob.status_selesai', 0);
                    //   ->orWhereRaw("
                    //       CAST(
                    //           JSON_UNQUOTE(
                    //               JSON_EXTRACT(ob.dataOrderDetail, '$[0].persentase_lhp_selesai')
                    //           ) AS UNSIGNED
                    //       ) < 100
                    //   ");
                });

            if ($request->filled('periode')) {
                $query->whereYear('ob.tgl_order', $request->periode);
            }

            return Datatables::of($query)
                ->filterColumn('no_order', function ($query, $keyword) {
                    $query->where('ob.no_order', 'like', "%{$keyword}%");
                })
                ->filterColumn('persentase', function ($query, $keyword) {
                    $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(ob.dataOrderDetail, '$[0].persentase_lhp_selesai')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('periode', function ($query, $keyword) {
                    $query->where('ob.tgl_order', 'like', "%{$keyword}%");
                })
                ->filterColumn('no_quotation', function ($query, $keyword) {
                    $query->where('ob.no_penawaran', 'like', "%{$keyword}%");
                })
                ->filterColumn('nama_pelanggan', function ($query, $keyword) {
                    $query->where('ob.nama_perusahaan', 'like', "%{$keyword}%");
                })
                ->filterColumn('nama_konsultan', function ($query, $keyword) {
                    $query->where('oh.konsultan', 'like', "%{$keyword}%");
                })
                ->filterColumn('sales_penanggung_jawab', function ($query, $keyword) {
                    $query->where('mk.nama_lengkap', 'like', "%{$keyword}%");
                })
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function detailOrderBerjalan(Request $request)
    {
        try {
            $noOrder = $request->no_order;

            if (!empty($noOrder)) {
                $order = DB::table('order_berjalan as ob')
                    ->leftJoin("order_header as oh", "oh.no_order", "=", "ob.no_order")
                    ->leftJoin("master_karyawan as mk", "mk.id", "=", "ob.sales_id")
                    ->select([
                        'ob.*',
                        'oh.konsultan as nama_konsultan',
                        'mk.nama_lengkap as sales_penanggung_jawab',
                    ])
                    ->where('ob.no_order', $noOrder)
                    ->first();

                if ($order) {
                    $listJadwal = DB::table('jadwal')
                        ->where('no_quotation', $order->no_penawaran)
                        ->where('is_active', true)
                        ->get();
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Detail Order Berhasil Ditemukan',
                        'data' => [
                            'order' => $order,
                            'jadwal' => $listJadwal
                        ]
                    ], 200);
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'No Order Tidak Ditemukan'
            ], 404);
            
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

      public function indexOrderSelesai(Request $request)
    {
        try {
            $query = DB::table("order_berjalan as ob") 
                ->leftJoin("order_header as oh", "oh.no_order", "=", "ob.no_order")
                ->leftJoin("master_karyawan as mk", "mk.id", "=", "ob.sales_id")
                ->select([
                    'ob.id',
                    'ob.no_order',
                    DB::raw("
                        CONCAT(
                            ROUND(
                                CAST(
                                    JSON_UNQUOTE(
                                        JSON_EXTRACT(ob.dataOrderDetail, '$[0].persentase_lhp_selesai')
                                    ) AS DECIMAL(10,6)
                                )
                            ),
                            ' %'
                        ) as persentase
                    "),
                    'ob.tgl_order as periode',
                    'ob.no_penawaran as no_quotation',
                    'ob.nama_perusahaan as nama_pelanggan',
                    'oh.konsultan as nama_konsultan',
                    'mk.nama_lengkap as sales_penanggung_jawab',
                ])
                ->where(function ($q) {
                    $q->where('ob.status_selesai', 1);
                    //   ->orWhereRaw("
                    //       CAST(
                    //           JSON_UNQUOTE(
                    //               JSON_EXTRACT(ob.dataOrderDetail, '$[0].persentase_lhp_selesai')
                    //           ) AS UNSIGNED
                    //       ) = 100
                    //   ");
                });

            if ($request->filled('periode')) {
                $query->whereYear('ob.tgl_order', $request->periode);
            }

            return Datatables::of($query)
                ->filterColumn('no_order', function ($query, $keyword) {
                    $query->where('ob.no_order', 'like', "%{$keyword}%");
                })
                ->filterColumn('persentase', function ($query, $keyword) {
                    $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(ob.dataOrderDetail, '$[0].persentase_lhp_selesai')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('periode', function ($query, $keyword) {
                    $query->where('ob.tgl_order', 'like', "%{$keyword}%");
                })
                ->filterColumn('no_quotation', function ($query, $keyword) {
                    $query->where('ob.no_penawaran', 'like', "%{$keyword}%");
                })
                ->filterColumn('nama_pelanggan', function ($query, $keyword) {
                    $query->where('ob.nama_perusahaan', 'like', "%{$keyword}%");
                })
                ->filterColumn('nama_konsultan', function ($query, $keyword) {
                    $query->where('oh.konsultan', 'like', "%{$keyword}%");
                })
                ->filterColumn('sales_penanggung_jawab', function ($query, $keyword) {
                    $query->where('mk.nama_lengkap', 'like', "%{$keyword}%");
                })
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

     public function detailOrderSelesai(Request $request)
    {
        try {
            $noOrder = $request->no_order;

            if (!empty($noOrder)) {
                $order = DB::table('order_berjalan as ob')
                    ->leftJoin("order_header as oh", "oh.no_order", "=", "ob.no_order")
                    ->leftJoin("master_karyawan as mk", "mk.id", "=", "ob.sales_id")
                    ->select([
                        'ob.*',
                        'oh.konsultan as nama_konsultan',
                        'mk.nama_lengkap as sales_penanggung_jawab',
                    ])
                    ->where('ob.no_order', $noOrder)
                    ->first();

                if ($order) {
                    $listJadwal = DB::table('jadwal')
                        ->where('no_quotation', $order->no_penawaran)
                        ->where('is_active', true)
                        ->get();
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Detail Order Berhasil Ditemukan',
                        'data' => [
                            'order' => $order,
                            'jadwal' => $listJadwal
                        ]
                    ], 200);
                }
            }

            return response()->json([
                'status' => 'error',
                'message' => 'No Order Tidak Ditemukan'
            ], 404);
            
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

      public function detailStep(Request $request)
    {
        try {
            $type = $request->type; // 'sampling', 'analisa', 'drafting', 'lhp_release'
            $noSampel = $request->no_sampel;
            $noLhp = $request->no_lhp;
            if (empty($noSampel) && empty($noLhp)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Parameter no_sampel atau no_lhp wajib diisi'
                ], 400);
            }
     
            if ($type === 'sampling') {
                $orderDetail = OrderDetail::withAnyDataLapangan()
                    ->where('is_active', true)
                    ->where(function ($q) use ($noSampel, $noLhp) {
                        if ($noSampel) {
                            $q->where('no_sampel', $noSampel);
                        } else if ($noLhp) {
                            $q->where('cfr', $noLhp);
                        }
                    })
                    ->first();

                if (!$orderDetail) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Data sampel tidak ditemukan'
                    ], 404);
                }

                $dataLapangan = $orderDetail->any_data_lapangan;
                $firstLap = $dataLapangan ? $dataLapangan->first() : null;

                $dataLapanganFiltered = [];
                if ($firstLap) {
                    $dataLapanganFiltered[] = [
                        'created_at' => $firstLap->created_at ? (string) $firstLap->created_at : null,
                        'approved_by' => $firstLap->approved_by ?? null,
                    ];
                }
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Detail step sampling berhasil ditemukan',
                    'type' => 'sampling',
                    'data' => [
                        'no_sampel' => $orderDetail->no_sampel,
                        'no_lhp' => $orderDetail->cfr,
                        'kategori' => $orderDetail->kategori_3,
                        'titik_lokasi' => $orderDetail->keterangan_1,
                        'tanggal_sampling' => $orderDetail->tanggal_sampling ?? $orderDetail->tanggal_terima,
                        'data_lapangan' => $dataLapanganFiltered,
                    ]
                ], 200);
            }

            if ($type === 'analisa') {
                $orderDetail = OrderDetail::where('is_active', true)
                    ->where(function ($q) use ($noSampel, $noLhp) {
                        if ($noSampel) {
                            $q->where('no_sampel', $noSampel);
                        } else if ($noLhp) {
                            $q->where('cfr', $noLhp);
                        }
                    })
                    ->first();

                $noOrder = $orderDetail->no_order ?? $request->no_order ?? null;

                if (!$noOrder && $noSampel) {
                    $header = DB::table('ws_final_approval_header')
                        ->where('no_sampel', $noSampel)
                        ->first();
                    $noOrder = $header ? $header->no_order : null;
                }

                if (!$noOrder) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No. Order tidak ditemukan untuk sampel ini'
                    ], 404);
                }

                $analisaDetails = DB::table('ws_final_approval_detail as d')
                ->where('d.no_sampel', $orderDetail->no_sampel ?? $noSampel)
                    ->join('ws_final_approval_header as h', 'h.id', '=', 'd.ws_final_approval_header_id')
                    // ->where('h.no_order', $noOrder)
                    ->select([
                        'd.id',
                        'd.no_sampel',
                        'd.parameter_regulasi',
                        'd.parameter_lab',
                        'd.hasil',
                        'h.no_order',
                        'h.kategori',
                        'h.nama_titik',
                        'h.is_approved',
                        'h.approved_by',
                        'h.approved_at'
                    ])
                    ->get();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Detail step analisa berhasil ditemukan',
                    'type' => 'analisa',
                    'data' => [
                        'no_order' => $noOrder,
                        'no_sampel' => $orderDetail->no_sampel ?? $noSampel,
                        'no_lhp' => $orderDetail->cfr ?? $noLhp,
                        'kategori' => $orderDetail->kategori_3 ?? null,
                        'titik_lokasi' => $orderDetail->keterangan_1 ?? null,
                        'analisa_detail' => $analisaDetails
                    ]
                ], 200);
            }

            // ===== 3. STEP DRAFTING =====
            if ($type === 'drafting') {
                $orderDetail = OrderDetail::withAnyLhps()
                    ->where('is_active', true)
                    ->where(function ($q) use ($noSampel, $noLhp) {
                        if ($noSampel) {
                            $q->where('no_sampel', $noSampel);
                        } else if ($noLhp) {
                            $q->where('cfr', $noLhp);
                        }
                    })
                    ->first();

                if (!$orderDetail) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Data sampel tidak ditemukan'
                    ], 404);
                }

                $anyLhps = $orderDetail->any_lhps;
                $firstLhp = $anyLhps ? $anyLhps->first() : null;

                $details = [];
                foreach ($firstLhp->lhpsSwabTesDetail as $detail) {
                    if ($detail->no_sampel == $noSampel) {
                        $details = $detail;
                        break;
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Detail step drafting berhasil ditemukan',
                    'type' => 'drafting',
                    'data' => [
                        'no_sampel' => $orderDetail->no_sampel,
                        'no_lhp' => $orderDetail->cfr,
                        'kategori' => $orderDetail->kategori_3,
                        'titik_lokasi' => $orderDetail->keterangan_1,
                        'detail' => $details,
                    ]
                ], 200);
            }

            // ===== 4. STEP LHP RELEASE =====
            if ($type === 'lhp_release') {
                $orderDetail = OrderDetail::withAnyLhps()
                    ->where('is_active', true)
                    ->where(function ($q) use ($noSampel, $noLhp) {
                        if ($noSampel) {
                            $q->where('no_sampel', $noSampel);
                        } else if ($noLhp) {
                            $q->where('cfr', $noLhp);
                        }
                    })
                    ->first();

                if (!$orderDetail) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Data sampel tidak ditemukan'
                    ], 404);
                }

                $anyLhps = $orderDetail->any_lhps;
                $firstLhp = $anyLhps ? $anyLhps->first() : null;

                $releaseInfo = null;
                if ($firstLhp) {
                    $releaseInfo = [
                        'file_lhp' => $firstLhp->file_lhp ?? null,
                        'approved_by' => $firstLhp->approved_by ?? null,
                        'approved_at' => $firstLhp->approved_at ? (string) $firstLhp->approved_at : null,
                    ];
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Detail step LHP release berhasil ditemukan',
                    'type' => 'lhp_release',
                    'data' => [
                        'no_sampel' => $orderDetail->no_sampel,
                        'no_lhp' => $orderDetail->cfr,
                        'kategori' => $orderDetail->kategori_3,
                        'titik_lokasi' => $orderDetail->keterangan_1,
                        'release' => $releaseInfo,
                    ]
                ], 200);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Detail tidak tersedia.'
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}