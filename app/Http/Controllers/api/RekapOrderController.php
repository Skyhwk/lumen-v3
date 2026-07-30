<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LinkLhp;
use App\Models\OrderDetail;
use Illuminate\Http\Request;

use Datatables;
use App\Models\OrderHeader;
use App\Models\CatatanLhpRilis;
use App\Services\GroupedCfrByLhp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
class RekapOrderController extends Controller
{
    // public function index(Request $request)
    // {
    //     $rekapOrder = OrderHeader::where('is_active', true)
    //         ->with(['orderDetail.trackingSatu', 'orderDetail.trackingDua'])
    //         ->withCount('orderDetail')
    //         ->whereDate('tanggal_order', $request->tanggal_order);

    //     return Datatables::of($rekapOrder)
    //         ->filterColumn('tipe_quotation', function ($query, $keyword) {
    //             if (stripos('kontrak', $keyword) !== false) {
    //                 $query->where('no_document', 'like', '%QTC%');
    //             } elseif (stripos('non kontrak', $keyword) !== false || stripos('non-kontrak', $keyword) !== false) {
    //                 $query->where('no_document', 'not like', '%QTC%');
    //             }
    //         })->make(true);
    // }

    public function index(Request $request)
    {
        // Subquery link_lhp
        $linkLhpQuery = LinkLhp::query();

        // Query utama
        $rekapOrder = DB::table('order_detail')
            ->selectRaw('
                order_detail.no_order,
                order_detail.no_quotation,
                GROUP_CONCAT(DISTINCT order_detail.cfr SEPARATOR ",") as cfr,
                COUNT(DISTINCT order_detail.cfr) AS total_cfr,
                order_detail.nama_perusahaan,
                order_detail.konsultan,
                order_detail.periode,
                order_detail.kontrak,
                link_lhp.is_completed,
                link_lhp.jumlah_lhp_rilis,
                MIN(order_detail.tanggal_sampling) as tanggal_sampling_min,
                MAX(order_detail.tanggal_sampling) as tanggal_sampling_max
            ')
            ->where('order_detail.is_active', true);

        if ($request->filled('is_completed')) {

            // Ambil kolom lengkap dari link_lhp
            $linkLhpQuery = LinkLhp::select(
                'no_order',
                'is_completed',
                'jumlah_lhp_rilis',
                'periode'
            );

            $rekapOrder = $rekapOrder->leftJoinSub($linkLhpQuery, 'link_lhp', function ($join) {
                $join->on('order_detail.no_order', '=', 'link_lhp.no_order');
            });

            if ($request->is_completed == 'true' || $request->is_completed == 1) {

                // Completed hanya yang completed
                $rekapOrder->where('link_lhp.is_completed', true);

            } else {

                // NOT completed → boleh punya link_lhp atau tidak
                $rekapOrder->where(function ($q) {
                    $q->whereNull('link_lhp.is_completed')
                    ->orWhere('link_lhp.is_completed', false);
                });
            }
        }

        // Hanya yang sudah ada LHP rilis
        $rekapOrder->where(function ($q) {
            $q->whereNotNull('link_lhp.jumlah_lhp_rilis')
            ->where('link_lhp.jumlah_lhp_rilis', '>', 0);
        });

        /** 
         * ===============================
         *         FILTER LOGIC
         * ===============================
         * Jika kontrak = C → filter by PERIODE
         * Jika kontrak != C → filter by tanggal_sampling_min LIKE
         */

        $rekapOrder->when($request->filled('tanggal_sampling'), function ($q) use ($request) {

            $periode = $request->tanggal_sampling;

            $q->where(function ($sub) use ($periode) {

                // Kontrak = C → filter periode
                $sub->where(function ($f) use ($periode) {
                    $f->where('order_detail.kontrak', 'C')
                    ->where('order_detail.periode', $periode)
                    ->where('link_lhp.periode', $periode);
                });

                // Kontrak != C → filter tanggal_sampling_min (HARUS HAVING)
                $sub->orWhere(function ($f) use ($periode) {
                    $f->where('order_detail.kontrak', '!=', 'C');
                });

            });
        });

        // Grouping
        $rekapOrder->groupByRaw('
            order_detail.no_order,
            order_detail.no_quotation,
            order_detail.nama_perusahaan,
            order_detail.konsultan,
            order_detail.periode,
            order_detail.kontrak,
            link_lhp.is_completed,
            link_lhp.jumlah_lhp_rilis
        ');

        // HAVING untuk tanggal_sampling_min
        if ($request->filled('tanggal_sampling')) {
            $rekapOrder->having('tanggal_sampling_min', 'like', '%' . $request->tanggal_sampling . '%');
        }

        // ORDER BY menggunakan nama alias yang benar
        $rekapOrder->orderBy('tanggal_sampling_min', 'asc');

        return DataTables::of($rekapOrder)
            ->addColumn('cfr_list', function ($data) {
                return explode(',', $data->cfr);
            })
            ->addColumn('is_completed_auto', function ($data) {
                return (int) $data->jumlah_lhp_rilis === (int) $data->total_cfr;
            })
            ->addColumn('jumlah_lhp_ob', function ($data) {
                // Static cache agar tidak query berulang untuk no_order yang sama
                static $cache = [];
                $noOrder = $data->no_order;
                if (!array_key_exists($noOrder, $cache)) {
                    $raw = DB::table('order_berjalan')
                        ->where('no_order', $noOrder)
                        ->value('dataOrderDetail');
                    $cache[$noOrder] = $raw;
                }
                $raw = $cache[$noOrder];
                if (empty($raw)) return null;

                $groups = collect(json_decode($raw, true) ?? []);

                // Kontrak: filter grup sesuai periode yang sedang di-render
                if ($data->kontrak === 'C' && !empty($data->periode)) {
                    $groups = $groups->where('periode', $data->periode)->values();
                }

                // Parse atribut 'proses' format "selesai/total"
                $total   = 0;
                $selesai = 0;
                foreach ($groups as $group) {
                    [$s, $t] = array_pad(explode('/', $group['proses'] ?? '0/0', 2), 2, 0);
                    $selesai += (int) $s;
                    $total   += (int) $t;
                }

                return ['total' => $total, 'selesai' => $selesai];
            })
            ->addColumn('catatan_keterangan', function ($data) {
                static $catatanCache = [];
                $key = $data->no_order . '_' . ($data->kontrak === 'C' ? $data->periode : '');
                
                if (!array_key_exists($key, $catatanCache)) {
                    $cat = DB::table('catatan_lhp_rilis')
                        ->where('no_order', $data->no_order)
                        ->when($data->kontrak === 'C', fn($q) => $q->where('periode', $data->periode))
                        ->when($data->kontrak !== 'C', fn($q) => $q->whereNull('periode'))
                        ->first();
                    $catatanCache[$key] = $cat ? $cat->keterangan : null;
                }
                
                return $catatanCache[$key];
            })
            ->filterColumn('no_order', function ($query, $keyword) {
                $query->where('order_detail.no_order', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_quotation', function ($query, $keyword) {
                $query->where('order_detail.no_quotation', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where('order_detail.nama_perusahaan', 'like', '%' . $keyword . '%')
                    ->orWhere('order_detail.konsultan', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('tanggal_sampling_min', function ($query, $keyword) {
                $query->where('order_detail.tanggal_sampling', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('tipe_quotation', function ($query, $keyword) {
                if (stripos('kon', strtolower($keyword)) !== false) {
                    $query->where('order_detail.kontrak', 'C');
                } elseif (stripos('non', strtolower($keyword)) !== false || stripos('non-kontrak', $keyword) !== false) {
                    $query->where('order_detail.kontrak', '!=', 'C');
                }
            })
            ->make(true);
    }

    public function getGroupedCFR(Request $request)
    {
        $orderBerjalan = DB::table('order_berjalan')
            ->where('no_order', $request->no_order)
            ->first();

        if (is_null($orderBerjalan)) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $dataOrderDetail = is_string($orderBerjalan->dataOrderDetail)
            ? json_decode($orderBerjalan->dataOrderDetail, true)
            : $orderBerjalan->dataOrderDetail;

        if (!is_array($dataOrderDetail)) {
            $dataOrderDetail = [];
        }

        $periodeFilter = $request->periode;

        $groupedCFRs = collect($dataOrderDetail)
            ->when($periodeFilter, fn($items) => $items->where('periode', $periodeFilter)->values())
            ->flatMap(function ($periodeGroup) use ($periodeFilter) {
                $details     = $periodeGroup['detail'] ?? [];
                $groupPeriode = $periodeGroup['periode'] ?? $periodeFilter;

                return collect($details)->map(function ($detail) use ($groupPeriode) {
                    $sampleNumbers = $detail['sampelNumbers'] ?? [];
                    $points        = $detail['points'] ?? [];

                    // kategori_3 harus berupa array (bisa dari 'categories' atau string 'kategori_3')
                    $rawKat3 = $detail['categories'] ?? [];
                    if (empty($rawKat3) && !empty($detail['kategori_3'])) {
                        $rawKat3 = [$detail['kategori_3']];
                    }

                    return [
                        'cfr'            => $detail['cfr'] ?? null,
                        'periode'        => $groupPeriode,
                        'no_sampel'      => $sampleNumbers,           // array of string
                        'total_no_sampel'=> $detail['jumlah_sampel'] ?? count($sampleNumbers),
                        'keterangan_1'   => $points,                  // array, alias 'points'
                        'kategori_2'     => $detail['kategori_2'] ?? null,
                        'kategori_3'     => $rawKat3,                 // array
                        'steps'          => $detail['steps'] ?? null,
                        'lhp_rilis'      => $detail['lhp_rilis'] ?? false,
                        'tgl_lhp_rilis'  => $detail['tgl_lhp_rilis'] ?? null,
                        'parameter'      => $detail['parameter'] ?? [],
                        'regulasi'       => $detail['regulasi'] ?? [],
                    ];
                });
            })
            ->filter(fn($item) => !empty($item['cfr']))
            ->values();

        // Ambil catatan untuk order ini (filter periode jika kontrak)
        $catatan = CatatanLhpRilis::where('no_order', $orderBerjalan->no_order)
            ->when($periodeFilter, fn($q) => $q->where('periode', $periodeFilter))
            ->when(!$periodeFilter, fn($q) => $q->whereNull('periode'))
            ->first();

        return response()->json([
            'no_order'          => $orderBerjalan->no_order,
            'no_document'       => $orderBerjalan->no_penawaran,
            'nama_perusahaan'   => $orderBerjalan->nama_perusahaan,
            'konsultan'         => null,
            'tanggal_penawaran' => $orderBerjalan->tgl_penawaran,
            'tanggal_order'     => $orderBerjalan->tgl_order,
            'groupedCFRs'       => $groupedCFRs,
            'catatan'           => $catatan ? [
                'keterangan' => $catatan->keterangan,
                'created_at' => $catatan->created_at,
            ] : null,
        ], 200);
    }

    public function getCatatan(Request $request)
    {
        $catatan = CatatanLhpRilis::where('no_order', $request->no_order)
            ->when($request->filled('periode'), fn($q) => $q->where('periode', $request->periode))
            ->when(!$request->filled('periode'), fn($q) => $q->whereNull('periode'))
            ->first();

        return response()->json(['data' => $catatan], 200);
    }

    public function saveCatatan(Request $request)
    {
        try {
            //code...
            $this->validate($request, [
                'no_order'   => 'required|string',
                'keterangan' => 'required|string',
            ]);
    
            $existing = CatatanLhpRilis::where('no_order', $request->no_order)
                ->when($request->filled('periode'), fn($q) => $q->where('periode', $request->periode))
                ->when(!$request->filled('periode'), fn($q) => $q->whereNull('periode'))
                ->first();
    
            if ($existing) {
                $existing->keterangan = $request->keterangan;
                $existing->save();
                $catatan = $existing;
            } else {
                $catatan = CatatanLhpRilis::create([
                    'no_order'   => $request->no_order,
                    'periode'    => $request->periode ? $request->periode : null,
                    'keterangan' => $request->keterangan,
                    'created_at' => Carbon::now(),
                    'created_by' => $this->karyawan ?? null,
                ]);
            }
    
            return response()->json([
                'message' => 'Catatan berhasil disimpan',
                'data'    => $catatan,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line'    => $th->getLine(),
                'file'    => $th->getFile(),
            ], 400);
        }
    }
}
