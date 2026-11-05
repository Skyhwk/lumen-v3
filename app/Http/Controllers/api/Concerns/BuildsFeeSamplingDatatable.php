<?php

namespace App\Http\Controllers\api\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

trait BuildsFeeSamplingDatatable
{
    protected function feeSamplingListQuery(Builder $query): Builder
    {
        return $query
            ->with(['detail_fee' => function ($q) {
                $q->where('is_approve', 1);
            }])
            ->select('pengajuan_fee_sampling.*')
            ->selectRaw(
                '(select count(*) from pengajuan_fee_sampling_detail where pengajuan_fee_sampling_id = pengajuan_fee_sampling.id) as jumlah_pengajuan'
            );
    }

    protected function buildFeeSamplingDatatable(Builder $query)
    {
        return Datatables::of($this->feeSamplingListQuery($query))
            ->addColumn('jumlah_pengajuan', function ($row) {
                return (int) ($row->jumlah_pengajuan ?? collect($row->detail_fee)->count());
            })
            ->addColumn('tgl_approve', function ($row) {
                $max = collect($row->detail_fee)
                    ->where('is_approve', 1)
                    ->pluck('approved_at')
                    ->filter()
                    ->max();

                return $max ?: null;
            })
            ->addColumn('approved_by', function ($row) {
                $detail = collect($row->detail_fee)
                    ->where('is_approve', 1)
                    ->sortByDesc('approved_at')
                    ->first();

                return $detail ? ($detail->approved_by ?: null) : null;
            })
            ->filterColumn('tgl_pengajuan', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }
                $date = strlen($keyword) >= 10 ? substr($keyword, 0, 10) : $keyword;
                $query->whereDate('tgl_pengajuan', $date);
            })
            ->filterColumn('jumlah_pengajuan', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '' || !is_numeric($keyword)) {
                    return;
                }
                $count = (int) $keyword;
                $query->whereRaw(
                    '(select count(*) from pengajuan_fee_sampling_detail where pengajuan_fee_sampling_id = pengajuan_fee_sampling.id) = ?',
                    [$count]
                );
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->whereRaw('LOWER(created_by) LIKE ?', [strtolower("%{$keyword}%")]);
            })
            ->filterColumn('total_fee_approve', function ($query, $keyword) {
                $query->whereRaw('CAST(total_fee_approve AS CHAR) LIKE ?', ["%{$keyword}%"]);
            })
            ->filterColumn('status_payment', function ($query, $keyword) {
                $query->whereRaw('LOWER(status_payment) LIKE ?', [strtolower("%{$keyword}%")]);
            })
            ->filterColumn('transfer_date', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }
                $date = strlen($keyword) >= 10 ? substr($keyword, 0, 10) : $keyword;
                $query->whereDate('transfer_date', $date);
            })
            ->filterColumn('tgl_approve', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }
                $date = strlen($keyword) >= 10 ? substr($keyword, 0, 10) : $keyword;
                $query->whereHas('detail_fee', function ($q) use ($date) {
                    $q->where('is_approve', 1)
                        ->whereDate('approved_at', $date);
                });
            })
            ->filterColumn('approved_by', function ($query, $keyword) {
                $keyword = trim($keyword);
                if ($keyword === '') {
                    return;
                }
                $query->whereHas('detail_fee', function ($q) use ($keyword) {
                    $q->where('is_approve', 1)
                        ->whereRaw('LOWER(approved_by) LIKE ?', [strtolower("%{$keyword}%")]);
                });
            })
            ->make(true);
    }

    protected function feeSamplingSummaryJson(Builder $query, Request $request)
    {
        if ($request->filled('tgl_pengajuan')) {
            $date = substr(trim($request->tgl_pengajuan), 0, 10);
            $query->whereDate('tgl_pengajuan', $date);
        }

        return response()->json([
            'data' => (float) $query->sum('total_fee_approve'),
        ]);
    }
}
