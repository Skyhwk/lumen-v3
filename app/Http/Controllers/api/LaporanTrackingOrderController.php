<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                    $q->where('ob.status_selesai', 0)
                      ->orWhereRaw("
                          CAST(
                              JSON_UNQUOTE(
                                  JSON_EXTRACT(ob.dataOrderDetail, '$[0].persentase_lhp_selesai')
                              ) AS UNSIGNED
                          ) < 100
                      ");
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
                    'ob.dataOrderDetail',
                    'ob.tgl_order as periode',
                    'ob.no_penawaran as no_quotation',
                    'ob.nama_perusahaan as nama_pelanggan',
                    'oh.konsultan as nama_konsultan',
                    'mk.nama_lengkap as sales_penanggung_jawab',
                ])
                ->where('ob.status_selesai', 1);

            if ($request->filled('periode')) {
                $query->whereYear('ob.tgl_order', $request->periode);
            }

            return Datatables::of($query)
                ->addColumn('persentase', function ($row) {
                    return '100%';
                })
                ->addColumn('proses_lhp', function ($row) {
                    if (empty($row->dataOrderDetail)) {
                        return '0/0';
                    }
                    $details = is_string($row->dataOrderDetail) 
                        ? json_decode($row->dataOrderDetail, true) 
                        : $row->dataOrderDetail;

                    if (!is_array($details) || empty($details)) {
                        return '0/0';
                    }

                    $totalLhp = 0;
                    $selesaiLhp = 0;

                    foreach ($details as $p) {
                        $totalLhp += (int) ($p['jumlah_lhp'] ?? 0);
                        $selesaiLhp += (int) ($p['jumlah_lhp_selesai'] ?? 0);
                    }

                    return $selesaiLhp . '/' . $totalLhp;
                })
                ->filterColumn('no_order', function ($query, $keyword) {
                    $query->where('ob.no_order', 'like', "%{$keyword}%");
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
}