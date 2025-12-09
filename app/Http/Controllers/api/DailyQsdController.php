<?php

namespace App\Http\Controllers\api;

date_default_timezone_set('Asia/Jakarta');

use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use App\Http\Controllers\Controller;
use App\Models\{LinkLhp, OrderHeader, OrderDetail};
use DB;

class DailyQsdController extends Controller
{
    public function index(Request $request)
    {
        $periodeFilter = null;
        $filterType = null; // 'year', 'month', 'date'

        /** 
         * Sebenarnya ga perlu sih ini kyknya cukup pakai today,
         * tapi biar mempersingkat pencarian kyknya harus 
         * menyesuaikan kondisi tanggal_sampling
         * */
        if ($request->filled('tanggal_sampling') && $request->tanggal_sampling == date('Y')) {
            $maxDate = Carbon::now()->format('Y-m-d');
        } elseif ($request->filled('tanggal_sampling') && $request->tanggal_sampling < date('Y')) {
            $maxDate = Carbon::createFromFormat('Y', $request->tanggal_sampling)->endOfYear()->format('Y-m-d');
        } else {
            $maxDate = Carbon::now()->format('Y-m-d');
        }
        
        if ($request->filled('tanggal_sampling')) {
            $tanggalInput = $request->tanggal_sampling;
            
            // Deteksi format input
            if (strlen($tanggalInput) == 4 && is_numeric($tanggalInput)) {
                // Format: Y (tahun saja) - 2024
                $periodeFilter = $tanggalInput;
                $filterType = 'year';
            } elseif (strlen($tanggalInput) == 7 && strpos($tanggalInput, '-') !== false) {
                // Format: Y-m (bulan) - 2024-01
                $periodeFilter = $tanggalInput;
                $filterType = 'month';
            } else {
                // Format: Y-m-d (tanggal lengkap) - 2024-01-15
                $periodeFilter = $tanggalInput;
                $filterType = 'date';
            }
        }

        $rekapOrder = DB::table('order_detail')
            ->selectRaw('
                order_detail.no_order,
                order_detail.no_quotation,
                GROUP_CONCAT(DISTINCT order_detail.cfr SEPARATOR ",") as cfr,
                COUNT(DISTINCT order_detail.cfr) AS total_cfr,
                order_detail.nama_perusahaan,
                order_detail.konsultan,
                MIN(CASE order_detail.kontrak WHEN "C" THEN rqkd.periode_kontrak ELSE NULL END) as periode,
                order_detail.kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.total_discount ELSE NULL END) as total_discount_kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.total_ppn ELSE NULL END) as total_ppn_kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.total_pph ELSE NULL END) as total_pph_kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.biaya_akhir ELSE NULL END) as biaya_akhir_kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.grand_total ELSE NULL END) as grand_total_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.total_discount ELSE NULL END) as total_discount_non_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.total_ppn ELSE NULL END) as total_ppn_non_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.total_pph ELSE NULL END) as total_pph_non_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.biaya_akhir ELSE NULL END) as biaya_akhir_non_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.grand_total ELSE NULL END) as grand_total_non_kontrak,
                MIN(order_detail.tanggal_sampling) as tanggal_sampling_min
            ')
            ->where('order_detail.is_active', true)
            ->whereDate('order_detail.tanggal_sampling', '<=', $maxDate); 

        /**
         * FILTER TANGGAL SAMPLING DI ORDER_DETAIL
         */
        if ($filterType) {
            switch ($filterType) {
                case 'year':
                    // Filter berdasarkan tahun saja
                    $rekapOrder->whereRaw("YEAR(order_detail.tanggal_sampling) = ?", [$periodeFilter]);
                    break;
                case 'month':
                    // Filter berdasarkan bulan
                    $rekapOrder->whereRaw("DATE_FORMAT(order_detail.tanggal_sampling, '%Y-%m') = ?", [$periodeFilter]);
                    break;
                case 'date':
                    // Filter berdasarkan tanggal lengkap
                    $rekapOrder->whereDate('order_detail.tanggal_sampling', $periodeFilter);
                    break;
            }
        }

        /**
         * JOIN UNTUK KONTRAK (C)
         */
        $rekapOrder->leftJoin('request_quotation_kontrak_H as rqkh', function ($join) {
            $join->on('order_detail.no_quotation', '=', 'rqkh.no_document')
                ->where('rqkh.is_active', true);
        });

        $rekapOrder->leftJoin('request_quotation_kontrak_D as rqkd', function ($join) use ($filterType, $periodeFilter) {
            $join->on('rqkh.id', '=', 'rqkd.id_request_quotation_kontrak_H');
            
            if ($filterType && $periodeFilter) {
                switch ($filterType) {
                    case 'year':
                        // Perbaikan: match tahun saja
                        $join->whereRaw("LEFT(rqkd.periode_kontrak, 4) = ?", [$periodeFilter]);
                        break;
                    case 'month':
                        $join->where('rqkd.periode_kontrak', '=', $periodeFilter);
                        break;
                    case 'date':
                        $monthFromDate = date('Y-m', strtotime($periodeFilter));
                        $join->where('rqkd.periode_kontrak', '=', $monthFromDate);
                        break;
                }
            }
        });


        /**
         * JOIN UNTUK NON-KONTRAK (!= C)
         */
        $rekapOrder->leftJoin('request_quotation as rq', function ($join) {
            $join->on('order_detail.no_quotation', '=', 'rq.no_document');
        });

        /**
         * FILTER UTAMA: Hanya tampilkan yang memenuhi kondisi
         */
        $rekapOrder->where(function ($query) use ($filterType, $periodeFilter) {
            $query->where(function ($q) use ($filterType, $periodeFilter) {
                // Untuk kontrak = C, harus ada relasi di rqkh (kontrak_H)
                $q->where('order_detail.kontrak', 'C')
                    ->whereNotNull('rqkh.id')
                    ->whereColumn('order_detail.periode', 'rqkd.periode_kontrak'); // ★ Tambahan penting

                if ($filterType && $periodeFilter) {
                    switch ($filterType) {
                        case 'year':
                            $q->whereNotNull('rqkd.id')
                            ->whereRaw("LEFT(rqkd.periode_kontrak, 4) = ?", [$periodeFilter]);
                            break;
                        case 'month':
                            $q->whereNotNull('rqkd.id')
                            ->where('rqkd.periode_kontrak', '=', $periodeFilter);
                            break;
                        case 'date':
                            $monthFromDate = date('Y-m', strtotime($periodeFilter));
                            $q->whereNotNull('rqkd.id')
                            ->where('rqkd.periode_kontrak', '=', $monthFromDate);
                            break;
                    }
                }
            })
            // Atau untuk non-kontrak, harus ada di request_quotation
            ->orWhere(function ($q) {
                $q->where('order_detail.kontrak', '!=', 'C')
                ->whereNotNull('rq.id');
            });
        });

        /**
         * GROUP BY yang TEPAT - termasuk periode_kontrak untuk kontrak
         */
        $rekapOrder->groupByRaw('
            order_detail.no_order,
            order_detail.no_quotation,
            order_detail.nama_perusahaan,
            order_detail.konsultan,
            order_detail.periode,
            order_detail.kontrak,
            CASE 
                WHEN order_detail.kontrak = "C" THEN rqkd.periode_kontrak 
                ELSE NULL 
            END
        ');

        /**
         * ORDER BY
         */
        $rekapOrder->orderBy('tanggal_sampling_min', 'desc')
                ->orderBy('order_detail.no_order', 'asc');


        return DataTables::of($rekapOrder)
            ->addColumn('cfr_list', function ($data) {
                return explode(',', $data->cfr);
            })
            ->addColumn('tipe_quotation', function ($data) {
                return $data->kontrak == 'C' ? 'KONTRAK' : 'NON-KONTRAK';
            })
            ->addColumn('periode_aktif', function ($data) {
                // Tampilkan periode kontrak jika ada
                if ($data->kontrak == 'C' && isset($data->periode_kontrak)) {
                    return $data->periode_kontrak;
                }
                return '-';
            })
            ->filterColumn('no_order', function ($query, $keyword) {
                $query->where('order_detail.no_order', 'like', "%$keyword%");
            })
            ->filterColumn('no_quotation', function ($query, $keyword) {
                $query->where('order_detail.no_quotation', 'like', "%$keyword%");
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where('order_detail.nama_perusahaan', 'like', "%$keyword%");
            })
            ->filterColumn('konsultan', function ($query, $keyword) {
                $query->where('order_detail.konsultan', 'like', "%$keyword%");
            })
            ->orderColumn('tanggal_sampling_min', function ($query, $keyword) {
                $query->orderBy('order_detail.tanggal_sampling_min', $keyword);
            })
            ->orderColumn('periode', function ($query, $keyword) {
                $query->orderBy('periode', $keyword);
            })
            ->filterColumn('tipe_quotation', function ($query, $keyword) {
                $keyword = strtolower($keyword);
                if (strpos($keyword, 'kon') !== false) {
                    $query->where('order_detail.kontrak', 'C');
                } elseif (strpos($keyword, 'non') !== false) {
                    $query->where('order_detail.kontrak', '!=', 'C');
                }
            })
            ->make(true);
    }
}
