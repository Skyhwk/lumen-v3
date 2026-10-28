<?php

namespace App\Http\Controllers\Concerns;

use App\Models\MasterKaryawan;
use Illuminate\Database\Eloquent\Builder;

trait BuildsHrisEmployeeIndexDatatable
{
    protected function hrisEmployeeBaseQuery(): Builder
    {
        return MasterKaryawan::with(['jabatan', 'cabang'])
            ->where('is_active', true)
            ->where('grade', '!=', 'DIRECTOR');
    }

    protected function applyHrisEmployeeContextColumns($datatable)
    {
        return $datatable
            ->addColumn('jabatan', function ($row) {
                return optional($row->jabatan)->nama_jabatan ?? ($row->jabatan ?? '-');
            })
            ->addColumn('cabang', function ($row) {
                return optional($row->cabang)->nama_cabang ?? '-';
            })
            ->filterColumn('jabatan', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('jabatan', function ($sub) use ($keyword) {
                        $sub->where('nama_jabatan', 'like', '%' . $keyword . '%');
                    })->orWhere('jabatan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('cabang', function ($query, $keyword) {
                $query->whereHas('cabang', function ($sub) use ($keyword) {
                    $sub->where('nama_cabang', 'like', '%' . $keyword . '%');
                });
            });
    }

    protected function applyActiveScope($query)
    {
        return $query->where(function ($q) {
            $q->where('is_active', 1)->orWhere('is_active', true)->orWhereNull('is_active');
        });
    }
}
