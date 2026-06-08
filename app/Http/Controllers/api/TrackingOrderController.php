<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\TrackingOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class TrackingOrderController extends Controller
{
    private const MIN_YEAR = 2026;

    private const DATE_COLUMN = 'COALESCE(tanggal_awal_sampling)';

    public function berjalan(Request $request)
    {
        return $this->buildDatatable($this->baseQuery($request, false));
    }

    public function selesai(Request $request)
    {
        return $this->buildDatatable($this->baseQuery($request, true));
    }

    private function resolveYear($year): int
    {
        $currentYear = (int) Carbon::now()->year;
        $year = (int) ($year ?: $currentYear);

        return max(self::MIN_YEAR, min($currentYear, $year));
    }

    private function baseQuery(Request $request, bool $isSelesai): Builder
    {
        $currentYear = (int) Carbon::now()->year;
        $year = $this->resolveYear($request->year);

        $query = TrackingOrder::query()
            ->where('is_selesai', $isSelesai ? 1 : 0)
            ->whereYear(\DB::raw(self::DATE_COLUMN), $year);

        if (!$isSelesai && $year === $currentYear) {
            $query->whereRaw(self::DATE_COLUMN . ' <= ?', [Carbon::now()->toDateString()]);
        }

        return $query;
    }

    private function buildDatatable(Builder $data)
    {
        return Datatables::of($data)
            ->filterColumn('progress', function ($query, $keyword) {
                $query->where('progress', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal_awal_sampling', function ($query, $keyword) {
                $query->where('tanggal_awal_sampling', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal_terakhir_lhp_rilis', function ($query, $keyword) {
                $query->where('tanggal_terakhir_lhp_rilis', 'like', "%{$keyword}%");
            })
            ->filterColumn('no_order', function ($query, $keyword) {
                $query->where('no_order', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal_order', function ($query, $keyword) {
                $query->where('tanggal_order', 'like', "%{$keyword}%");
            })
            ->filterColumn('no_quotation', function ($query, $keyword) {
                $query->where('no_quotation', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal_penawaran', function ($query, $keyword) {
                $query->where('tanggal_penawaran', 'like', "%{$keyword}%");
            })
            ->filterColumn('no_invoice', function ($query, $keyword) {
                if ($keyword === '-') {
                    $query->whereNull('no_invoice');
                } else {
                    $query->where('no_invoice', 'like', "%{$keyword}%");
                }
            })
            ->filterColumn('tanggal_pembayaran', function ($query, $keyword) {
                $query->where('tanggal_pembayaran', 'like', "%{$keyword}%");
            })
            ->filterColumn('tipe_quotation', function ($query, $keyword) {
                if ($keyword === '') {
                    $query->whereIn('kontrak', ['C', 'N']);
                } else {
                    $query->where('kontrak', $keyword);
                }
            })
            ->filterColumn('periode', function ($query, $keyword) {
                $query->where('periode', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where('nama_perusahaan', 'like', "%{$keyword}%");
            })
            ->filterColumn('konsultan', function ($query, $keyword) {
                $query->where('konsultan', 'like', "%{$keyword}%");
            })
            ->filterColumn('status_sampling', function ($query, $keyword) {
                if ($keyword !== 'Gabungan') {
                    $query->where('status_sampling', $keyword);
                } else {
                    $query->where('status_sampling', 'like', '%,%');
                }
            })
            ->filterColumn('sales_nama', function ($query, $keyword) {
                $query->where('sales_nama', 'like', "%{$keyword}%");
            })
            ->filterColumn('is_lunas', function ($query, $keyword) {
                if ($keyword === '1') {
                    $query->where('is_lunas', 1);
                } elseif ($keyword === '0') {
                    $query->where('is_lunas', 0);
                }
            })
            ->order(function ($query) {
                $query->orderByDesc('tanggal_awal_sampling')
                    ->orderBy('no_order');
            })
            ->make(true);
    }
}
