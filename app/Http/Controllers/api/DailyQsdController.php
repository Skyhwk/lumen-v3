<?php
namespace App\Http\Controllers\api;

date_default_timezone_set('Asia/Jakarta');

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class DailyQsdController extends Controller
{

    public function index(Request $request)
    {
        $query = DB::table('daily_qsd')
            ->select(
                'daily_qsd.no_order',
                'daily_qsd.no_quotation',
                'daily_qsd.periode',
                'daily_qsd.tanggal_sampling_min',
                'daily_qsd.nama_perusahaan',
                'daily_qsd.konsultan',
                'daily_qsd.kontrak',
                'daily_qsd.sales_nama',
                'daily_qsd.total_discount',
                'daily_qsd.total_ppn',
                'daily_qsd.total_pph',
                'daily_qsd.biaya_akhir',
                'daily_qsd.grand_total',
                'daily_qsd.total_revenue',
                DB::raw('MAX(kelengkapan_konfirmasi_qs.approval_order) as approval_order'),
                DB::raw('MAX(kelengkapan_konfirmasi_qs.no_purchaseorder) as no_purchaseorder'),
                DB::raw('MAX(kelengkapan_konfirmasi_qs.no_co_qsd) as no_co_qsd'),
                DB::raw('GROUP_CONCAT(DISTINCT invoice.no_invoice SEPARATOR ", ") as no_invoice'),

            )->groupBy(
            'daily_qsd.no_order',
            'daily_qsd.no_quotation',
            'daily_qsd.periode',
            'daily_qsd.tanggal_sampling_min',
            'daily_qsd.nama_perusahaan',
            'daily_qsd.konsultan',
            'daily_qsd.kontrak',
            'daily_qsd.sales_nama',
            'daily_qsd.total_discount',
            'daily_qsd.total_ppn',
            'daily_qsd.total_pph',
            'daily_qsd.biaya_akhir',
            'daily_qsd.grand_total',
            'daily_qsd.total_revenue'
        );

        $query->leftJoin(DB::raw('(
            SELECT *
            FROM kelengkapan_konfirmasi_qs
            WHERE keterangan_approval_order != "menyusul"
            AND is_active = 1
        ) as kelengkapan_konfirmasi_qs'), function ($join) {
            $join->on('daily_qsd.no_quotation', '=', 'kelengkapan_konfirmasi_qs.no_quotation')
                ->on('daily_qsd.periode', '=', 'kelengkapan_konfirmasi_qs.periode');
        });

        $query->leftJoin('invoice', function ($join) {
            $join->on('daily_qsd.no_quotation', '=', 'invoice.no_quotation')
                ->where(function ($q) {
                    $q->where('invoice.periode', 'all')
                        ->orWhereColumn('invoice.periode', 'daily_qsd.periode');
                    $q->where('invoice.is_active', 1);
                });

        });

        // Filter tanggal_sampling jika ada
        if ($request->filled('tanggal_sampling')) {
            $tanggalInput = $request->tanggal_sampling;

            // Deteksi format input
            if (strlen($tanggalInput) == 4 && is_numeric($tanggalInput)) {
                // Format: Y (tahun saja) - 2024
                $query->whereRaw("YEAR(tanggal_sampling_min) = ?", [$tanggalInput]);
            } elseif (strlen($tanggalInput) == 7 && strpos($tanggalInput, '-') !== false) {
                // Format: Y-m (bulan) - 2024-01
                $query->whereRaw("DATE_FORMAT(tanggal_sampling_min, '%Y-%m') = ?", [$tanggalInput]);
            } else {
                // Format: Y-m-d (tanggal lengkap) - 2024-01-15
                $query->whereDate('tanggal_sampling_min', $tanggalInput);
            }
        }

        // Filter periode untuk data kontrak
        if ($request->filled('periode')) {
            $periodeInput = $request->periode;

            if (strlen($periodeInput) == 4 && is_numeric($periodeInput)) {
                // Filter tahun periode
                $query->where(function ($q) use ($periodeInput) {
                    $q->where('kontrak', 'C')
                        ->whereRaw("LEFT(periode, 4) = ?", [$periodeInput]);
                });
            } elseif (strlen($periodeInput) == 7 && strpos($periodeInput, '-') !== false) {
                // Filter bulan periode
                $query->where(function ($q) use ($periodeInput) {
                    $q->where('kontrak', 'C')
                        ->where('periode', $periodeInput);
                });
            }
        }

        // Default ordering
        $query->orderBy('tanggal_sampling_min', 'desc')
            ->orderBy('no_order', 'asc');

        return DataTables::of($query)
            ->addColumn('tipe_quotation', function ($data) {
                return $data->kontrak == 'C' ? 'KONTRAK' : 'NON-KONTRAK';
            })
            ->addColumn('periode_aktif', function ($data) {
                if ($data->kontrak == 'C' && isset($data->periode)) {
                    return $data->periode;
                }
                return '-';
            })
            ->addColumn('sales_id', function ($data) {
                return $data->sales_id ?? '-';
            })
            ->addColumn('sales_nama', function ($data) {
                return $data->sales_nama ?? '-';
            })
            ->addColumn('total_revenue', function ($data) {
                return $data->total_revenue ?? 0;
            })
            ->filterColumn('no_order', function ($query, $keyword) {
                $query->where('daily_qsd.no_order', 'like', "%$keyword%");
            })
            ->filterColumn('no_quotation', function ($query, $keyword) {
                $query->where('daily_qsd.no_quotation', 'like', "%$keyword%");
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where('daily_qsd.nama_perusahaan', 'like', "%$keyword%");
            })
            ->filterColumn('konsultan', function ($query, $keyword) {
                $query->where('daily_qsd.konsultan', 'like', "%$keyword%");
            })
            ->filterColumn('tanggal_sampling_min', function ($query, $keyword) {
                $query->whereDate('daily_qsd.tanggal_sampling_min', 'like', "%$keyword%");
            })
            ->filterColumn('tipe_quotation', function ($query, $keyword) {
                $keyword = strtolower($keyword);
                if (strpos($keyword, 'kon') !== false) {
                    $query->where('kontrak', 'C');
                } elseif (strpos($keyword, 'non') !== false) {
                    $query->where('kontrak', '!=', 'C');
                }
            })
            ->filterColumn('sales_nama', function ($query, $keyword) {
                $keyword = strtolower($keyword);
                $query->where(function ($q) use ($keyword) {
                    $q->where('sales_nama', 'like', "%$keyword%");

                });
            })
            ->filterColumn('no_co_qsd', function ($query, $keyword) {
                $query->where('kelengkapan_konfirmasi_qs.no_co_qsd', 'like', "%$keyword%");
            })
            ->filterColumn('no_purchaseorder', function ($query, $keyword) {
                $query->where('kelengkapan_konfirmasi_qs.no_purchaseorder', 'like', "%$keyword%");
            })
            ->filterColumn('approval_order', function ($query, $keyword) {
                $query->where('kelengkapan_konfirmasi_qs.approval_order', 'like', "%$keyword%");
            })
            ->filterColumn('no_invoice', function ($query, $keyword) {
                $query->where('invoice.no_invoice', 'like', "%$keyword%");
            })
            ->orderColumn('tanggal_sampling_min', function ($query, $order) {
                $query->orderBy('tanggal_sampling_min', $order);
            })
            ->orderColumn('periode', function ($query, $order) {
                $query->orderBy('periode', $order);
            })
            ->make(true);
    }

    public function getTotalRevenue(Request $request)
    {
        $currentYear  = Carbon::now()->format('Y');
        $currentMonth = Carbon::now()->format('Y-m');
        $currentDate  = Carbon::now()->format('Y-m-d');

        // $currentYear  = $request->year;
        // $currentMonth = $currentYear . '-' . Carbon::now()->format('m');

        // Total Revenue Per Tahun
        $yearRevenue = DB::table('daily_qsd')
            ->whereRaw("YEAR(tanggal_sampling_min) = ?", [$currentYear])
            ->selectRaw('
                SUM(COALESCE(total_revenue, 0)) as total
            ')
            ->first();

        // Total Revenue Per Bulan
        $monthRevenue = DB::table('daily_qsd')
            ->whereRaw("DATE_FORMAT(tanggal_sampling_min, '%Y-%m') = ?", [$currentMonth])
            ->selectRaw('
                SUM(COALESCE(total_revenue, 0)) as total
            ')
            ->first();

        // Total Revenue Per Hari (Today)
        $dayRevenue = DB::table('daily_qsd')
            ->whereDate('tanggal_sampling_min', $currentDate)
            ->selectRaw('
                SUM(COALESCE(total_revenue, 0)) as total
            ')
            ->first();

        return response()->json([
            'year_revenue'  => $yearRevenue->total ?? 0,
            'month_revenue' => $monthRevenue->total ?? 0,
            'day_revenue'   => $dayRevenue->total ?? 0,
        ]);
    }
}
