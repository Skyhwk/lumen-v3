<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LinkLhp;
use App\Models\OrderDetail;
use Illuminate\Http\Request;

use Datatables;
use App\Models\{OrderHeader,QuotationKontrakH, QuotationNonKontrak, LiburPerusahaan};
use App\Services\GroupedCfrByLhp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MonitoringLhpController extends Controller
{
    public function index(Request $request)
    {
        $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
        $id_jabatan = [21,24,157, 15, 148]; // Can't View All
        $getBawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->toArray();
        $workDay = Carbon::now()->subWeekdays(10);

        $liburPerusahaan = LiburPerusahaan::where('tanggal', '>=', $workDay)->get();
        $workDayWithLibur = $workDay->subWeekdays($liburPerusahaan->count());

        $linkLhpQuery = LinkLhp::select(
            'no_order',
            'is_completed',
            'jumlah_lhp_rilis',
            'periode'
        );

        $rekapOrder = DB::table('order_detail')
            ->selectRaw('
                order_detail.no_order,
                order_detail.no_quotation,
                order_detail.cfr as no_lhp,
                order_detail.nama_perusahaan,
                order_detail.konsultan,
                order_detail.periode,
                order_detail.kontrak,
                MAX(order_detail.kategori_1) as status_sampling,
                MIN(order_detail.tanggal_sampling) as tanggal_sampling,
                MIN(order_detail.is_approve) as is_approve,
                MIN(order_detail.status) as status
            ')
            ->leftJoinSub($linkLhpQuery, 'link_lhp', function ($join) {
                $join->on('order_detail.no_order', '=', 'link_lhp.no_order');
            })
            ->leftJoin('order_header', 'order_detail.no_order', '=', 'order_header.no_order')
            ->where('order_detail.tanggal_sampling','<=', $workDayWithLibur)
            ->where('order_detail.is_active', true)
            ->where('order_detail.is_approve', false)
            ->where('order_detail.status', '<>', 3);
        if(in_array($jabatan, [21,15, 157])){
            $rekapOrder->whereIn('sales_id', $getBawahan);
        }else if(in_array($jabatan, [24,148])){
            $rekapOrder->where('sales_id', $this->karyawan);
        }

        /*
        |--------------------------------------------------------------------------
        | FILTER is_completed
        |--------------------------------------------------------------------------
        */

        if ($request->filled('is_completed')) {

            if ($request->is_completed == 'true' || $request->is_completed == 1) {

                $rekapOrder->where('link_lhp.is_completed', true);

            } else {

                $rekapOrder->where(function ($q) {
                    $q->whereNull('link_lhp.is_completed')
                    ->orWhere('link_lhp.is_completed', false);
                });
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Hanya yang sudah ada LHP rilis
        |--------------------------------------------------------------------------
        */

        $rekapOrder->where(function ($q) {
            $q->whereNotNull('link_lhp.jumlah_lhp_rilis')
            ->where('link_lhp.jumlah_lhp_rilis', '>', 0);
        });

        /*
        |--------------------------------------------------------------------------
        | FILTER tanggal_sampling / periode
        |--------------------------------------------------------------------------
        */

        $rekapOrder->when($request->filled('tanggal_sampling'), function ($q) use ($request) {

            $periode = $request->tanggal_sampling;

            $q->where(function ($sub) use ($periode) {

                $sub->where(function ($f) use ($periode) {
                    $f->where('order_detail.kontrak', 'C')
                    ->where('order_detail.periode', $periode)
                    ->where('link_lhp.periode', $periode);
                });

                $sub->orWhere(function ($f) {
                    $f->where('order_detail.kontrak', '!=', 'C');
                });

            });
        });

        /*
        |--------------------------------------------------------------------------
        | GROUP BY sesuai permintaan
        |--------------------------------------------------------------------------
        */

        $rekapOrder->groupBy(
            'order_detail.no_order',
            'order_detail.no_quotation',
            'order_detail.nama_perusahaan',
            'order_detail.konsultan',
            'order_detail.periode',
            'order_detail.kontrak',
            'order_detail.cfr'
        );

        /*
        |--------------------------------------------------------------------------
        | HAVING untuk tanggal sampling
        |--------------------------------------------------------------------------
        */

        if ($request->filled('tanggal_sampling')) {
            $rekapOrder->having('tanggal_sampling', 'like', '%' . $request->tanggal_sampling . '%');
        }

        $rekapOrder->orderBy('tanggal_sampling', 'asc');

        return DataTables::of($rekapOrder)
            ->addColumn('total_keterlambatan', function ($data) {

                if (!$data->tanggal_sampling) {
                    return 0;
                }

                $start = Carbon::parse($data->tanggal_sampling);
                $end   = Carbon::now();

                // hitung weekday
                $weekdays = $start->diffInWeekdays($end);

                // ambil libur perusahaan di range tanggal
                $libur = LiburPerusahaan::whereBetween('tanggal', [$start, $end])
                    ->where('is_active', true)
                    ->get()
                    ->filter(function ($item) {
                        return !Carbon::parse($item->tanggal)->isWeekend();
                    })
                    ->count();

                return $weekdays - $libur;
            })

            ->addColumn('sales_penanggung_jawab', function ($data) {
                $isKontrak = $data->kontrak == 'C' ? true : false;
                if($isKontrak) {
                    $quotation = QuotationKontrakH::with('sales')->where('no_document', $data->no_quotation)->first();
                    return $quotation->sales->nama_lengkap;
                }else{
                    $quotation = QuotationNonKontrak::with('sales')->where('no_document', $data->no_quotation)->first();
                    return $quotation->sales->nama_lengkap;
                }
            })

            ->filterColumn('no_order', function ($query, $keyword) {
                $query->where('order_detail.no_order', 'like', '%' . $keyword . '%');
            })

            ->filterColumn('no_quotation', function ($query, $keyword) {
                $query->where('order_detail.no_quotation', 'like', '%' . $keyword . '%');
            })

            ->filterColumn('no_lhp', function ($query, $keyword) {
                $query->where('order_detail.cfr', 'like', '%' . $keyword . '%');
            })

            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where('order_detail.nama_perusahaan', 'like', '%' . $keyword . '%')
                    ->orWhere('order_detail.konsultan', 'like', '%' . $keyword . '%');
            })

            ->filterColumn('tanggal_sampling', function ($query, $keyword) {
                $query->where('order_detail.tanggal_sampling', 'like', '%' . $keyword . '%');
            })

            ->filterColumn('tipe_quotation', function ($query, $keyword) {

                $keyword = strtolower($keyword);

                if (stripos($keyword, 'kon') !== false) {
                    $query->where('order_detail.kontrak', 'C');
                }

                if (stripos($keyword, 'non') !== false) {
                    $query->where('order_detail.kontrak', '!=', 'C');
                }
            })

            ->make(true);
    }

    public function getGroupedCFR(Request $request)
    {
        $orderHeader = OrderHeader::where('is_active', true)
            ->where('no_order', $request->no_order)
            ->first();
        if (is_null($orderHeader)) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $groupedCFRs = (new GroupedCfrByLhp($orderHeader, $request->periode))->get();

        $groupedCFRs = $groupedCFRs->filter(function ($item) use ($request) {
            return $item['cfr'] == $request->no_lhp;
        })->values();
        return response()->json([
            'no_order' => $orderHeader->no_order,
            'no_document' => $orderHeader->no_document,
            'nama_perusahaan' => $orderHeader->nama_perusahaan,
            'konsultan' => $orderHeader->konsultan,
            'tanggal_penawaran' => $orderHeader->tanggal_penawaran,
            'tanggal_order' => $orderHeader->tanggal_order,
            'groupedCFRs' => $groupedCFRs
        ], 200);
    }
}
