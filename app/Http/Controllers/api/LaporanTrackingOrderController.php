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
    public function indexOrder(Request $request)
    {
        try {
            $statusSelesai = $request->input('status_selesai', 0);
            if ($statusSelesai === 'selesai' || $statusSelesai === '1' || $statusSelesai === 1 || $statusSelesai === true) {
                $statusSelesai = 1;
            } else {
                $statusSelesai = 0;
            }

            $subOd = DB::table('order_detail')
                ->select(
                    'no_order',
                    DB::raw("MIN(COALESCE(NULLIF(periode, ''), tanggal_sampling)) as periode_detail")
                )
                ->where('is_active', true)
                ->groupBy('no_order');

            $query = DB::table("order_berjalan as ob") 
                ->leftJoinSub($subOd, 'od', 'od.no_order', '=', 'ob.no_order')
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
                    'od.periode_detail as periode',
                    'ob.no_penawaran as no_quotation',
                    'ob.nama_perusahaan as nama_pelanggan',
                    'oh.konsultan as nama_konsultan',
                    'mk.nama_lengkap as sales_penanggung_jawab',
                ])
                ->where('ob.status_selesai', $statusSelesai)
                ->where(function ($q) {
                    $q->where('ob.jenis_order', 'NORMAL')
                      ->orWhereNull('ob.jenis_order');
                });

            if ($request->filled('periode')) {
                $periodeVal = $request->periode;
                $query->where('od.periode_detail', 'like', "{$periodeVal}%");
            }

            return Datatables::of($query)
                ->filterColumn('no_order', function ($query, $keyword) {
                    $query->where('ob.no_order', 'like', "%{$keyword}%");
                })
                ->filterColumn('persentase', function ($query, $keyword) {
                    $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(ob.dataOrderDetail, '$[0].persentase_lhp_selesai')) like ?", ["%{$keyword}%"]);
                })
                ->filterColumn('periode', function ($query, $keyword) {
                    $query->where('od.periode_detail', 'like', "%{$keyword}%");
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

    public function indexOrderKontrak(Request $request)
    {
        try {
            $statusSelesai = $request->input('status_selesai', 0);
            $targetSelesai = ($statusSelesai === 'selesai' || $statusSelesai === '1' || $statusSelesai === 1 || $statusSelesai === true);

            $query = DB::table("order_berjalan as ob") 
                ->leftJoin("order_header as oh", "oh.no_order", "=", "ob.no_order")
                ->leftJoin("master_karyawan as mk", "mk.id", "=", "ob.sales_id")
                ->select([
                    'ob.id',
                    'ob.no_order',
                    'ob.no_penawaran as no_quotation',
                    'ob.nama_perusahaan as nama_pelanggan',
                    'oh.konsultan as nama_konsultan',
                    'mk.nama_lengkap as sales_penanggung_jawab',
                    'ob.dataOrderDetail',
                    'ob.tgl_order',
                ])
                ->where('ob.jenis_order', 'KONTRAK');

            $periodFilter = $request->input('periode');
            if (!empty($periodFilter)) {
                if (strlen($periodFilter) === 7) {
                    $query->where('ob.dataOrderDetail', 'like', "%\"{$periodFilter}\"%");
                } else {
                    $query->where('ob.dataOrderDetail', 'like', "%\"{$periodFilter}-%");
                }
            }

            $orders = $query->get();
            $rows = collect();

            foreach ($orders as $ob) {
                $details = is_string($ob->dataOrderDetail) ? json_decode($ob->dataOrderDetail, true) : $ob->dataOrderDetail;
                if (is_array($details)) {
                    foreach ($details as $p) {
                        $isSelesai = !empty($p['status_selesai']);
                        if ($isSelesai === $targetSelesai) {
                            $periodeStr = $p['periode'] ?? '-';

                            if (!empty($periodFilter)) {
                                if (strlen($periodFilter) === 7) {
                                    if ($periodeStr !== $periodFilter) {
                                        continue;
                                    }
                                } else {
                                    $year = substr($periodeStr, 0, 4);
                                    if ($year != $periodFilter) {
                                        continue;
                                    }
                                }
                            }

                            $persentaseNum = isset($p['persentase_lhp_selesai']) ? round((float)$p['persentase_lhp_selesai']) : 0;
                            $proses = isset($p['jumlah_lhp_selesai']) && isset($p['jumlah_lhp']) ? " ({$p['jumlah_lhp_selesai']}/{$p['jumlah_lhp']})" : "";

                            $rows->push([
                                'id' => $ob->id,
                                'no_order' => $ob->no_order,
                                'persentase' => $persentaseNum . ' %' . $proses,
                                'periode' => $periodeStr,
                                'no_quotation' => $ob->no_quotation,
                                'nama_pelanggan' => $ob->nama_pelanggan,
                                'nama_konsultan' => $ob->nama_konsultan,
                                'sales_penanggung_jawab' => $ob->sales_penanggung_jawab,
                            ]);
                        }
                    }
                }
            }

            return Datatables::of($rows)->make(true);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

      public function detailOrder(Request $request)
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
                    $periode = $request->periode;
                    if (strtoupper($order->jenis_order) === 'KONTRAK' && !empty($periode) && !empty($order->dataOrderDetail)) {
                        $details = is_string($order->dataOrderDetail) ? json_decode($order->dataOrderDetail, true) : $order->dataOrderDetail;
                        if (is_array($details)) {
                            $filtered = array_values(array_filter($details, function ($item) use ($periode) {
                                return isset($item['periode']) && $item['periode'] === $periode;
                            }));
                            $order->dataOrderDetail = json_encode($filtered);
                        }
                    }

                    $jadwalQuery = DB::table('jadwal')
                        ->where('no_quotation', $order->no_penawaran)
                        ->where('is_active', true);

                    if (strtoupper($order->jenis_order) === 'KONTRAK' && !empty($periode)) {
                        $jadwalQuery->where('periode', $periode);
                    }

                    $listJadwal = $jadwalQuery->get();
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
                
                $tanggalSampling = ($firstLap && $firstLap->created_at) ? (string) $firstLap->created_at : ($orderDetail->tanggal_sampling ?? $orderDetail->tanggal_terima);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Detail step sampling berhasil ditemukan',
                    'type' => 'sampling',
                    'data' => [
                        'no_sampel' => $orderDetail->no_sampel,
                        'no_lhp' => $orderDetail->cfr,
                        'kategori' => $orderDetail->kategori_3,
                        'titik_lokasi' => $orderDetail->keterangan_1,
                        'tanggal_sampling' => $tanggalSampling,
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

            if ($type === 'drafting') {
                $orderDetail = OrderDetail::withAnyLhps()
                    ->withAnyDataLapangan()
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

                $dataLapangan = $orderDetail->any_data_lapangan;
                $firstLap = $dataLapangan ? $dataLapangan->first() : null;
                $tanggalSampling = ($firstLap && $firstLap->created_at) ? (string) $firstLap->created_at : ($orderDetail->tanggal_sampling ?? null);

                $details = [];

                if ($firstLhp) {
                    $relations = method_exists($firstLhp, 'getRelations') ? $firstLhp->getRelations() : [];
                    $rawItems = [];

                    foreach ($relations as $relName => $relationData) {
                        if (empty($relationData) || $relName === 'link') continue;

                        if (is_iterable($relationData)) {
                            foreach ($relationData as $item) {
                                if (!is_object($item)) continue;

                                $itemNoSampel = $item->no_sampel ?? $item->no_sample ?? null;
                                if ($noSampel && $itemNoSampel && $itemNoSampel != $noSampel) {
                                    continue;
                                }

                                $rawItems[] = $item;
                            }
                        } elseif (is_object($relationData)) {
                            $itemNoSampel = $relationData->no_sampel ?? $relationData->no_sample ?? null;
                            if (!$noSampel || !$itemNoSampel || $itemNoSampel == $noSampel) {
                                $rawItems[] = $relationData;
                            }
                        }
                    }

                    if (empty($rawItems)) {
                        $rawItems[] = $firstLhp;
                    }

                    foreach ($rawItems as $item) {
                        $namaParam = $item->nama_parameter ?? $item->parameter ?? $item->parameter_lab ?? $item->parameter_regulasi ?? $item->param ?? null;
                        $hasilUji = $item->hasil_uji ?? $item->hasil ?? null;

                        if ($namaParam !== null || $hasilUji !== null) {
                            $details[] = [
                                'nama_parameter' => $namaParam,
                                'hasil_uji' => $hasilUji,
                                'tanggal_sampling' => $tanggalSampling,
                            ];
                        }
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
                        'tanggal_sampling' => $tanggalSampling,
                        'detail' => $details,
                    ]
                ], 200);
            }

            // lhp
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