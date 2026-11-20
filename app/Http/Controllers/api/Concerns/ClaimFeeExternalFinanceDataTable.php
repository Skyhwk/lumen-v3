<?php

namespace App\Http\Controllers\api\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

trait ClaimFeeExternalFinanceDataTable
{
    protected function makeClaimFeeExternalFinanceDataTable(Builder $query, Request $request)
    {
        return DataTables::of($query)
            ->filter(function (Builder $q) use ($request) {
                $this->applyClaimFeeExternalFinanceColumnFilters($q, $request);
            })
            ->withQuery('sum_nominal', static function (Builder $q) {
                return (float) $q->sum('nominal');
            })
            ->withQuery('sum_potongan', static function (Builder $q) {
                return (float) $q->sum(DB::raw('COALESCE(potongan, 0)'));
            })
            ->withQuery('sum_nominal_bayar', static function (Builder $q) {
                return (float) $q->sum(DB::raw('COALESCE(nominal_bayar, 0)'));
            })
            ->make(true);
    }

    protected function applyClaimFeeExternalFinanceColumnFilters(Builder $query, Request $request): void
    {
        foreach ($request->columns ?? [] as $column) {
            $value = trim((string) ($column['search']['value'] ?? ''));
            $field = $column['data'] ?? null;

            if ($value === '' || $field === null || $field === '') {
                continue;
            }

            switch ($field) {
                case 'no_order':
                case 'nama_penerima':
                case 'keterangan_tambahan':
                case 'nama_bank':
                case 'metode_pembayaran':
                case 'nama_perusahaan':
                case 'no_quotation':
                case 'no_invoice':
                case 'no_quotation':
                case 'created_by':
                case 'periode':
                    $query->where($field, 'like', '%' . $value . '%');
                    break;
                case 'status_pembayaran':
                    $query->where('status_pembayaran', $value);
                    break;
                case 'nominal':
                case 'potongan':
                case 'nominal_bayar':
                    if (is_numeric(str_replace([',', '.'], '', $value))) {
                        $digits = preg_replace('/\D/', '', $value);
                        if ($digits !== '') {
                            $query->where($field, 'like', '%' . $digits . '%');
                        }
                    } else {
                        $query->where($field, 'like', '%' . $value . '%');
                    }
                    break;
                case 'due_date':
                case 'tanggal_pembayaran':
                    $this->applyClaimFeeExternalFinanceDateFilter($query, $field, $value);
                    break;
            }
        }
    }

    protected function applyClaimFeeExternalFinanceDateFilter(Builder $query, string $column, string $value): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $query->whereDate($column, $value);
        } elseif (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $query->whereYear($column, substr($value, 0, 4))
                ->whereMonth($column, substr($value, 5, 2));
        } elseif (preg_match('/^\d{4}$/', $value)) {
            $query->whereYear($column, $value);
        }
    }
}